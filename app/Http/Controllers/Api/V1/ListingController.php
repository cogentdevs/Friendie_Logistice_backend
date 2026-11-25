<?php

namespace App\Http\Controllers\Api\V1;

use App\Queries\Api\PublicListingQuery;
use App\Support\Api\PublicData;
use App\Support\ApiResponse;
use App\Services\Api\ListingDeals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ListingController
{
    public function index(Request $request): JsonResponse
    {
        $rules = PublicData::paginationRules(50) + [
            'search' => 'sometimes|string|min:1|max:100',
            'sort' => ['sometimes', Rule::in(['latest', 'oldest', 'departure'])],
            'ad_type' => ['sometimes', Rule::in(['Carring', 'Send'])],
            'weight' => 'sometimes|integer|min:0|max:2147483647',
            'departure_on' => 'sometimes|date_format:Y-m-d',
        ];
        foreach (['category_id', 'from_country_id', 'to_country_id', 'from_city_id', 'to_city_id'] as $field) {
            $rules[$field] = 'sometimes|integer|min:1|max:2147483647';
        }
        $input = PublicData::input($request, $rules);
        $page = PublicListingQuery::filter(PublicListingQuery::make(), $input)
            ->paginate($input['per_page'] ?? 20, ['*'], 'page', $input['page'] ?? 1);
        $availability=ListingDeals::listingData($page->getCollection(),$request);

        return ApiResponse::success('Listings retrieved successfully.', PublicData::page($page,
            fn ($row) => PublicData::listing($row)+$availability[$row->id]))->header('Cache-Control','no-store, private');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        PublicData::input($request, []);
        $listing = PublicListingQuery::make()->where('ads.id', $id)->first();
        abort_if($listing === null, 404);

        $availability=ListingDeals::listingData([$listing],$request);
        return ApiResponse::success('Listing retrieved successfully.', PublicData::listing($listing)+$availability[$listing->id]
            +['description'=>PublicData::text($listing->description)])->header('Cache-Control','no-store, private');
    }
}
