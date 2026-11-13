<?php

namespace App\Http\Controllers\Api\V1;

use App\Queries\Api\ReferenceDataQuery;
use App\Support\Api\PublicData;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CountryController
{
    public function __invoke(Request $request): JsonResponse
    {
        $input = ReferenceDataQuery::input($request);
        $query = DB::table('countries')->select('id', 'name', 'short_name', 'image')->where('isActive', 1);
        $items = ReferenceDataQuery::apply($query, 'name', $input['search'] ?? null)->get()
            ->map(PublicData::country(...))->values()->all();

        return ApiResponse::success('Countries retrieved successfully.', ['items' => $items]);
    }
}
