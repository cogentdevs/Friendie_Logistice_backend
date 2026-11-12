<?php

namespace App\Http\Controllers\Api\V1;

use App\Queries\Api\PublicListingQuery;
use App\Support\Api\PublicData;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class HomeController
{
    public function __invoke(Request $request): JsonResponse
    {
        PublicData::input($request, []);
        $categories = DB::table('categories')->select('id', 'name', 'image')->where('isActive', 1)
            ->orderBy('name')->orderBy('id')->limit(12)->get()->map(PublicData::category(...));
        // Existing featured section requires a future departure; latest also includes today's unexpired ads.
        $featured = PublicListingQuery::make()->where('ads.isFeatured', 1)
            ->where('ads.departure_on', '>', now()->toDateString())->orderByDesc('ads.id')->limit(8)->get();
        $latest = PublicListingQuery::make()->orderByDesc('ads.id')->limit(8)->get();

        return ApiResponse::success('Home retrieved successfully.', [
            'categories' => $categories->values()->all(),
            'featured_listings' => $featured->map(fn ($row) => PublicData::listing($row))->values()->all(),
            'latest_listings' => $latest->map(fn ($row) => PublicData::listing($row))->values()->all(),
        ]);
    }
}
