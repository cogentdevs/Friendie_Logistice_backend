<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\Api\CustomerListingImageService;
use App\Services\EmailActivationService;
use App\Support\Api\CustomerListingFields;
use App\Support\Api\PublicData;
use App\Support\ApiResponse;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CustomerListingController
{
    private const COLUMNS = ['id','category_id','ad_type','title','description','image','weight','weight_price','currency',
        'from_country_id','from_city_id','from_area','from_address','to_address','to_country_id','to_city_id','to_area','departure_on','reached_on',
        'show_contact_info','isActive','isFeatured','created_at','updated_at'];

    private function query(Request $request): Builder
    {
        return DB::table('post_ads')->select(self::COLUMNS)->where('created_by', $request->user()->id);
    }

    private function owned(Request $request, string $id, bool $lock = false): object
    {
        abort_unless(filter_var($id, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) !== false, 404);
        $query = $this->query($request)->where('id', $id);
        $listing = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($listing, 404);

        return $listing;
    }

    private function lockCustomer(Request $request): void
    {
        $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
        abort_unless((int)$user->isActive === 1 && (int)$user->isVerified === 1
            && app(EmailActivationService::class)->customer($user), 403);
        abort_unless($user->tokens()->whereKey($request->user()->currentAccessToken()->getKey())->exists(), 401);
    }

    private function editable(object $listing): void
    {
        abort_unless(in_array((int)$listing->isActive, [0,1,2], true), 409);
    }

    private function limit(Request $request, string $operation, int $maximum, int $seconds): void
    {
        foreach (['account:'.$request->user()->id, 'ip:'.hash('sha256',(string)$request->ip())] as $scope) {
            $key = 'customer-listing-'.$operation.':'.$scope;
            if (RateLimiter::tooManyAttempts($key, $maximum)) {
                throw new HttpException(429, '', null, ['Retry-After'=>(string)max(1,RateLimiter::availableIn($key))]);
            }
            RateLimiter::hit($key, $seconds);
        }
    }

    private function input(Request $request, array $allowed): array
    {
        $unknown = array_diff(array_keys($request->all()), $allowed);
        if ($unknown !== []) throw ValidationException::withMessages(array_fill_keys($unknown, ['This field is not allowed.']));

        return $request->only($allowed);
    }

    private function serialize(object $row): array
    {
        // Owner-only editable data, deliberately no user joins or contact fields.
        $result = [];
        foreach (['id','category_id','from_country_id','from_city_id','to_country_id','to_city_id','weight'] as $key) {
            $result[$key] = $row->{$key} === null ? null : (int)$row->{$key};
        }
        foreach (['ad_type','title','description','currency','from_area','to_area','from_address','to_address','departure_on','reached_on','created_at','updated_at'] as $key) {
            $result[$key] = $row->{$key};
        }
        $result['weight_price'] = $row->weight_price === null ? null : (float)$row->weight_price;
        $result['status'] = (int)$row->isActive;
        $result['is_featured'] = (bool)$row->isFeatured;
        $result['show_contact_info'] = (int)$row->show_contact_info;
        $result['image_url'] = PublicData::image($row->image, 'frontend/img/listing');

        return $result;
    }

    public function index(Request $request): JsonResponse
    {
        $input = PublicData::input($request, PublicData::paginationRules(50) + [
            'search'=>'sometimes|string|min:1|max:100', 'status'=>['sometimes','integer',Rule::in([0,1,2])],
            'ad_type'=>['sometimes',Rule::in(['Carring','Send'])], 'sort'=>['sometimes',Rule::in(['latest','oldest','departure'])],
        ]);
        $query = $this->query($request);
        if (isset($input['status'])) $query->where('isActive', $input['status']);
        if (isset($input['ad_type'])) $query->where('ad_type', $input['ad_type']);
        if (isset($input['search'])) $query->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [PublicData::like(mb_strtolower($input['search']))]);
        match ($input['sort'] ?? 'latest') {
            'oldest'=>$query->orderBy('id'), 'departure'=>$query->orderBy('departure_on')->orderByDesc('id'),
            default=>$query->orderByDesc('id'),
        };
        $page = $query->paginate($input['per_page'] ?? 20, ['*'], 'page', $input['page'] ?? 1);

        return ApiResponse::success('Your listings retrieved successfully.', PublicData::page($page, $this->serialize(...)))
            ->header('Cache-Control','no-store, private');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        PublicData::input($request, []);

        return ApiResponse::success('Your listing retrieved successfully.', $this->serialize($this->owned($request,$id)))
            ->header('Cache-Control','no-store, private');
    }

    public function store(Request $request): JsonResponse
    {
        $this->limit($request,'create',10,3600);
        $input = $this->input($request, CustomerListingFields::EDITABLE);
        $listing = DB::transaction(function () use ($request,$input) {
            $this->lockCustomer($request);
            $data = CustomerListingFields::validate($input);
            if (array_key_exists('description', $data)) $data['description'] = \App\Support\ListingDescription::html($data['description']);
            $id = DB::table('post_ads')->insertGetId($data + ['created_by'=>$request->user()->id, 'isActive'=>1,
                'isFeatured'=>0,'show_contact_info'=>0,'image'=>'listing.png','created_at'=>now(),'updated_at'=>now()]);

            return $this->owned($request,(string)$id);
        });

        return ApiResponse::success('Listing created successfully.', $this->serialize($listing), 201)
            ->header('Cache-Control','no-store, private');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->limit($request,'update',30,900);
        $this->owned($request,$id);
        $input = $this->input($request, CustomerListingFields::EDITABLE);
        $listing = DB::transaction(function () use ($request,$id,$input) {
            $this->lockCustomer($request);$listing = $this->owned($request,$id,true);$this->editable($listing);
            $data = CustomerListingFields::validate($input,$listing);
            if (array_key_exists('description', $data)) $data['description'] = \App\Support\ListingDescription::html($data['description']);
            DB::table('post_ads')->where('id',$listing->id)->where('created_by',$request->user()->id)
                ->update($data + ['updated_at'=>now()]);

            return $this->owned($request,$id);
        });

        return ApiResponse::success('Listing updated successfully.', $this->serialize($listing))->header('Cache-Control','no-store, private');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->limit($request,'delete',10,900);
        $this->owned($request,$id);
        $this->input($request, []);

        // Existing hard-delete can orphan chats/reviews and has no concurrency-safe retention policy.
        // Never pretend deletion succeeded; no listing, relationship or media is deleted.
        return ApiResponse::error('Listing deletion is unavailable until a safe retention policy is approved.',409);
    }

    public function uploadImage(Request $request, string $id, CustomerListingImageService $images): JsonResponse
    {
        $this->limit($request,'image',10,3600);$this->owned($request,$id);
        $this->input($request,['image']);
        $data = $request->validate(['image'=>['required','file','image','mimetypes:image/jpeg,image/png,image/webp',
            'extensions:jpg,jpeg,png,webp','max:2048','dimensions:min_width=1,min_height=1,max_width=2048,max_height=2048']]);
        $name = null;
        try {
            $listing = DB::transaction(function () use ($request,$id,$data,$images,&$name) {
                $this->lockCustomer($request);$listing = $this->owned($request,$id,true);$this->editable($listing);
                $name = $images->store((int)$listing->id,$data['image']);
                DB::table('post_ads')->where('id',$listing->id)->where('created_by',$request->user()->id)
                    ->update(['image'=>$name,'updated_at'=>now()]);

                return $this->owned($request,$id);
            });
        } catch (\Throwable $exception) {
            if ($name !== null) $images->discardNew($name,(int)$id);
            throw $exception;
        }

        return ApiResponse::success('Listing image updated successfully.', ['image_url'=>PublicData::image($listing->image,'frontend/img/listing')]);
    }

    public function removeImage(Request $request, string $id): JsonResponse
    {
        $this->limit($request,'image',10,3600);$this->owned($request,$id);$this->input($request,[]);
        $listing = DB::transaction(function () use ($request,$id) {
            $this->lockCustomer($request);$listing = $this->owned($request,$id,true);$this->editable($listing);
            DB::table('post_ads')->where('id',$listing->id)->where('created_by',$request->user()->id)
                ->update(['image'=>'listing.png','updated_at'=>now()]);

            return $this->owned($request,$id);
        });

        return ApiResponse::success('Listing image removed successfully.', ['image_url'=>PublicData::image($listing->image,'frontend/img/listing')]);
    }
}
