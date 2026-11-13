<?php

namespace App\Queries\Api;

use App\Support\Api\PublicData;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

/** Complete reference lists only; listing pagination and home queries are unrelated. */
final class ReferenceDataQuery
{
    public static function input(Request $request, array $extraRules = []): array
    {
        $search = $request->query('search');
        if (is_string($search)) {
            $request->query->set('search', trim($search));
        }

        return PublicData::input($request, ['search' => 'sometimes|nullable|string|max:100'] + $extraRules);
    }

    public static function apply(Builder $query, string $column, ?string $search, ?string $tieKey = 'id'): Builder
    {
        // Identifiers are source-code constants, never request-controlled SQL.
        if (! in_array($column, ['name', 'city.name', 'code'], true)
            || ! in_array($tieKey, ['id', 'city.id', null], true)) {
            throw new \LogicException('Unsupported reference search column.');
        }
        $term = mb_strtolower(trim($search ?? ''));
        if ($term !== '') {
            $literal = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
            $query->whereRaw("LOWER($column) LIKE ? ESCAPE '!'", ['%'.$literal.'%'])
                ->orderByRaw("CASE WHEN LOWER($column) = ? THEN 0 WHEN LOWER($column) LIKE ? ESCAPE '!' THEN 1 ELSE 2 END",
                    [$term, $literal.'%']);
        }
        $query->orderBy($column);
        if ($tieKey !== null) {
            $query->orderBy($tieKey);
        }

        return $query;
    }
}
