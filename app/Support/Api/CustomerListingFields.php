<?php

namespace App\Support\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CustomerListingFields
{
    public const EDITABLE = ['ad_type', 'title', 'description', 'category_id', 'from_country_id', 'from_city_id',
        'from_area', 'to_country_id', 'to_city_id', 'to_area', 'weight', 'weight_price', 'currency',
        'departure_on', 'reached_on', 'show_contact_info'];

    public static function validate(array $input, ?object $current = null): array
    {
        $unknown = array_diff(array_keys($input), self::EDITABLE);
        if ($unknown !== []) {
            throw ValidationException::withMessages(array_fill_keys($unknown, ['This field is not allowed.']));
        }
        if ($current !== null && $input === []) {
            throw ValidationException::withMessages(['listing' => 'Provide at least one editable field.']);
        }
        if (isset($input['currency']) && is_string($input['currency'])) {
            $input['currency'] = mb_strtoupper(trim($input['currency']));
        }
        $required = $current === null ? 'required' : 'sometimes|required';
        $presence = $current === null ? ['required'] : ['sometimes', 'required'];
        $rules = [
            'ad_type' => [...$presence, Rule::in(['Carring', 'Send'])],
            'title' => $required.'|string|max:255', 'description' => 'sometimes|nullable|string|max:10000',
            'category_id' => [...$presence, 'integer', 'min:1', 'max:2147483647', Rule::exists('categories', 'id')->where('isActive', 1)],
            'from_area' => 'sometimes|nullable|string|max:255', 'to_area' => 'sometimes|nullable|string|max:255',
            'weight' => 'sometimes|nullable|integer|min:0|max:2147483647',
            'weight_price' => ['sometimes', 'nullable', 'numeric', 'min:0', function ($attribute, $value, $fail) {
                if (is_numeric($value) && ! is_finite((float) $value)) $fail('The price must be finite.');
            }],
            'currency' => [...$presence, 'string', 'max:11', function ($attribute, $value, $fail) {
                if (is_string($value) && ! DB::table('countries')->where('isActive', 1)
                    ->whereRaw('UPPER(TRIM(currency_name)) = ?', [$value])->exists()) {
                    $fail('Select a supported currency.');
                }
            }],
            'departure_on' => $required.'|date_format:Y-m-d', 'reached_on' => $required.'|date_format:Y-m-d',
            // A legacy visibility flag cannot authorize phone disclosure. New API listings default to 0.
            'show_contact_info' => 'sometimes|integer|in:0',
        ];
        foreach (['from', 'to'] as $side) {
            $rules[$side.'_country_id'] = [...$presence, 'integer', 'min:1', 'max:2147483647', Rule::exists('countries', 'id')->where('isActive', 1)];
            $rules[$side.'_city_id'] = [...$presence, 'integer', 'min:1', 'max:2147483647', Rule::exists('cities', 'id')->where('isActive', 1)];
        }
        $validator = Validator::make($input, $rules);
        $validator->after(function ($validator) use ($input, $current) {
            foreach (['from', 'to'] as $side) {
                $countryKey = $side.'_country_id';$cityKey = $side.'_city_id';
                if ($current !== null && ! array_key_exists($countryKey, $input) && ! array_key_exists($cityKey, $input)) continue;
                if ($validator->errors()->has($countryKey) || $validator->errors()->has($cityKey)) continue;
                $country = $input[$countryKey] ?? $current?->{$countryKey};
                $city = $input[$cityKey] ?? $current?->{$cityKey};
                if (! $country || ! DB::table('countries')->where('id', $country)->where('isActive', 1)->exists()) {
                    $validator->errors()->add($countryKey, 'Select an active country.');
                } elseif (! $city || ! DB::table('cities')->where('id', $city)->where('country_id', $country)->where('isActive', 1)->exists()) {
                    $validator->errors()->add($cityKey, 'Select an active city belonging to the selected country.');
                }
            }
            if ($current === null || array_key_exists('departure_on', $input) || array_key_exists('reached_on', $input)) {
                $dates = Validator::make([
                    'departure_on' => $input['departure_on'] ?? $current?->departure_on,
                    'reached_on' => $input['reached_on'] ?? $current?->reached_on,
                ], ['departure_on' => 'required|date_format:Y-m-d', 'reached_on' => 'required|date_format:Y-m-d|after_or_equal:departure_on']);
                foreach ($dates->errors()->messages() as $key => $messages) {
                    foreach ($messages as $message) $validator->errors()->add($key, $message);
                }
            }
        });

        return $validator->validate();
    }
}
