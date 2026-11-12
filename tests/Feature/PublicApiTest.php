<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use DatabaseTransactions;

    private int $owner;
    private int $category;
    private int $country;
    private int $otherCountry;
    private int $city;
    private int $otherCity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-12 12:00:00', 'UTC'));
        $this->owner = User::factory()->create([
            'name' => 'Public Owner', 'email' => 'private-owner@example.test',
            'phone' => '+923001234567', 'remember_token' => 'private-token-canary',
        ])->id;
        $this->category = DB::table('categories')->insertGetId(['name' => 'Parcels', 'isActive' => 1]);
        $this->country = DB::table('countries')->insertGetId([
            'name' => 'Alpha', 'short_name' => 'AA', 'currency_name' => 'USD', 'isActive' => 1,
        ]);
        $this->otherCountry = DB::table('countries')->insertGetId([
            'name' => 'Beta', 'short_name' => 'BB', 'currency_name' => ' usd ', 'isActive' => 1,
        ]);
        $this->city = DB::table('cities')->insertGetId(['name' => 'Alpha City', 'country_id' => $this->country, 'isActive' => 1]);
        $this->otherCity = DB::table('cities')->insertGetId(['name' => 'Beta City', 'country_id' => $this->otherCountry, 'isActive' => 1]);
    }

    private function listing(array $overrides = []): int
    {
        return DB::table('post_ads')->insertGetId(array_replace([
            'category_id' => $this->category, 'ad_type' => 'Carring', 'title' => 'Carry a parcel',
            'description' => '<p>Room for a small parcel.</p>', 'weight' => 5, 'weight_price' => 20,
            'currency' => 'USD', 'from_country_id' => $this->country, 'from_city_id' => $this->city,
            'to_country_id' => $this->otherCountry, 'to_city_id' => $this->otherCity,
            'from_area' => 'private-origin-address-canary', 'to_area' => 'private-destination-address-canary',
            'departure_on' => '2026-11-13', 'reached_on' => '2026-11-14', 'show_contact_info' => 1,
            'isFeatured' => 1, 'isActive' => 1, 'created_by' => $this->owner,
            'created_at' => '2026-11-01 10:00:00', 'updated_at' => '2026-11-01 10:00:00',
        ], $overrides));
    }

    public static function publicEndpoints(): array
    {
        return array_map(fn ($endpoint) => [$endpoint], ['home', 'categories', 'countries', 'cities', 'currencies', 'listings', 'detail']);
    }

    #[DataProvider('publicEndpoints')]
    public function test_every_endpoint_is_public_json_without_cookies_or_credentials(string $endpoint): void
    {
        $id = $this->listing();
        $path = $endpoint === 'detail' ? 'listings/'.$id : $endpoint;
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        $response = $this->get('/api/v1/'.$path, ['Accept' => 'text/html'])
            ->assertOk()->assertHeader('Content-Type', 'application/json')
            ->assertHeaderMissing('Set-Cookie')->assertHeaderMissing('Location')->assertJsonPath('success', true);
        foreach (['private-owner@example.test', '+923001234567', 'private-token-canary',
            'private-origin-address-canary', 'private-destination-address-canary',
            'password', 'remember_token', 'show_contact_info', 'email_verified_at'] as $private) {
            $response->assertDontSee($private, false);
        }
        foreach (DB::connection()->getQueryLog() as $query) {
            $this->assertMatchesRegularExpression('/^select\b/i', $query['query']);
        }
        $this->assertGuest();
    }

    public static function invalidQueries(): array
    {
        return [
            ['cities?country_id=oops'], ['cities?country_id=0'], ['cities?search[]=x'],
            ['cities?per_page=101'], ['countries?page=0'], ['categories?per_page=-1'],
            ['currencies?page=one'], ['listings?per_page=51'], ['listings?page=100001'],
            ['listings?category_id=1%20OR%201=1'], ['listings?from_city_id=-1'],
            ['listings?to_country_id=2.5'], ['listings?sort=weight_price'],
            ['listings?ad_type=unknown'], ['listings?departure_on=tomorrow'],
            ['listings?weight=-1'], ['listings?country_id=1'], ['home?debug=true'],
        ];
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_and_unsupported_parameters_return_json_422(string $path): void
    {
        $this->get('/api/v1/'.$path)->assertUnprocessable()
            ->assertJsonPath('success', false)->assertJsonPath('message', 'Validation failed')
            ->assertJsonStructure(['errors']);
    }

    public function test_empty_results_and_missing_private_or_malformed_details_are_non_disclosing(): void
    {
        $this->get('/api/v1/listings')->assertOk()->assertJsonPath('data.items', [])
            ->assertJsonPath('data.pagination.total', 0)->assertJsonPath('data.pagination.last_page', 1);
        $hidden = $this->listing(['isActive' => 0]);
        $expired = $this->listing(['departure_on' => '2026-11-11']);
        foreach ([$hidden, $expired, 999999999, 'invalid'] as $id) {
            $this->get('/api/v1/listings/'.$id)->assertNotFound()
                ->assertExactJson(['success' => false, 'message' => 'Resource not found']);
        }
        $this->get('/api/v1/cities?search=not-existing')->assertOk()->assertJsonPath('data.items', []);
        $this->get('/api/v1/cities?country_id=2147483647')->assertOk()->assertJsonPath('data.items', []);
    }

    public function test_listing_status_expiry_and_featured_boundaries_are_read_only(): void
    {
        $future = $this->listing();
        $today = $this->listing(['departure_on' => '2026-11-12']);
        $this->listing(['departure_on' => '2026-11-11']);
        $this->listing(['isActive' => 0]);
        $this->listing(['isActive' => 2]);
        $before = DB::table('post_ads')->orderBy('id')->get()->toJson();
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        $this->get('/api/v1/listings')->assertOk()->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.items.0.id', $today)->assertJsonPath('data.items.1.id', $future);
        $this->get('/api/v1/home')->assertOk()->assertJsonCount(1, 'data.featured_listings')
            ->assertJsonCount(2, 'data.latest_listings')->assertJsonPath('data.featured_listings.0.id', $future);
        $this->get('/api/v1/listings/'.$future)->assertOk();
        foreach (DB::connection()->getQueryLog() as $query) {
            $this->assertMatchesRegularExpression('/^select\b/i', $query['query']);
        }
        $this->assertSame($before, DB::table('post_ads')->orderBy('id')->get()->toJson());
    }

    public function test_search_is_grouped_with_status_expiry_category_and_location_constraints(): void
    {
        $match = $this->listing(['title' => 'Needle parcel']);
        $this->listing(['title' => 'Needle hidden', 'isActive' => 0]);
        $this->listing(['title' => 'Needle expired', 'departure_on' => '2026-11-11']);
        $otherCategory = DB::table('categories')->insertGetId(['name' => 'Needle other', 'isActive' => 1]);
        $this->listing(['category_id' => $otherCategory]);
        $this->listing(['title' => 'Needle elsewhere', 'from_city_id' => $this->otherCity]);
        $this->get('/api/v1/listings?search=Needle&category_id='.$this->category.'&from_city_id='.$this->city)
            ->assertOk()->assertJsonPath('data.pagination.total', 1)->assertJsonPath('data.items.0.id', $match);
        $this->get('/api/v1/listings?search=Parcels&category_id='.$this->category)
            ->assertOk()->assertJsonPath('data.pagination.total', 2);
        $this->get('/api/v1/listings?search=%25')->assertOk()->assertJsonPath('data.items', []);
        $this->get('/api/v1/listings?search=%27%20OR%201%3D1--')->assertOk()->assertJsonPath('data.items', []);
    }

    public function test_supported_transport_filters_are_exact_and_directional(): void
    {
        $id = $this->listing(['ad_type' => 'Send', 'weight' => 7]);
        $this->listing();
        $query = http_build_query(['ad_type' => 'Send', 'weight' => 7, 'departure_on' => '2026-11-13',
            'from_country_id' => $this->country, 'to_country_id' => $this->otherCountry,
            'from_city_id' => $this->city, 'to_city_id' => $this->otherCity]);
        $this->get('/api/v1/listings?'.$query)->assertOk()->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $id);
        $this->get('/api/v1/listings?from_country_id='.$this->otherCountry)->assertOk()->assertJsonPath('data.items', []);
    }

    public function test_stable_sorting_and_pagination_have_no_overlapping_items(): void
    {
        $ids = [$this->listing(), $this->listing(), $this->listing()];
        $this->get('/api/v1/listings?per_page=2')->assertOk()->assertJsonPath('data.pagination', [
            'current_page' => 1, 'per_page' => 2, 'total' => 3, 'last_page' => 2,
        ])->assertJsonPath('data.items.0.id', $ids[2])->assertJsonPath('data.items.1.id', $ids[1]);
        $this->get('/api/v1/listings?per_page=2&page=2')->assertOk()->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $ids[0]);
        $this->get('/api/v1/listings?sort=oldest')->assertOk()->assertJsonPath('data.items.0.id', $ids[0]);
        $this->get('/api/v1/listings?sort=departure')->assertOk()->assertJsonPath('data.items.0.id', $ids[2]);
    }

    public function test_reference_visibility_currency_deduplication_and_city_country_filter(): void
    {
        DB::table('countries')->insert(['name' => 'Hidden', 'short_name' => 'HH', 'currency_name' => 'HID', 'isActive' => 0]);
        DB::table('countries')->insert(['name' => 'Blank', 'short_name' => 'ZZ', 'currency_name' => ' ', 'isActive' => 1]);
        DB::table('categories')->insert(['name' => 'Hidden', 'isActive' => 0]);
        DB::table('cities')->insert(['name' => 'Hidden', 'country_id' => $this->country, 'isActive' => 0]);
        $this->get('/api/v1/categories')->assertOk()->assertJsonCount(1, 'data.items');
        $this->get('/api/v1/countries?per_page=1')->assertOk()->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.items.0.id', $this->country);
        $this->get('/api/v1/currencies')->assertOk()->assertJsonPath('data.items', [['code' => 'USD']])
            ->assertJsonPath('data.pagination.total', 1);
        $this->get('/api/v1/cities?country_id='.$this->country.'&search=Alpha')->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $this->city);
        $this->get('/api/v1/cities?per_page=1')->assertOk()->assertJsonPath('data.pagination.total', 2);
    }

    public function test_inactive_category_and_missing_join_relationships_do_not_expose_listings(): void
    {
        $id = $this->listing();
        DB::table('categories')->where('id', $this->category)->update(['isActive' => 0]);
        $this->get('/api/v1/listings/'.$id)->assertNotFound();
        $this->get('/api/v1/listings')->assertOk()->assertJsonPath('data.items', []);
        DB::table('categories')->where('id', $this->category)->update(['isActive' => 1]);
        DB::table('post_ads')->where('id', $id)->update(['created_by' => 2147483647]);
        $this->get('/api/v1/listings/'.$id)->assertNotFound();
    }

    public function test_description_html_and_contact_information_are_never_returned_raw(): void
    {
        $id = $this->listing(['description' => '<script>secret-script</script><p>Small <b>parcel</b>.</p>']);
        $this->get('/api/v1/listings/'.$id)->assertOk()->assertJsonPath('data.description', 'Small parcel.')
            ->assertDontSee('secret-script')->assertDontSee('<p>', false);
        foreach (['Call +92 300 1234567', 'private-owner&#64;example.test', 'Address: private house',
            'https://private.example.test', 'WhatsApp me'] as $description) {
            DB::table('post_ads')->where('id', $id)->update(['description' => $description]);
            $this->get('/api/v1/listings/'.$id)->assertOk()->assertJsonPath('data.description', null);
        }
    }

    public function test_images_use_trusted_absolute_urls_and_missing_or_unsafe_paths_are_null(): void
    {
        config(['app.url' => 'https://api.example.test']);
        file_put_contents(public_path('frontend/img/listing/synthetic image.png'), 'synthetic-local-test-image');
        $id = $this->listing(['image' => 'synthetic image.png']);
        $this->get('/api/v1/listings/'.$id, ['Host' => 'untrusted.example.test'])->assertOk()
            ->assertJsonPath('data.image_url', 'https://api.example.test/frontend/img/listing/synthetic%20image.png');
        foreach (['missing.png', '../secret.png', 'https://other.example.test/x.png', 'file.svg'] as $image) {
            DB::table('post_ads')->where('id', $id)->update(['image' => $image]);
            $this->get('/api/v1/listings/'.$id)->assertOk()->assertJsonPath('data.image_url', null);
        }
    }

    public function test_home_is_bounded_and_queries_do_not_grow_per_listing(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->listing();
            DB::table('categories')->insert(['name' => 'Category '.$i, 'isActive' => 1]);
        }
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        $response = $this->get('/api/v1/home')->assertOk()->assertJsonCount(12, 'data.categories')
            ->assertJsonCount(8, 'data.featured_listings')->assertJsonCount(8, 'data.latest_listings');
        $this->assertCount(3, DB::connection()->getQueryLog());
        foreach ($response->json('data.latest_listings') as $listing) {
            $this->assertArrayNotHasKey('description', $listing);
        }
    }

    public function test_listing_and_owner_contracts_have_only_explicit_public_fields(): void
    {
        $id = $this->listing();
        $card = $this->get('/api/v1/listings')->assertOk()->json('data.items.0');
        $this->assertSame([
            'id', 'ad_type', 'title', 'short_description', 'image_url', 'category', 'origin', 'destination',
            'weight', 'weight_price', 'currency', 'departure_on', 'reached_on', 'is_featured', 'created_at', 'owner',
        ], array_keys($card));
        $this->assertSame(['id', 'display_name', 'image_url'], array_keys($card['owner']));
        $detail = $this->get('/api/v1/listings/'.$id)->assertOk()->json('data');
        $this->assertSame([...array_keys($card), 'description'], array_keys($detail));
    }

    public function test_api_media_configuration_applies_to_every_public_image_without_changing_web_assets(): void
    {
        config(['app.url' => 'http://localhost', 'api.public_base_url' => 'https://mobile.example.test:8443//friendie///']);
        $filename = 'synthetic image.png';
        foreach (['backend/img/category', 'backend/img/flags', 'frontend/img/listing', 'frontend/img/profiles'] as $directory) {
            file_put_contents(public_path($directory.'/'.$filename), 'synthetic-test-image');
        }
        DB::table('categories')->where('id', $this->category)->update(['image' => $filename]);
        DB::table('countries')->where('id', $this->country)->update(['image' => $filename]);
        DB::table('users')->where('id', $this->owner)->update(['image' => $filename]);
        $id = $this->listing(['image' => $filename]);
        $expected = 'https://mobile.example.test:8443/friendie/';
        $headers = ['Host' => 'attacker.example.test', 'X-Forwarded-Host' => 'attacker.example.test', 'X-Forwarded-Proto' => 'http'];
        $webAssetBefore = asset('backend/img/category/'.$filename);
        $this->get('/api/v1/categories', $headers)->assertOk()
            ->assertJsonPath('data.items.0.image_url', $expected.'backend/img/category/synthetic%20image.png')
            ->assertDontSee('localhost')->assertDontSee('attacker.example.test');
        $this->get('/api/v1/countries', $headers)->assertOk()
            ->assertJsonPath('data.items.0.image_url', $expected.'backend/img/flags/synthetic%20image.png');
        $this->get('/api/v1/home', $headers)->assertOk()
            ->assertJsonPath('data.categories.0.image_url', $expected.'backend/img/category/synthetic%20image.png')
            ->assertJsonPath('data.featured_listings.0.image_url', $expected.'frontend/img/listing/synthetic%20image.png')
            ->assertJsonPath('data.latest_listings.0.image_url', $expected.'frontend/img/listing/synthetic%20image.png')
            ->assertJsonPath('data.latest_listings.0.owner.image_url', $expected.'frontend/img/profiles/synthetic%20image.png');
        $this->get('/api/v1/listings', $headers)->assertOk()
            ->assertJsonPath('data.items.0.image_url', $expected.'frontend/img/listing/synthetic%20image.png')
            ->assertJsonPath('data.items.0.owner.image_url', $expected.'frontend/img/profiles/synthetic%20image.png');
        $this->get('/api/v1/listings/'.$id, $headers)->assertOk()
            ->assertJsonPath('data.image_url', $expected.'frontend/img/listing/synthetic%20image.png')
            ->assertJsonPath('data.owner.image_url', $expected.'frontend/img/profiles/synthetic%20image.png')
            ->assertDontSee(base_path(), false)->assertDontSee('storage/app', false);
        $this->assertSame('http://localhost', config('app.url'));
        $this->assertSame($webAssetBefore, asset('backend/img/category/'.$filename));
    }

    public static function invalidMediaBases(): array
    {
        return array_map(fn ($base) => [$base], [
            '/relative', 'file:///G:/private', 'ftp://media.example.test',
            'https://user:secret@media.example.test', 'https://media.example.test?token=secret',
            'https://media.example.test#private', 'https://media.example.test/../private',
            'https://media.example.test/%2e%2e/private', "https://media.example.test\r\nX-Test: bad",
            'https://media.example.test\\private',
        ]);
    }

    #[DataProvider('invalidMediaBases')]
    public function test_invalid_media_configuration_never_leaks_private_or_non_http_urls(string $base): void
    {
        config(['api.public_base_url' => $base]);
        file_put_contents(public_path('backend/img/category/synthetic.png'), 'synthetic-test-image');
        DB::table('categories')->where('id', $this->category)->update(['image' => 'synthetic.png']);
        $this->get('/api/v1/categories')->assertOk()->assertJsonPath('data.items.0.image_url', null);
    }

    public function test_blank_api_media_setting_falls_back_to_app_url_and_null_values_remain_null(): void
    {
        config(['api.public_base_url' => ' ', 'app.url' => 'https://fallback.example.test/base/']);
        file_put_contents(public_path('backend/img/category/synthetic.png'), 'synthetic-test-image');
        DB::table('categories')->where('id', $this->category)->update(['image' => 'synthetic.png']);
        $this->get('/api/v1/categories')->assertOk()
            ->assertJsonPath('data.items.0.image_url', 'https://fallback.example.test/base/backend/img/category/synthetic.png');
        $id = $this->listing(['image' => null]);
        $this->get('/api/v1/listings/'.$id)->assertOk()->assertJsonPath('data.image_url', null)
            ->assertJsonPath('data.owner.image_url', null);
    }
}
