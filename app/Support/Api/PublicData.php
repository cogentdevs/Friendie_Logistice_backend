<?php

namespace App\Support\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Public API serialization only; never serialize a database row wholesale. */
final class PublicData
{
    public static function input(Request $request, array $rules): array
    {
        $unknown = array_diff(array_keys($request->query()), array_keys($rules));
        if ($unknown !== []) {
            throw ValidationException::withMessages(array_fill_keys($unknown, ['Unsupported query parameter.']));
        }

        return validator($request->query(), $rules)->validate();
    }

    public static function paginationRules(int $maximum = 100): array
    {
        return ['page' => 'sometimes|integer|min:1|max:100000', 'per_page' => "sometimes|integer|min:1|max:$maximum"];
    }

    public static function page(LengthAwarePaginator $page, callable $serialize): array
    {
        return [
            'items' => $page->getCollection()->map($serialize)->values()->all(),
            'pagination' => [
                'current_page' => $page->currentPage(), 'per_page' => $page->perPage(),
                'total' => $page->total(), 'last_page' => $page->lastPage(),
            ],
        ];
    }

    public static function like(string $value): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($value)).'%';
    }

    public static function image(?string $filename, string $directory): ?string
    {
        if (! $filename || basename(str_replace('\\', '/', $filename)) !== $filename
            || ! preg_match('/\A[a-zA-Z0-9_. -]+\.(?:jpe?g|png|gif|webp)\z/i', $filename)
            || ! is_file(public_path($directory.'/'.$filename))) {
            return null;
        }

        $publicRoot = str_replace('\\', '/', realpath(public_path()));
        $file = str_replace('\\', '/', realpath(public_path($directory.'/'.$filename)));
        if (PHP_OS_FAMILY === 'Windows') {
            $publicRoot = strtolower($publicRoot);
            $file = strtolower($file);
        }
        if (! str_starts_with($file, rtrim($publicRoot, '/').'/')) {
            return null;
        }

        $base = self::mediaBaseUrl();

        return $base === null ? null : $base.'/'.$directory.'/'.rawurlencode($filename);
    }

    private static function mediaBaseUrl(): ?string
    {
        $configured = config('api.public_base_url');
        $base = is_string($configured) ? trim($configured) : '';
        if ($base === '') {
            $base = trim((string) config('app.url'));
        }
        // Invalid explicit configuration fails closed; never use arbitrary request authority.
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $base)) {
            return null;
        }
        $parts = parse_url($base);
        if ($parts === false || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || filter_var($base, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $path = rtrim(preg_replace('~/+~', '/', $parts['path'] ?? ''), '/');
        foreach (explode('/', rawurldecode($path)) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return null;
            }
        }

        return strtolower($parts['scheme']).'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '').$path;
    }

    public static function text(?string $value, int $limit = 10000): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $value);
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Legacy free text has no moderation classification. Withhold contact/address-bearing text.
        if (preg_match('~[\w.+-]+\s*@\s*[\w.-]+|(?:\+?\d[\d\s().-]{5,}\d)|https?://|www\.|\b(?:phone|mobile|email|e-mail|whatsapp|telegram|address|street|apartment|postcode|zip code)\b~iu', $value)) {
            return null;
        }

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $value)), 0, $limit);
    }

    public static function category(object $row): array
    {
        return ['id' => (int) $row->id, 'name' => self::text($row->name, 255),
            'image_url' => self::image($row->image, 'backend/img/category')];
    }

    public static function country(object $row): array
    {
        return ['id' => (int) $row->id, 'name' => self::text($row->name ?: $row->short_name, 255),
            'short_name' => self::text($row->short_name, 255),
            'image_url' => self::image($row->image, 'backend/img/flags')];
    }

    public static function city(object $row): array
    {
        return ['id' => (int) $row->id, 'country_id' => (int) $row->country_id,
            'name' => self::text($row->name, 255), 'short_name' => self::text($row->short_name, 255)];
    }

    public static function listing(object $row, bool $detail = false): array
    {
        $description = self::text($row->description);
        $result = [
            'id' => (int) $row->id, 'ad_type' => self::text($row->ad_type, 255),
            'title' => self::text($row->title, 255),
            'short_description' => $description === null ? null : mb_substr($description, 0, 200),
            'image_url' => self::image($row->image, 'frontend/img/listing'),
            'category' => ['id' => (int) $row->category_id, 'name' => self::text($row->category_name, 255)],
            'origin' => ['country_id' => (int) $row->from_country_id,
                'country' => self::text($row->from_country_name, 255),
                'city_id' => (int) $row->from_city_id, 'city' => self::text($row->from_city_name, 255)],
            'destination' => ['country_id' => (int) $row->to_country_id,
                'country' => self::text($row->to_country_name, 255),
                'city_id' => (int) $row->to_city_id, 'city' => self::text($row->to_city_name, 255)],
            'weight' => $row->weight === null ? null : (int) $row->weight,
            'weight_price' => $row->weight_price === null ? null : (float) $row->weight_price,
            'currency' => self::text($row->currency, 11),
            'departure_on' => $row->departure_on, 'reached_on' => $row->reached_on,
            'is_featured' => (bool) $row->isFeatured, 'created_at' => $row->created_at,
            'owner' => ['id' => (int) $row->owner_id, 'display_name' => self::text($row->owner_name, 255),
                'image_url' => self::image($row->owner_image, 'frontend/img/profiles')],
        ];
        if ($detail) {
            $result['description'] = $description;
        }

        return $result;
    }
}
