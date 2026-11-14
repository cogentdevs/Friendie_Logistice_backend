<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Queries\Api\PublicListingQuery;
use App\Services\EmailActivationService;
use App\Support\Api\PublicData;
use App\Support\ApiResponse;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Existing listing-owner feedback, isolated from web controllers and Blade. */
final class CustomerReviewController
{
    private function query(): Builder
    {
        // Match the website's listing/reviewer joins; a missing target can remain historical data.
        return DB::table('reviews as review')
            ->join('post_ads as listing','listing.id','=','review.post_ads_id')
            ->join('users as reviewer','reviewer.id','=','review.from_user_id')
            ->leftJoin('users as recipient','recipient.id','=','review.to_user_id')
            ->select('review.id','review.post_ads_id','review.from_user_id','review.to_user_id',
                'review.rating','review.review','review.date_time','review.created_at','review.updated_at',
                'listing.title','reviewer.name as reviewer_name','recipient.name as recipient_name');
    }

    private function data(object $row): array
    {
        return ['id'=>(int)$row->id,
            'listing'=>['id'=>(int)$row->post_ads_id,'title'=>PublicData::text($row->title,255)],
            'reviewer'=>['id'=>(int)$row->from_user_id,'display_name'=>PublicData::text($row->reviewer_name,255)],
            'reviewed_customer'=>['id'=>(int)$row->to_user_id,'display_name'=>PublicData::text($row->recipient_name,255)],
            'rating'=>(int)$row->rating,'review'=>$row->review,'date_time'=>$row->date_time,
            'created_at'=>$row->created_at,'updated_at'=>$row->updated_at];
    }

    private function collection(Request $request, string $field, string $message)
    {
        $input = PublicData::input($request,PublicData::paginationRules(50));
        $page = $this->query()->where('review.'.$field,$request->user()->id)->orderByDesc('review.id')
            ->paginate($input['per_page'] ?? 20,['*'],'page',$input['page'] ?? 1);
        return ApiResponse::success($message,PublicData::page($page,$this->data(...)))
            ->header('Cache-Control','no-store, private');
    }

    public function received(Request $request)
    {
        return $this->collection($request,'to_user_id','Received reviews retrieved successfully.');
    }

    public function given(Request $request)
    {
        return $this->collection($request,'from_user_id','Given reviews retrieved successfully.');
    }

    public function store(Request $request)
    {
        foreach (['account:'.$request->user()->id,'ip:'.hash('sha256',(string)$request->ip())] as $scope) {
            $key='customer-review:'.$scope;
            if (RateLimiter::tooManyAttempts($key,10)) throw new HttpException(429,'',null,
                ['Retry-After'=>(string)max(1,RateLimiter::availableIn($key))]);
            RateLimiter::hit($key,900);
        }
        $unknown=array_diff(array_keys($request->all()),['post_ads_id','rating','review']);
        if ($unknown) throw ValidationException::withMessages(array_fill_keys($unknown,['This field is not allowed.']));
        $input=$request->validate([
            'post_ads_id'=>'required|integer|min:1|max:2147483647',
            // The web form defaults to 0 without requiring a star click, and offers stars 1..5.
            'rating'=>'required|integer|between:0,5',
            // Web textarea is required but has no maxlength; retain LONGTEXT rather than invent a business cap.
            'review'=>['required','string',function ($key,$value,$fail) {
                if (is_string($value) && (trim($value)==='' || str_contains($value,"\0"))) $fail('Enter a valid nonempty review.');
            }],
        ]);
        $row=DB::transaction(function () use ($request,$input) {
            $reviewer=User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless((int)$reviewer->isActive===1 && (int)$reviewer->isVerified===1
                && app(EmailActivationService::class)->customer($reviewer),403);
            abort_unless($reviewer->tokens()->whereKey($request->user()->currentAccessToken()->getKey())->exists(),401);
            $listing=DB::table('post_ads')->where('id',$input['post_ads_id'])->lockForUpdate()->first();
            abort_unless($listing && PublicListingQuery::make()->where('ads.id',$listing->id)
                ->select('ads.id')->lockForUpdate()->first(),404);
            if ((int)$listing->created_by===(int)$reviewer->id) throw ValidationException::withMessages(['post_ads_id'=>['You cannot review your own listing.']]);
            // The form's to_user_id is always the listing owner. Never accept arbitrary client targets.
            $recipient=User::find($listing->created_by);
            abort_unless($recipient,404);
            if (DB::table('reviews')->where('post_ads_id',$listing->id)->where('from_user_id',$reviewer->id)->exists()) {
                return null;
            }
            $id=DB::table('reviews')->insertGetId(['post_ads_id'=>$listing->id,'from_user_id'=>$reviewer->id,
                'to_user_id'=>$recipient->id,'rating'=>$input['rating'],'review'=>$input['review'],
                'date_time'=>now()->format('Y-m-d'),'created_at'=>now(),'updated_at'=>now()]);
            return $this->query()->where('review.id',$id)->first();
        },3);
        if ($row===null) return ApiResponse::error('You have already reviewed this listing.',409);
        return ApiResponse::success('Review submitted successfully.',$this->data($row),201)
            ->header('Cache-Control','no-store, private');
    }
}
