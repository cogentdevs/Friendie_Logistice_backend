<?php

namespace App\Support\Api;

use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Private listing pins. No model hooks, geocoding or external services. */
final class ListingCoordinates
{
    public const FIELDS = ['from_latitude', 'from_longitude', 'to_latitude', 'to_longitude'];

    public static function available(): bool
    {
        // Do not cache across deployment/schema transitions or test connections.
        return Schema::hasColumns('post_ads', self::FIELDS);
    }

    public static function validate(array $input): array
    {
        $provided = array_intersect_key($input, array_flip(self::FIELDS));
        if ($provided === []) return [];
        if (! self::available()) {
            throw ValidationException::withMessages(array_fill_keys(array_keys($provided),
                ['Location pins are not available yet. Please try again after the server update.']));
        }
        $errors = [];
        foreach (['from', 'to'] as $side) {
            $lat = $side.'_latitude'; $lng = $side.'_longitude';
            $hasLat = array_key_exists($lat, $input); $hasLng = array_key_exists($lng, $input);
            if (! $hasLat && ! $hasLng) continue;
            if (! $hasLat || ! $hasLng || (($input[$lat] === null) !== ($input[$lng] === null))) {
                $errors[$lat] = ['Supply both latitude and longitude, or set both to null.'];
                continue;
            }
            foreach ([$lat => 90, $lng => 180] as $key => $max) {
                $value = $input[$key];
                if ($value === null) continue;
                // JSON numbers and plain decimal form strings; no exponent/whitespace syntax.
                $validType = is_int($value) || is_float($value) || is_string($value);
                $decimal = $validType ? (string)$value : '';
                if (is_float($value) && is_finite($value)) {
                    $scaled = $value * 10000000;
                    $tolerance = max(1, abs($scaled)) * PHP_FLOAT_EPSILON * 4;
                    $decimal = abs($scaled - round($scaled)) <= $tolerance
                        ? number_format($value, 7, '.', '') : '';
                }
                if (! $validType || ! preg_match('/\A-?[0-9]+(?:\.[0-9]{1,7})?\z/D', $decimal)
                    || ! is_finite((float)$value) || (float)$value < -$max || (float)$value > $max) {
                    $errors[$key] = ["Use a finite decimal between -{$max} and {$max}, with at most 7 decimal places."];
                } else {
                    $provided[$key] = number_format((float)$value, 7, '.', '');
                }
            }
        }
        if ($errors !== []) throw ValidationException::withMessages($errors);

        return $provided;
    }

    /** Validate/normalize only IDs actually submitted by the legacy edit form. */
    public static function locationIds(array $input): array
    {
        $ids = [];
        foreach (['from_country_id', 'from_city_id', 'to_country_id', 'to_city_id'] as $key) {
            if (! array_key_exists($key, $input)) continue;
            $value = $input[$key];
            if ((! is_int($value) && ! is_string($value)) || ! preg_match('/\A[0-9]+\z/D', (string)$value)
                || (float)$value < 1 || (float)$value > 2147483647) {
                throw ValidationException::withMessages([$key => ['Use a positive integer location ID.']]);
            }
            $ids[$key] = (int)$value;
        }

        return $ids;
    }

    /** Called only with validated IDs while the listing row is locked. */
    public static function clearChangedLocations(array $input, object $current, array $replacement = []): array
    {
        $clears = [];
        foreach (['from', 'to'] as $side) {
            if (array_key_exists($side.'_latitude', $replacement)) continue;
            foreach ([$side.'_country_id', $side.'_city_id'] as $key) {
                if (array_key_exists($key, $input) && (int)$input[$key] !== (int)$current->{$key}) {
                    $clears[$side.'_latitude'] = null;
                    $clears[$side.'_longitude'] = null;
                }
            }
        }

        return $clears;
    }
}
