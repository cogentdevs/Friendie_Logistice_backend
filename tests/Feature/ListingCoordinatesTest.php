<?php

namespace Tests\Feature;

use App\Http\Controllers\PostAdsController;
use App\Models\User;
use App\Support\Api\ListingCoordinates;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ListingCoordinatesTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;
    private User $other;
    private string $token;
    private array $fields;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2030-01-01 12:00:00', 'UTC'));
        Role::findOrCreate('user', 'web'); Role::findOrCreate('admin', 'web');
        $this->owner = User::factory()->create(['isActive'=>1, 'isVerified'=>1]);
        $this->other = User::factory()->create(['isActive'=>1, 'isVerified'=>1]);
        $this->owner->assignRole('user'); $this->other->assignRole('user');
        $this->token = $this->owner->createToken('mobile', ['mobile:account'])->plainTextToken;
        $category = DB::table('categories')->insertGetId(['name'=>'Parcels', 'isActive'=>1]);
        $country = DB::table('countries')->insertGetId(['name'=>'Alpha', 'isActive'=>1, 'currency_name'=>'USD']);
        $city = DB::table('cities')->insertGetId(['name'=>'Alpha City', 'country_id'=>$country, 'isActive'=>1]);
        $this->fields = ['ad_type'=>'Carring', 'title'=>'Parcel capacity', 'category_id'=>$category,
            'description'=>'<p>Safe description</p>', 'from_country_id'=>$country, 'from_city_id'=>$city,
            'to_country_id'=>$country, 'to_city_id'=>$city, 'from_area'=>'Origin area', 'to_area'=>'Destination area',
            'weight'=>5, 'weight_price'=>20, 'currency'=>'USD', 'departure_on'=>'2030-01-02', 'reached_on'=>'2030-01-03'];
    }

    private function migrated(): void
    {
        if (! ListingCoordinates::available()) $this->markTestSkipped('User must manually apply the coordinate migration to the isolated schema.');
    }

    public function test_api_accepts_maps_links_and_pair_strings_without_saving_urls(): void
    {
        $this->migrated();
        \Illuminate\Support\Facades\Http::fake();
        $id=$this->postJson('/api/v1/customer/listings',$this->fields+[
            'from_location_pin'=>'https://www.google.com/maps?q=24.8607343,67.0011364',
            'to_location_pin'=>'0, 0',
        ],$this->headers())->assertCreated()->assertJsonPath('data.from_latitude',24.8607343)
            ->assertJsonPath('data.to_longitude',0)->assertJsonMissingPath('data.from_location_pin')->json('data.id');
        $this->assertSame('24.8607343',DB::table('post_ads')->where('id',$id)->value('from_latitude'));
        $this->putJson('/api/v1/customer/listings/'.$id,['to_location_pin'=>'https://maps.google.com/?q=-33.8688197,151.2092955'],$this->headers())
            ->assertOk()->assertJsonPath('data.to_latitude',-33.8688197)->assertJsonPath('data.from_latitude',24.8607343);
        $this->putJson('/api/v1/customer/listings/'.$id,['title'=>'Same pins'],$this->headers())->assertOk()->assertJsonPath('data.to_latitude',-33.8688197);
        $this->putJson('/api/v1/customer/listings/'.$id,['from_location_pin'=>null],$this->headers())->assertOk()->assertJsonPath('data.from_latitude',null)
            ->assertJsonPath('data.from_longitude',null)->assertJsonPath('data.to_latitude',-33.8688197);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_invalid_maps_pin_and_conflicting_inputs_do_not_mutate_listing_or_leak_link_details(): void
    {
        $this->migrated(); $id=$this->create(['from_latitude'=>1,'from_longitude'=>2]);
        $resolver=\Mockery::mock(\App\Services\Api\GoogleMapsPinResolver::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $resolver->shouldReceive('addresses')->andReturn(['8.8.8.8']);
        app()->instance(\App\Services\Api\GoogleMapsPinResolver::class,$resolver);
        $before=DB::table('post_ads')->find($id);
        foreach ([['from_location_pin'=>'https://127.0.0.1/maps?q=1,2'],['from_location_pin'=>[]],
            ['from_location_pin'=>'1,2','from_latitude'=>1,'from_longitude'=>2],
            ['from_location_pin'=>'https://www.google.com/maps?q=91,0']] as $input) {
            \Illuminate\Support\Facades\Http::fake(fn()=>\Illuminate\Support\Facades\Http::response('',200));
            $reply=$this->putJson('/api/v1/customer/listings/'.$id,$input,$this->headers())->assertUnprocessable()->assertJsonValidationErrors('from_location_pin');
            $this->assertStringNotContainsString('127.0.0.1',$reply->getContent());
            $this->assertEquals($before,DB::table('post_ads')->find($id));
        }
    }

    public function test_short_maps_link_resolves_before_locks_and_timeout_leaves_existing_pin_unchanged(): void
    {
        $this->migrated(); $id=$this->create(['from_latitude'=>1,'from_longitude'=>2]);
        $resolver=\Mockery::mock(\App\Services\Api\GoogleMapsPinResolver::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $resolver->shouldReceive('addresses')->andReturn(['8.8.8.8']);
        app()->instance(\App\Services\Api\GoogleMapsPinResolver::class,$resolver);
        $outerLevel=DB::transactionLevel();
        \Illuminate\Support\Facades\Http::fake(function () use ($outerLevel) {
            $this->assertSame($outerLevel,DB::transactionLevel(),'External resolution must precede listing/customer locks.');
            return \Illuminate\Support\Facades\Http::response('',302,['Location'=>'https://www.google.com/maps?q=3,4']);
        });
        $this->putJson('/api/v1/customer/listings/'.$id,['from_location_pin'=>'https://maps.app.goo.gl/example'],$this->headers())
            ->assertOk()->assertJsonPath('data.from_latitude',3)->assertJsonPath('data.from_longitude',4);
        $before=DB::table('post_ads')->find($id);
        \Illuminate\Support\Facades\Http::fake(fn()=>throw new \Illuminate\Http\Client\ConnectionException('Synthetic timeout: private transport detail'));
        $response=$this->putJson('/api/v1/customer/listings/'.$id,['from_location_pin'=>'https://maps.app.goo.gl/timeout'],$this->headers())
            ->assertUnprocessable()->assertJsonValidationErrors('from_location_pin');
        $this->assertStringNotContainsString('private transport detail',$response->getContent());
        $this->assertEquals($before,DB::table('post_ads')->find($id));
        $this->putJson('/api/v1/customer/listings/'.$id,['to_location_pin'=>'0,0','to_city_id'=>$this->fields['to_city_id']],$this->headers())
            ->assertOk()->assertJsonPath('data.to_latitude',0);
    }

    private function headers(?string $token = null): array
    {
        app('auth')->forgetGuards();
        return ['Authorization'=>'Bearer '.($token ?? $this->token)];
    }

    private function create(array $coordinates = []): int
    {
        $response = $this->postJson('/api/v1/customer/listings', $this->fields + $coordinates, $this->headers())
            ->assertCreated()->assertJsonPath('data.status', 1);
        foreach (ListingCoordinates::FIELDS as $key) {
            if (($coordinates[$key] ?? null) === null) $response->assertJsonPath('data.'.$key, null);
            else {
                $value=$response->json('data.'.$key);
                $this->assertTrue(is_int($value) || is_float($value));
                $this->assertEqualsWithDelta((float)$coordinates[$key],$value,0.00000001);
            }
        }

        return $response->json('data.id');
    }

    public static function validPairs(): array
    {
        return [
            [[]], [['from_latitude'=>null, 'from_longitude'=>null]],
            [['from_latitude'=>0, 'from_longitude'=>0]],
            [['from_latitude'=>-90, 'from_longitude'=>-180]],
            [['to_latitude'=>90, 'to_longitude'=>180]],
            [['from_latitude'=>-33.8688197, 'from_longitude'=>151.2092955]],
            [['to_latitude'=>'24.8607343', 'to_longitude'=>'67.0011364']],
            [['from_latitude'=>0.0000001, 'from_longitude'=>-0.0000001]],
            [['from_latitude'=>24.8607343, 'from_longitude'=>67.0011364, 'to_latitude'=>31.5203696, 'to_longitude'=>74.3587473]],
        ];
    }

    #[DataProvider('validPairs')]
    public function test_create_persists_pairs_and_owner_responses_use_numbers_or_null(array $pins): void
    {
        $this->migrated(); $id = $this->create($pins);
        $reply = $this->getJson('/api/v1/customer/listings/'.$id, $this->headers())->assertOk();
        $row = DB::table('post_ads')->find($id);
        foreach (ListingCoordinates::FIELDS as $key) {
            if (($pins[$key] ?? null) === null) {
                $reply->assertJsonPath('data.'.$key, null); $this->assertNull($row->{$key});
            } else {
                $actual = $reply->json('data.'.$key);
                $this->assertTrue(is_int($actual) || is_float($actual));
                $this->assertEqualsWithDelta((float)$pins[$key], $actual, 0.00000001);
                $this->assertEqualsWithDelta((float)$pins[$key], (float)$row->{$key}, 0.00000001);
            }
        }
    }

    public static function invalidPairs(): array
    {
        return [
            [['from_latitude'=>1]], [['to_longitude'=>1]],
            [['from_latitude'=>null, 'from_longitude'=>1]],
            [['from_latitude'=>91, 'from_longitude'=>0]], [['from_latitude'=>0, 'from_longitude'=>181]],
            [['to_latitude'=>-91, 'to_longitude'=>0]], [['to_latitude'=>0, 'to_longitude'=>-181]],
            [['from_latitude'=>true, 'from_longitude'=>false]],
            [['from_latitude'=>[], 'from_longitude'=>[]]],
            [['from_latitude'=>['value'=>1], 'from_longitude'=>['value'=>1]]],
            [['from_latitude'=>'NaN', 'from_longitude'=>'Infinity']],
            [['from_latitude'=>'1e2', 'from_longitude'=>'1,2']],
            [['from_latitude'=>'24.12345678', 'from_longitude'=>0]],
            [['from_latitude'=>24.12345678, 'from_longitude'=>0]],
            [['from_latitude'=>0.00000001, 'from_longitude'=>0]],
        ];
    }

    #[DataProvider('invalidPairs')]
    public function test_invalid_create_and_update_do_not_write(array $pins): void
    {
        $this->migrated();
        $this->postJson('/api/v1/customer/listings', $this->fields + $pins, $this->headers())->assertUnprocessable();
        $this->assertSame(0, DB::table('post_ads')->count());
        $id = $this->create(['from_latitude'=>1, 'from_longitude'=>2]);
        $before = DB::table('post_ads')->find($id);
        $this->putJson('/api/v1/customer/listings/'.$id, $pins + ['title'=>'Must not change'], $this->headers())->assertUnprocessable();
        $this->assertEquals($before, DB::table('post_ads')->find($id));
    }

    public function test_partial_updates_clearing_replacement_and_geography_changes_are_independent(): void
    {
        $this->migrated();
        $id = $this->create(['from_latitude'=>1, 'from_longitude'=>2, 'to_latitude'=>3, 'to_longitude'=>4]);
        $url = '/api/v1/customer/listings/'.$id;
        $this->putJson($url, ['title'=>'Updated'], $this->headers())->assertOk()->assertJsonPath('data.from_latitude', 1)->assertJsonPath('data.to_latitude', 3);
        $this->putJson($url, ['from_latitude'=>'5.0000000', 'from_longitude'=>'6.0000000'], $this->headers())->assertOk()
            ->assertJsonPath('data.from_latitude', 5)->assertJsonPath('data.to_longitude', 4);
        $this->putJson($url, ['to_latitude'=>null, 'to_longitude'=>null], $this->headers())->assertOk()
            ->assertJsonPath('data.to_latitude', null)->assertJsonPath('data.from_latitude', 5);
        $city = DB::table('cities')->insertGetId(['name'=>'Other City', 'country_id'=>$this->fields['from_country_id'], 'isActive'=>1]);
        $this->putJson($url, ['from_city_id'=>$city], $this->headers())->assertOk()->assertJsonPath('data.from_latitude', null)->assertJsonPath('data.from_longitude', null);
        $this->putJson($url, ['from_city_id'=>$this->fields['from_city_id'], 'from_latitude'=>7, 'from_longitude'=>8], $this->headers())->assertOk()
            ->assertJsonPath('data.from_latitude', 7)->assertJsonPath('data.from_longitude', 8);
        $this->putJson($url, ['to_latitude'=>'', 'to_longitude'=>''], $this->headers())->assertOk()->assertJsonPath('data.to_latitude', null);
        $this->putJson($url, ['from_latitude'=>null], $this->headers())->assertUnprocessable();
    }

    public function test_public_chat_review_and_nonowner_responses_never_expose_exact_pins(): void
    {
        $this->migrated(); $id = $this->create(['from_latitude'=>24.8607343, 'from_longitude'=>67.0011364]);
        $otherToken = $this->other->createToken('mobile', ['mobile:account'])->plainTextToken;
        foreach (['/api/v1/home', '/api/v1/listings?search=Parcel', '/api/v1/listings/'.$id] as $url) {
            foreach ([[], $this->headers($otherToken), $this->headers()] as $headers) {
                $response = $this->getJson($url, $headers)->assertOk();
                foreach (ListingCoordinates::FIELDS as $key) $response->assertDontSee($key);
                $response->assertDontSee('24.8607343')->assertDontSee('67.0011364');
            }
        }
        $this->getJson('/api/v1/customer/listings/'.$id)->assertUnauthorized();
        $this->getJson('/api/v1/customer/listings/'.$id, $this->headers($otherToken))->assertNotFound();
        $this->putJson('/api/v1/customer/listings/'.$id, ['from_latitude'=>0, 'from_longitude'=>0], $this->headers($otherToken))->assertNotFound();
        $sender=DB::table('post_ads')->insertGetId($this->fields+['created_by'=>$this->other->id,'isActive'=>1,'show_contact_info'=>0]);
        DB::table('post_ads')->where('id',$sender)->update(['ad_type'=>'Send']);
        $deal=DB::table('listing_deals')->insertGetId(['sender_listing_id'=>$sender,'carrier_listing_id'=>$id,
            'sender_user_id'=>$this->other->id,'carrier_user_id'=>$this->owner->id,'initiated_by'=>$this->other->id,
            'requested_kg'=>5,'agreed_kg'=>5,'status'=>'confirmed','listing_snapshot'=>'{}']);
        $room = $this->postJson('/api/v1/customer/conversations', ['deal_id'=>$deal, 'message'=>'Hello'], $this->headers($otherToken))->assertCreated();
        $review = $this->postJson('/api/v1/customer/reviews', ['post_ads_id'=>$id, 'rating'=>0, 'review'=>'Good'], $this->headers($otherToken))->assertCreated();
        foreach (ListingCoordinates::FIELDS as $key) { $room->assertDontSee($key); $review->assertDontSee($key); }
        $history = $this->getJson('/api/v1/customer/reviews/given', $this->headers($otherToken))->assertOk();
        $chat = $this->getJson('/api/v1/customer/conversations', $this->headers($otherToken))->assertOk();
        foreach (ListingCoordinates::FIELDS as $key) { $history->assertDontSee($key); $chat->assertDontSee($key); }
        $this->getJson('/api/v1/customer/listings/'.$id, $this->headers())->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public static function webLocationChanges(): array
    {
        return [['from_country_id'], ['from_city_id'], ['to_country_id'], ['to_city_id'], ['both'], ['unchanged'], ['missing']];
    }

    #[DataProvider('webLocationChanges')]
    public function test_shared_web_admin_edit_preserves_or_clears_only_changed_location(string $change): void
    {
        $this->migrated(); $id = $this->create(['from_latitude'=>1, 'from_longitude'=>2, 'to_latitude'=>3, 'to_longitude'=>4]);
        foreach ([$this->owner, $this->other] as $actor) {
            DB::table('post_ads')->where('id', $id)->update($this->fields + ['from_latitude'=>1, 'from_longitude'=>2, 'to_latitude'=>3, 'to_longitude'=>4]);
            if ($actor->is($this->other)) $actor->syncRoles(['admin']);
            $this->actingAs($actor, 'web');
            $data = $this->fields + ['cur_image'=>'listing.png', 'isActive'=>1, 'show_contact_info'=>0];
            $data['description'] = '<p>Changed</p>'; $data['weight_price'] = 30;
            $country = DB::table('countries')->insertGetId(['name'=>'Beta', 'isActive'=>1, 'currency_name'=>'USD']);
            $city = DB::table('cities')->insertGetId(['name'=>'Beta City', 'country_id'=>$country, 'isActive'=>1]);
            if ($change === 'both') {
                foreach (['from', 'to'] as $side) { $data[$side.'_country_id']=$country; $data[$side.'_city_id']=$city; }
            } elseif ($change === 'missing') {
                foreach (['from', 'to'] as $side) { unset($data[$side.'_country_id'], $data[$side.'_city_id']); }
            } elseif ($change !== 'unchanged') {
                $data[$change] = str_contains($change, 'country') ? $country : $city;
            } else {
                foreach (['from', 'to'] as $side) foreach (['country', 'city'] as $kind) $data[$side.'_'.$kind.'_id'] = (string)$data[$side.'_'.$kind.'_id'];
            }
            (new PostAdsController())->editPostAds(Request::create('/user/edit-listing/'.$id, 'POST', $data), $id);
            $row = DB::table('post_ads')->find($id);
            foreach (['from'=>[1,2], 'to'=>[3,4]] as $side=>$pin) {
                $clear = $change === 'both' || str_starts_with($change, $side.'_');
                foreach (['latitude', 'longitude'] as $index=>$axis) {
                    $actual = $row->{$side.'_'.$axis};
                    if ($clear) $this->assertNull($actual); else $this->assertSame((float)$pin[$index], (float)$actual);
                }
            }
            $this->assertSame('listing.png', $row->image); $this->assertSame(1, (int)$row->isActive);
        }
    }

    public function test_missing_schema_keeps_legacy_create_detail_and_web_edit_working_and_rejects_new_pins(): void
    {
        // Simulate unavailable columns without executing DDL on any database.
        $dealTable = Schema::hasTable('listing_deals');
        Schema::shouldReceive('hasColumns')->with('post_ads', ListingCoordinates::FIELDS)->andReturn(false);
        Schema::shouldReceive('hasTable')->with('listing_deals')->andReturn($dealTable);
        $id = $this->create();
        $this->getJson('/api/v1/customer/listings/'.$id, $this->headers())->assertOk()->assertJsonPath('data.from_latitude', null);
        $this->putJson('/api/v1/customer/listings/'.$id, ['title'=>'Legacy edit'], $this->headers())->assertOk();
        $this->postJson('/api/v1/customer/listings', $this->fields + ['from_latitude'=>0, 'from_longitude'=>0], $this->headers())
            ->assertUnprocessable()->assertJsonValidationErrors(['from_latitude','from_longitude']);
        $this->actingAs($this->owner, 'web');
        (new PostAdsController())->editPostAds(Request::create('/user/edit-listing/'.$id, 'POST',
            $this->fields + ['cur_image'=>'listing.png', 'isActive'=>1, 'show_contact_info'=>0]), $id);
        $this->assertSame(1, DB::table('post_ads')->count());
    }

    public function test_failed_update_rolls_back_ids_and_pins_together(): void
    {
        $this->migrated(); $id=$this->create(['from_latitude'=>1,'from_longitude'=>2]);
        $before=DB::table('post_ads')->find($id);
        DB::connection()->beforeExecuting(function ($sql) {
            if (str_starts_with(strtolower($sql), 'update `post_ads`')) throw new \RuntimeException('Synthetic update failure');
        });
        $this->putJson('/api/v1/customer/listings/'.$id, ['from_latitude'=>3,'from_longitude'=>4], $this->headers())->assertStatus(500);
        $this->assertEquals($before,DB::table('post_ads')->find($id));
    }

    public function test_api_and_web_edit_take_the_same_listing_row_lock(): void
    {
        $this->migrated(); $id=$this->create(['from_latitude'=>1,'from_longitude'=>2]);
        $config=DB::connection()->getConfig();
        $observer=new \PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'],
            $config['username'],$config['password'],[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);
        $checked=0;
        DB::listen(function ($query) use ($observer,$id,&$checked) {
            if (!str_contains(strtolower($query->sql),'`post_ads`') || !str_contains(strtolower($query->sql),'for update')) return;
            $observer->beginTransaction();
            try {
                $observer->query('SELECT id FROM post_ads WHERE id='.(int)$id.' FOR UPDATE NOWAIT');
                $this->fail('Competing connection must not acquire the locked listing');
            } catch (\PDOException $error) {
                $this->assertSame(1205,(int)$error->errorInfo[1]); $checked++;
            } finally { $observer->rollBack(); }
        });
        $this->putJson('/api/v1/customer/listings/'.$id,['from_latitude'=>3,'from_longitude'=>4],$this->headers())->assertOk();
        $this->actingAs($this->owner,'web');
        (new PostAdsController())->editPostAds(Request::create('/user/edit-listing/'.$id,'POST',
            $this->fields+['cur_image'=>'listing.png','isActive'=>1,'show_contact_info'=>0]),$id);
        $this->assertGreaterThanOrEqual(2,$checked);
        $this->assertSame(3.0,(float)DB::table('post_ads')->where('id',$id)->value('from_latitude'));
    }

    public function test_nonfinite_values_and_invalid_legacy_ids_are_rejected_without_changes(): void
    {
        $this->migrated();
        foreach ([NAN,INF,-INF] as $number) {
            try { ListingCoordinates::validate(['from_latitude'=>$number,'from_longitude'=>0]); $this->fail('Nonfinite value accepted'); }
            catch (\Illuminate\Validation\ValidationException $error) { $this->assertArrayHasKey('from_latitude',$error->errors()); }
        }
        $id=$this->create(['from_latitude'=>1,'from_longitude'=>2]); $before=DB::table('post_ads')->find($id);
        $this->actingAs($this->owner,'web');
        try {
            (new PostAdsController())->editPostAds(Request::create('/user/edit-listing/'.$id,'POST',
                array_replace($this->fields,['from_city_id'=>'invalid','cur_image'=>'listing.png','isActive'=>1,'show_contact_info'=>0])),$id);
            $this->fail('Malformed location ID accepted');
        } catch (\Illuminate\Validation\ValidationException $error) { $this->assertArrayHasKey('from_city_id',$error->errors()); }
        $this->assertEquals($before,DB::table('post_ads')->find($id));
    }

    public function test_form_decimal_strings_and_failed_web_updates_preserve_atomicity(): void
    {
        $this->migrated();
        $reply=$this->post('/api/v1/customer/listings',$this->fields+['from_latitude'=>'24.8607343','from_longitude'=>'67.0011364'],
            $this->headers()+['Accept'=>'application/json'])->assertCreated();
        $id=$reply->json('data.id');
        $reply->assertJsonPath('data.from_latitude',24.8607343)->assertJsonPath('data.from_longitude',67.0011364);
        $before=DB::table('post_ads')->find($id);
        $this->actingAs($this->owner,'web');
        DB::connection()->beforeExecuting(function ($sql) {
            if (str_starts_with(strtolower($sql),'update `post_ads`')) throw new \RuntimeException('Synthetic web update failure');
        });
        try {
            (new PostAdsController())->editPostAds(Request::create('/user/edit-listing/'.$id,'POST',
                array_replace($this->fields,['from_city_id'=>$this->fields['from_city_id']+1,'cur_image'=>'listing.png','isActive'=>1,'show_contact_info'=>0])),$id);
            $this->fail('Synthetic update must fail');
        } catch (\RuntimeException $error) { $this->assertSame('Synthetic web update failure',$error->getMessage()); }
        $this->assertEquals($before,DB::table('post_ads')->find($id));
    }
}
