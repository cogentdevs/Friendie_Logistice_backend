<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Api\PublicData;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CategoryController
{
    public function __invoke(Request $request): JsonResponse
    {
        $input = PublicData::input($request, PublicData::paginationRules());
        $page = DB::table('categories')->select('id', 'name', 'image')->where('isActive', 1)
            ->orderBy('name')->orderBy('id')->paginate($input['per_page'] ?? 50, ['*'], 'page', $input['page'] ?? 1);

        return ApiResponse::success('Categories retrieved successfully.', PublicData::page($page, PublicData::category(...)));
    }
}
