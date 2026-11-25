<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Api\ListingDeals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ListingAvailabilityTest extends TestCase
{
    use DatabaseTransactions;

    private User $sender;
    private User $carrier;
    private User $other;
    private array $tokens=[];
    private array $fields;
    private int $send;
    private int $carry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(Schema::hasTable('listing_deals'));
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-25 12:00:00','UTC'));
        Role::findOrCreate('user','web'); Role::findOrCreate('admin','web');
        foreach (['sender','carrier','other'] as $name) {
            $this->$name=User::factory()->create(['isActive'=>1,'isVerified'=>1]);
            $this->$name->assignRole('user');
            $this->tokens[$name]=$this->$name->createToken('mobile',['mobile:account'],now()->addDays(30))->plainTextToken;
        }
        $category=DB::table('categories')->insertGetId(['name'=>'Parcels','isActive'=>1]);
        $country=DB::table('countries')->insertGetId(['name'=>'Alpha','isActive'=>1,'currency_name'=>'USD']);
        $city=DB::table('cities')->insertGetId(['name'=>'City','country_id'=>$country,'isActive'=>1]);
        $this->fields=['ad_type'=>'Send','title'=>'Parcel','description'=>'Safe parcel','weight'=>5,
            'category_id'=>$category,'from_country_id'=>$country,'to_country_id'=>$country,'from_city_id'=>$city,'to_city_id'=>$city,
            'departure_on'=>'2030-01-01','reached_on'=>'2030-01-02','isActive'=>1,'isFeatured'=>1,'show_contact_info'=>0];
        $this->send=$this->listing($this->sender);
        $this->carry=$this->listing($this->carrier,['ad_type'=>'Carring','weight'=>12]);
    }

    private function listing(User $owner,array $changes=[]): int
    {
        return DB::table('post_ads')->insertGetId(array_replace($this->fields,['created_by'=>$owner->id],$changes));
    }
    private function headers(?string $who=null): array
    {
        app('auth')->forgetGuards();
        return $who ? ['Authorization'=>'Bearer '.$this->tokens[$who]] : [];
    }
    private function show(int $id,?string $who=null)
    {
        return $this->getJson('/api/v1/listings/'.$id,$this->headers($who))->assertOk();
    }
    private function deal(string $status,array $extra=[]): int
    {
        return DB::table('listing_deals')->insertGetId(array_replace([
            'sender_listing_id'=>$this->send,'carrier_listing_id'=>$this->carry,
            'sender_user_id'=>$this->sender->id,'carrier_user_id'=>$this->carrier->id,'initiated_by'=>$this->sender->id,
            'requested_kg'=>5,'agreed_kg'=>5,'status'=>$status,'active_pair'=>null,'listing_snapshot'=>'{}',
            'acceptance_expires_at'=>now()->addHours(24),'created_at'=>now(),'updated_at'=>now(),
        ],$extra));
    }

    public function test_guest_available_listing_is_distinct_from_authenticated_request_eligibility(): void
    {
        $this->show($this->carry)->assertJsonPath('data.weight',12)->assertJsonPath('data.availability',[
            'total_kg'=>12,'reserved_kg'=>0,'committed_kg'=>0,'remaining_kg'=>12,'available'=>true,
        ])->assertJsonPath('data.request_eligibility',['eligible'=>null,'reason'=>'authentication_required']);
        $this->show($this->carry,'sender')->assertJsonPath('data.request_eligibility',['eligible'=>true,'reason'=>'eligible']);
        $this->show($this->carry,'other')->assertJsonPath('data.availability.available',true)
            ->assertJsonPath('data.request_eligibility',['eligible'=>false,'reason'=>'no_eligible_complementary_listing']);
        $this->show($this->carry,'carrier')->assertJsonPath('data.request_eligibility',['eligible'=>false,'reason'=>'self_listing']);
        $this->show($this->send,'carrier')->assertJsonPath('data.request_eligibility.eligible',true);
    }

    public function test_actual_lifecycle_is_reflected_across_public_home_and_owner_listing_responses(): void
    {
        $id=$this->postJson('/api/v1/customer/deals',['sender_listing_id'=>$this->send,'carrier_listing_id'=>$this->carry,'requested_kg'=>5],$this->headers('sender'))
            ->assertCreated()->json('data.id');
        $this->show($this->carry,'sender')->assertJsonPath('data.availability.available',true)->assertJsonPath('data.request_eligibility.eligible',false);
        $this->postJson('/api/v1/customer/deals/'.$id.'/accept',[],$this->headers('carrier'))->assertOk();
        $this->show($this->carry)->assertJsonPath('data.availability.reserved_kg',5)->assertJsonPath('data.availability.remaining_kg',7)->assertJsonPath('data.availability.available',false);
        $this->getJson('/api/v1/customer/listings/'.$this->carry,$this->headers('carrier'))->assertOk()->assertJsonPath('data.availability.reserved_kg',5);
        $this->postJson('/api/v1/customer/deals/'.$id.'/confirm',[],$this->headers('sender'))->assertOk();
        $this->show($this->carry)->assertJsonPath('data.availability.committed_kg',5)->assertJsonPath('data.availability.reserved_kg',0)
            ->assertJsonPath('data.availability.remaining_kg',7)->assertJsonPath('data.availability.available',true);
        $this->show($this->send)->assertJsonPath('data.availability.remaining_kg',0)->assertJsonPath('data.availability.available',false);
        $home=$this->getJson('/api/v1/home',$this->headers())->assertOk();
        foreach (['latest_listings','featured_listings'] as $section) {
            $item=collect($home->json('data.'.$section))->firstWhere('id',$this->carry);
            $this->assertSame(5,$item['availability']['committed_kg']);
        }
        $this->postJson('/api/v1/customer/deals/'.$id.'/cancel',[],$this->headers('sender'))->assertOk();
        $this->show($this->carry,'sender')->assertJsonPath('data.availability.remaining_kg',12)->assertJsonPath('data.request_eligibility.eligible',true);
    }

    public function test_exact_deadline_excludes_reservation_without_writing_deal_or_token(): void
    {
        $id=$this->deal('accepted'); $this->travel(24)->hours();
        $before=DB::table('listing_deals')->find($id);
        $tokens=DB::table('personal_access_tokens')->orderBy('id')->get()->toJson();
        DB::connection()->enableQueryLog(); DB::connection()->flushQueryLog();
        $this->show($this->carry,'sender')->assertJsonPath('data.availability.reserved_kg',0)->assertJsonPath('data.request_eligibility.eligible',true);
        foreach (DB::connection()->getQueryLog() as $q) $this->assertMatchesRegularExpression('/^select\b/i',$q['query']);
        $this->assertEquals($before,DB::table('listing_deals')->find($id));
        $this->assertSame($tokens,DB::table('personal_access_tokens')->orderBy('id')->get()->toJson());
    }

    public function test_completed_commitments_and_terminal_requests_do_not_reopen_booked_listing(): void
    {
        foreach (['pending','rejected','cancelled','expired'] as $status) $this->deal($status);
        $this->show($this->carry)->assertJsonPath('data.availability.committed_kg',0)->assertJsonPath('data.availability.remaining_kg',12);
        $this->deal('completed',['agreed_kg'=>12]);
        $this->show($this->carry)->assertJsonPath('data.availability.available',false)->assertJsonPath('data.availability.remaining_kg',0);
        $this->getJson('/api/v1/listings?ad_type=Carring',$this->headers())->assertOk()->assertJsonPath('data.pagination.total',1)
            ->assertJsonPath('data.items.0.weight',12)->assertJsonPath('data.items.0.availability.committed_kg',12);
    }

    public function test_matching_route_full_parcel_capacity_and_candidate_hold_are_required(): void
    {
        DB::table('post_ads')->where('id',$this->send)->update(['weight'=>13]);
        $this->show($this->carry,'sender')->assertJsonPath('data.availability.available',true)->assertJsonPath('data.request_eligibility.eligible',false);
        DB::table('post_ads')->where('id',$this->send)->update(['weight'=>5,'isActive'=>0]);
        $this->show($this->carry,'sender')->assertJsonPath('data.request_eligibility.eligible',false);
        DB::table('post_ads')->where('id',$this->send)->update(['isActive'=>1,'to_country_id'=>$this->fields['to_country_id']+1]);
        $this->show($this->carry,'sender')->assertJsonPath('data.request_eligibility.eligible',false);
        DB::table('post_ads')->where('id',$this->send)->update(['to_country_id'=>$this->fields['to_country_id']]);
        $another=$this->listing($this->other,['ad_type'=>'Carring','weight'=>12]);
        $this->deal('accepted',['carrier_listing_id'=>$another]);
        $this->show($this->carry,'sender')->assertJsonPath('data.availability.available',true)->assertJsonPath('data.request_eligibility.eligible',false);
    }

    public function test_one_free_candidate_is_sufficient_but_active_pair_prevents_duplicate_request(): void
    {
        $this->deal('pending');
        $this->show($this->carry,'sender')->assertJsonPath('data.request_eligibility.eligible',false);
        $this->listing($this->sender,['weight'=>4]);
        $this->show($this->carry,'sender')->assertJsonPath('data.request_eligibility.eligible',true);
    }

    public function test_ineligible_or_invalid_bearer_never_blocks_public_browsing_or_makes_caller_eligible(): void
    {
        foreach ([['isActive'=>0],['isActive'=>1,'isVerified'=>0]] as $changes) {
            $this->sender->update($changes);
            $this->show($this->carry,'sender')->assertJsonPath('data.availability.available',true)
                ->assertJsonPath('data.request_eligibility',['eligible'=>false,'reason'=>'ineligible_account']);
        }
        $this->sender->update(['isActive'=>1,'isVerified'=>1]);
        foreach ([['admin'],['user','admin']] as $roles) {
            $this->sender->syncRoles($roles);
            $this->show($this->carry,'sender')->assertJsonPath('data.request_eligibility.reason','ineligible_account');
        }
        $this->sender->syncRoles(['user']);
        $this->tokens['sender']=$this->sender->createToken('other',['other'])->plainTextToken;
        $this->show($this->carry,'sender')->assertJsonPath('data.request_eligibility.eligible',false);
        $this->tokens['sender']='invalid';
        $this->show($this->carry,'sender')->assertJsonPath('data.request_eligibility.eligible',null);
        $expired=$this->sender->createToken('mobile',['mobile:account'],now()->subMinute());
        $this->tokens['sender']=$expired->plainTextToken;
        $this->show($this->carry,'sender')->assertJsonPath('data.request_eligibility.eligible',null);
        $revoked=$this->sender->createToken('mobile',['mobile:account']); $revoked->accessToken->delete();
        $this->tokens['sender']=$revoked->plainTextToken;
        $this->show($this->carry,'sender')->assertJsonPath('data.request_eligibility.eligible',null);
    }

    public function test_owner_eligibility_and_nonpublic_listings_do_not_change_existing_public_visibility_scope(): void
    {
        $this->carrier->update(['isVerified'=>0]);
        $this->show($this->carry)->assertJsonPath('data.availability.available',false);
        $this->carrier->update(['isVerified'=>1]);
        DB::table('post_ads')->where('id',$this->carry)->update(['isActive'=>2]);
        $this->getJson('/api/v1/listings/'.$this->carry,$this->headers())->assertNotFound();
        $this->getJson('/api/v1/customer/listings/'.$this->carry,$this->headers('carrier'))->assertOk()->assertJsonPath('data.availability.available',false);
        $this->getJson('/api/v1/customer/listings/'.$this->carry,$this->headers('sender'))->assertNotFound();
    }

    public function test_read_responses_match_existing_capacity_service_and_reveal_no_private_deal_data(): void
    {
        $this->deal('confirmed');
        $data=$this->show($this->carry,'sender')->assertHeader('Cache-Control','no-store, private')->json('data');
        $this->assertSame(ListingDeals::capacity(DB::table('post_ads')->find($this->carry)),$data['availability']);
        $this->assertSame(['total_kg','reserved_kg','committed_kg','remaining_kg','available'],array_keys($data['availability']));
        $this->assertSame(['eligible','reason'],array_keys($data['request_eligibility']));
        foreach (['deals','deal_id','sender_user_id','carrier_user_id','active_pair','phone','email','from_latitude','to_longitude','from_address'] as $field) {
            $this->assertStringNotContainsString('"'.$field.'":',json_encode($data));
        }
    }

    public function test_query_count_is_constant_for_public_and_owner_collections_and_home_is_batched(): void
    {
        for ($i=0;$i<20;$i++) $this->listing($this->carrier,['ad_type'=>'Carring','weight'=>12]);
        foreach ([['/api/v1/listings?ad_type=Carring&', 'sender'],['/api/v1/customer/listings?', 'carrier']] as [$url,$who]) {
            $counts=[];
            foreach ([1,20] as $size) {
                DB::connection()->enableQueryLog(); DB::connection()->flushQueryLog();
                $this->getJson($url.'per_page='.$size,$this->headers($who))->assertOk()->assertJsonCount($size,'data.items');
                $counts[]=count(DB::connection()->getQueryLog());
            }
            $this->assertSame($counts[0],$counts[1],'Query count must not grow with returned listings.');
        }
        DB::connection()->flushQueryLog();
        $this->getJson('/api/v1/home',$this->headers('sender'))->assertOk();
        $dealQueries=array_filter(DB::connection()->getQueryLog(),fn ($q)=>str_contains($q['query'],'from `listing_deals`'));
        $this->assertCount(1,$dealQueries);
    }

    public function test_missing_deal_infrastructure_preserves_browsing_but_reports_unknown_capacity(): void
    {
        Schema::shouldReceive('hasTable')->with('listing_deals')->andReturn(false);
        $this->show($this->carry)->assertJsonPath('data.weight',12)->assertJsonPath('data.availability.available',null)
            ->assertJsonPath('data.availability.remaining_kg',null)->assertJsonPath('data.request_eligibility.reason','infrastructure_unavailable');
    }

    public function test_website_session_without_bearer_is_guest_context_and_empty_collections_stay_empty(): void
    {
        $this->actingAs($this->sender,'web');
        $this->getJson('/api/v1/listings/'.$this->carry)->assertOk()
            ->assertJsonPath('data.request_eligibility',['eligible'=>null,'reason'=>'authentication_required']);
        $this->getJson('/api/v1/listings?search=NoSuchListing',$this->headers())->assertOk()->assertJsonPath('data.items',[]);
    }

    public function test_partially_committed_carrier_uses_remaining_capacity_for_unsplit_parcel_eligibility(): void
    {
        $otherParcel=$this->listing($this->other,['weight'=>8]);
        $this->deal('confirmed',['sender_listing_id'=>$otherParcel,'sender_user_id'=>$this->other->id,'agreed_kg'=>8]);
        $this->show($this->carry,'sender')->assertJsonPath('data.availability.available',true)
            ->assertJsonPath('data.availability.remaining_kg',4)->assertJsonPath('data.request_eligibility.eligible',false);
        $this->listing($this->sender,['weight'=>4]);
        $this->show($this->carry,'sender')->assertJsonPath('data.request_eligibility.eligible',true);
    }
}
