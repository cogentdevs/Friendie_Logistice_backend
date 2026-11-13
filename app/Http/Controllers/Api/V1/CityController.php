<?php

namespace App\Http\Controllers\Api\V1;

use App\Queries\Api\ReferenceDataQuery;
use App\Support\Api\PublicData;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CityController
{
    public function __invoke(Request $request): JsonResponse
    {
        $input = ReferenceDataQuery::input($request, [
            'country_id' => 'sometimes|integer|min:1|max:2147483647',
        ]);
        $query = DB::table('cities as city')->join('countries as country', 'country.id', '=', 'city.country_id')
            ->select('city.id', 'city.name', 'city.short_name', 'city.country_id')
            ->where('city.isActive', 1)->where('country.isActive', 1);
        if (isset($input['country_id'])) {
            $query->where('city.country_id', $input['country_id']);
        }
        $items = ReferenceDataQuery::apply($query, 'city.name', $input['search'] ?? null, 'city.id')->get()
            ->map(PublicData::city(...))->values()->all();

        return ApiResponse::success('Cities retrieved successfully.', ['items' => $items]);
    }
}
