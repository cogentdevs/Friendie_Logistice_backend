<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReferenceDataApiTest extends TestCase
{
    use DatabaseTransactions;

    private ?int $country = null;

    public static function endpoints(): array
    {
        return [['countries'], ['cities'], ['categories'], ['currencies']];
    }

    private function country(): int
    {
        return $this->country ??= DB::table('countries')->insertGetId(['name' => 'Parent', 'short_name' => 'PA', 'isActive' => 1]);
    }

    private function add(string $endpoint, string $name, int $active = 1): int
    {
        return match ($endpoint) {
            'countries' => DB::table('countries')->insertGetId(['name' => $name, 'short_name' => 'EX', 'isActive' => $active]),
            'cities' => DB::table('cities')->insertGetId(['name' => $name, 'country_id' => $this->country(), 'isActive' => $active]),
            'categories' => DB::table('categories')->insertGetId(['name' => $name, 'isActive' => $active]),
            'currencies' => DB::table('countries')->insertGetId(['name' => 'Example Country', 'short_name' => 'EX',
                'currency_name' => $name, 'isActive' => $active]),
        };
    }

    private function labels(array $items, string $endpoint): array
    {
        return array_column($items, $endpoint === 'currencies' ? 'code' : 'name');
    }

    #[DataProvider('endpoints')]
    public function test_complete_lists_exceed_100_without_limits_pagination_authentication_or_writes(string $endpoint): void
    {
        for ($i = 0; $i < 105; $i++) {
            $this->add($endpoint, 'Entry '.str_pad((string) $i, 3, '0', STR_PAD_LEFT));
        }
        $this->add($endpoint, 'Hidden Entry', 0);
        foreach (['', '?search=entry'] as $query) {
            DB::enableQueryLog();DB::flushQueryLog();
            $response = $this->getJson('/api/v1/'.$endpoint.$query)->assertOk()->assertJsonPath('success', true)
                ->assertJsonCount(105, 'data.items')->assertJsonMissingPath('data.pagination')
                ->assertHeaderMissing('Set-Cookie')->assertHeaderMissing('Location');
            $this->assertSame(['items'], array_keys($response->json('data')));
            $queries = DB::getQueryLog();
            $this->assertCount(1, $queries);
            $this->assertMatchesRegularExpression('/^select\b/i', $queries[0]['query']);
            $this->assertDoesNotMatchRegularExpression('/\blimit\b|\boffset\b/i', $queries[0]['query']);
            $this->assertGuest('web');
        }
    }

    #[DataProvider('endpoints')]
    public function test_case_insensitive_trimmed_exact_prefix_middle_and_end_ranking_and_stable_ties(string $endpoint): void
    {
        foreach (['Shikarpur', 'Karkana', 'Bakar', 'Karachi', 'Kar', 'Bakar'] as $name) {
            $this->add($endpoint, $name);
        }
        $this->add($endpoint, 'Kar hidden', 0);
        $response = $this->getJson('/api/v1/'.$endpoint.'?search='.rawurlencode(' kAr '))->assertOk();
        $expected = ['Kar', 'Karachi', 'Karkana', 'Bakar', 'Bakar', 'Shikarpur'];
        if ($endpoint === 'currencies') {
            $expected = array_values(array_unique(array_map('strtoupper', $expected)));
        }
        $this->assertSame($expected, $this->labels($response->json('data.items'), $endpoint));
        if ($endpoint !== 'currencies') {
            $items = $response->json('data.items');
            $this->assertLessThan($items[4]['id'], $items[3]['id']);
        }
        $this->assertSame($response->json(), $this->getJson('/api/v1/'.$endpoint.'?search=KAR')->assertOk()->json());
    }

    #[DataProvider('endpoints')]
    public function test_no_search_and_blank_search_are_alphabetical_and_no_matches_return_empty_items(string $endpoint): void
    {
        $this->add($endpoint, 'Zulu');$this->add($endpoint, 'Alpha');$this->add($endpoint, 'Beta');
        $expected = $endpoint === 'currencies' ? ['ALPHA', 'BETA', 'ZULU'] : ['Alpha', 'Beta', 'Zulu'];
        foreach (['', '?search=', '?search=%20%20'] as $query) {
            $items = $this->getJson('/api/v1/'.$endpoint.$query)->assertOk()->json('data.items');
            $this->assertSame($expected, $this->labels($items, $endpoint));
        }
        $this->getJson('/api/v1/'.$endpoint.'?search=no-match')->assertOk()->assertJsonPath('data.items', [])
            ->assertJsonMissingPath('data.pagination');
    }

    #[DataProvider('endpoints')]
    public function test_wildcards_and_escape_character_are_literal_and_injection_cannot_bypass_visibility(string $endpoint): void
    {
        $names = ['Percent%value', 'Under_score', 'Bang!value', 'Back\\slash', 'Visible'];
        foreach ($names as $name) $this->add($endpoint, $name);
        $this->add($endpoint, 'Hidden%_!', 0);
        foreach (['%' => $names[0], '_' => $names[1], '!' => $names[2], '\\' => $names[3]] as $term => $name) {
            $items = $this->getJson('/api/v1/'.$endpoint.'?search='.rawurlencode($term))->assertOk()->json('data.items');
            $this->assertSame([$endpoint === 'currencies' ? strtoupper($name) : $name], $this->labels($items, $endpoint));
        }
        $this->getJson('/api/v1/'.$endpoint.'?search='.rawurlencode("%' OR 1=1 --"))->assertOk()->assertJsonPath('data.items', []);
        $this->add($endpoint, "x' OR 1=1 --");
        $this->getJson('/api/v1/'.$endpoint.'?search='.rawurlencode("x' OR 1=1 --"))->assertOk()->assertJsonCount(1, 'data.items');
    }

    #[DataProvider('endpoints')]
    public function test_pagination_all_unknown_and_malformed_search_parameters_are_rejected(string $endpoint): void
    {
        foreach (['page=1', 'per_page=1', 'all=1', 'unknown=1', 'search[]=Kar', 'search='.str_repeat('a', 101)] as $query) {
            $this->getJson('/api/v1/'.$endpoint.'?'.$query)->assertUnprocessable()->assertJsonPath('success', false)
                ->assertJsonPath('message', 'Validation failed');
        }
        if ($endpoint !== 'cities') {
            $this->getJson('/api/v1/'.$endpoint.'?country_id=1')->assertUnprocessable();
        }
    }

    public function test_city_country_filter_combines_with_search_and_existing_parent_visibility(): void
    {
        $first = $this->add('cities', 'Karachi');$country = $this->country();
        $other = DB::table('countries')->insertGetId(['name' => 'Other', 'short_name' => 'OT', 'isActive' => 1]);
        $hidden = DB::table('countries')->insertGetId(['name' => 'Hidden', 'short_name' => 'HI', 'isActive' => 0]);
        DB::table('cities')->insert([
            ['name' => 'Kar', 'country_id' => $other, 'isActive' => 1],
            ['name' => 'Kar', 'country_id' => $hidden, 'isActive' => 1],
            ['name' => 'Kar', 'country_id' => 2147483647, 'isActive' => 1],
            ['name' => 'Kar', 'country_id' => $country, 'isActive' => 0],
        ]);
        $this->getJson('/api/v1/cities')->assertOk()->assertJsonCount(2, 'data.items');
        $this->getJson('/api/v1/cities?country_id='.$country)->assertOk()->assertJsonCount(1, 'data.items');
        $this->getJson('/api/v1/cities?country_id='.$country.'&search=KAR')->assertOk()->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $first);
        $this->getJson('/api/v1/cities?country_id='.$hidden)->assertOk()->assertJsonPath('data.items', []);
        $this->getJson('/api/v1/cities?country_id=2147483647')->assertOk()->assertJsonPath('data.items', []);
        foreach (['0', '-1', '1 OR 1=1', '1.5'] as $invalid) {
            $this->getJson('/api/v1/cities?country_id='.rawurlencode($invalid))->assertUnprocessable();
        }
    }

    public function test_currency_normalization_deduplication_and_blank_values_are_preserved(): void
    {
        foreach ([' usd ', 'USD', 'usd', ' ', ''] as $name) $this->add('currencies', $name);
        DB::table('countries')->insert(['name' => 'No currency', 'short_name' => 'NC', 'currency_name' => null, 'isActive' => 1]);
        $this->add('currencies', 'HIDDEN', 0);
        $this->getJson('/api/v1/currencies?search= UsD ')->assertOk()->assertJsonPath('data.items', [['code' => 'USD']]);
        $this->getJson('/api/v1/currencies')->assertOk()->assertJsonPath('data.items', [['code' => 'USD']]);
    }
}
