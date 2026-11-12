<?php

namespace App\Queries\Api;

use App\Support\Api\PublicData;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PublicListingQuery
{
    public static function make(): Builder
    {
        // 0=pending, 1=published, 2=expired. Never invoke the mutating Blade composer.
        return DB::table('post_ads as ads')
            ->join('users as owner', 'owner.id', '=', 'ads.created_by')
            ->join('categories as category', 'category.id', '=', 'ads.category_id')
            ->join('countries as origin_country', 'origin_country.id', '=', 'ads.from_country_id')
            ->join('countries as destination_country', 'destination_country.id', '=', 'ads.to_country_id')
            ->join('cities as origin_city', 'origin_city.id', '=', 'ads.from_city_id')
            ->join('cities as destination_city', 'destination_city.id', '=', 'ads.to_city_id')
            ->where('ads.isActive', 1)->where('ads.departure_on', '>=', now()->toDateString())
            ->where('category.isActive', 1)
            ->select([
                'ads.id', 'ads.ad_type', 'ads.title', 'ads.description', 'ads.image',
                'ads.category_id', 'ads.from_country_id', 'ads.from_city_id', 'ads.to_country_id', 'ads.to_city_id',
                'ads.weight', 'ads.weight_price', 'ads.currency', 'ads.departure_on', 'ads.reached_on',
                'ads.isFeatured', 'ads.created_at', 'category.name as category_name',
                'origin_country.name as from_country_name', 'destination_country.name as to_country_name',
                'origin_city.name as from_city_name', 'destination_city.name as to_city_name',
                'owner.id as owner_id', 'owner.name as owner_name', 'owner.image as owner_image',
            ]);
    }

    public static function filter(Builder $query, array $input): Builder
    {
        foreach (['category_id', 'from_country_id', 'to_country_id', 'from_city_id', 'to_city_id', 'ad_type', 'weight', 'departure_on'] as $field) {
            if (isset($input[$field])) {
                $query->where('ads.'.$field, $input[$field]);
            }
        }
        if (isset($input['search'])) {
            $pattern = PublicData::like($input['search']);
            // Group ORs so keywords never escape publication, expiry or other filters.
            // Search only public card labels; do not search private area/contact fields.
            $query->where(fn (Builder $q) => $q
                ->whereRaw("ads.title LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("category.name LIKE ? ESCAPE '!'", [$pattern]));
        }

        return match ($input['sort'] ?? 'latest') {
            'oldest' => $query->orderBy('ads.id'),
            'departure' => $query->orderBy('ads.departure_on')->orderByDesc('ads.id'),
            default => $query->orderByDesc('ads.id'),
        };
    }
}
