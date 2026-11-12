<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Api\PublicData;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CurrencyController
{
    public function __invoke(Request $request): JsonResponse
    {
        $input = PublicData::input($request, PublicData::paginationRules());
        $currencies = DB::table('countries')->where('isActive', 1)->whereNotNull('currency_name')
            ->whereRaw("TRIM(currency_name) <> ''")
            ->selectRaw('UPPER(TRIM(currency_name)) as code')->distinct();
        $page = DB::query()->fromSub($currencies, 'currencies')->orderBy('code')
            ->paginate($input['per_page'] ?? 50, ['*'], 'page', $input['page'] ?? 1);

        return ApiResponse::success('Currencies retrieved successfully.', PublicData::page($page, fn ($row) => ['code' => PublicData::text($row->code, 255)]));
    }
}
