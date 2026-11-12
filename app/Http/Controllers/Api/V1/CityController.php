<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Api\PublicData;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CityController
{
    public function __invoke(Request $request): JsonResponse
    {
        $input = PublicData::input($request, PublicData::paginationRules() + [
            'country_id' => 'sometimes|integer|min:1|max:2147483647', 'search' => 'sometimes|string|min:1|max:100',
        ]);
        $query = DB::table('cities as city')->join('countries as country', 'country.id', '=', 'city.country_id')
            ->select('city.id', 'city.name', 'city.short_name', 'city.country_id')
            ->where('city.isActive', 1)->where('country.isActive', 1);
        if (isset($input['country_id'])) {
            $query->where('city.country_id', $input['country_id']);
        }
        if (isset($input['search'])) {
            $query->whereRaw("city.name LIKE ? ESCAPE '!'", [PublicData::like($input['search'])]);
        }
        $page = $query->orderBy('city.name')->orderBy('city.id')
            ->paginate($input['per_page'] ?? 50, ['*'], 'page', $input['page'] ?? 1);

        return ApiResponse::success('Cities retrieved successfully.', PublicData::page($page, PublicData::city(...)));
    }
}
