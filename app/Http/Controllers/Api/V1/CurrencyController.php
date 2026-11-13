<?php

namespace App\Http\Controllers\Api\V1;

use App\Queries\Api\ReferenceDataQuery;
use App\Support\Api\PublicData;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CurrencyController
{
    public function __invoke(Request $request): JsonResponse
    {
        $input = ReferenceDataQuery::input($request);
        $currencies = DB::table('countries')->where('isActive', 1)->whereNotNull('currency_name')
            ->whereRaw("TRIM(currency_name) <> ''")
            ->selectRaw('UPPER(TRIM(currency_name)) as code')->distinct();
        $query = DB::query()->fromSub($currencies, 'currencies');
        $items = ReferenceDataQuery::apply($query, 'code', $input['search'] ?? null, null)->get()
            ->map(fn ($row) => ['code' => PublicData::text($row->code, 255)])->values()->all();

        return ApiResponse::success('Currencies retrieved successfully.', ['items' => $items]);
    }
}
