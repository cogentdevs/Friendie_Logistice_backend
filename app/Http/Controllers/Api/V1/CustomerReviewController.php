<?php

namespace App\Http\Controllers\Api\V1;

use App\Queries\Api\PublicListingQuery;
use App\Services\Api\DealReviews;
use App\Support\Api\PublicData;
use App\Support\ApiResponse;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Shared review storage, completed-deal authorization and explicit public/private views. */
final class CustomerReviewController
{
    private function query(): Builder
    {
        return DealReviews::query();
    }

    private function data(object $row): array
    {
        return DealReviews::data($row);
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
        $unknown=array_diff(array_keys($request->all()),['deal_id','rating','review']);
        if ($unknown) throw ValidationException::withMessages(array_fill_keys($unknown,['This field is not allowed.']));
        $input=$request->validate(['deal_id'=>'required|integer|min:1|max:'.PHP_INT_MAX]+DealReviews::rules());
        $id=DealReviews::submit((int)$request->user()->id,$input,(int)$request->user()->currentAccessToken()->getKey());
        $row=$this->query()->where('review.id',$id)->first();
        return ApiResponse::success('Review submitted successfully.',$this->data($row),201)
            ->header('Cache-Control','no-store, private');
    }

    public function listingReviews(Request $request, string $id)
    {
        $input=PublicData::input($request,PublicData::paginationRules(50));
        $listing=PublicListingQuery::make()->where('ads.id',$id)->select('ads.id','ads.created_by')->first();
        abort_unless($listing,404);
        $query=DealReviews::verified($this->query())->where('review.post_ads_id',$id)->where('review.to_user_id',$listing->created_by);
        $totals=(clone $query)->reorder()->select([])->selectRaw('COUNT(*) AS review_count, AVG(review.rating) AS average_rating')->first();
        $page=$query->orderByDesc('review.id')->paginate($input['per_page']??20,['*'],'page',$input['page']??1);
        $data=PublicData::page($page,function ($row) {
            $review=$this->data($row);
            return ['id'=>$review['id'],'reviewer'=>$review['reviewer'],'rating'=>$review['rating'],
                'review'=>PublicData::text($row->review),'date_time'=>$review['date_time'],
                'created_at'=>$review['created_at'],'updated_at'=>$review['updated_at']];
        });
        return ApiResponse::success('Listing reviews retrieved successfully.',[
            'listing_id'=>(int)$id,'average_rating'=>$totals->average_rating===null?null:round((float)$totals->average_rating,1),
            'review_count'=>(int)$totals->review_count]+$data);
    }
}
