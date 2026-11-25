<?php

namespace Tests\Feature;

use App\Http\Controllers\PostAdsController;
use App\Models\User;
use App\Services\Api\ListingDeals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerDealTest extends TestCase
{
    use DatabaseTransactions;

    private User $sender;
    private User $carrier;
    private User $other;
    private int $send;
    private int $carry;
    private array $fields;
    private string $base='/api/v1/customer/deals';
    private array $tokens=[];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(Schema::hasTable('listing_deals'),'Apply the approved migration to the isolated database before testing.');
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-25 12:00:00','UTC'));
        Role::findOrCreate('user','web'); Role::findOrCreate('admin','web');
        foreach (['sender','carrier','other'] as $name) {
            $this->$name=User::factory()->create(['isActive'=>1,'isVerified'=>1]);
            $this->$name->assignRole('user');
            $this->tokens[$name]=$this->$name->createToken('mobile',['mobile:account'])->plainTextToken;
        }
        $cat=DB::table('categories')->insertGetId(['name'=>'Parcel','isActive'=>1]);
        $country=DB::table('countries')->insertGetId(['name'=>'Alpha','isActive'=>1,'currency_name'=>'USD']);
        $city=DB::table('cities')->insertGetId(['name'=>'City','country_id'=>$country,'isActive'=>1]);
        $this->fields=['category_id'=>$cat,'ad_type'=>'Send','title'=>'Parcel','description'=>'<p>Safe</p>',
            'weight'=>5,'weight_price'=>1,'currency'=>'USD','from_country_id'=>$country,'from_city_id'=>$city,
            'to_country_id'=>$country,'to_city_id'=>$city,'from_area'=>null,'to_area'=>null,
            'departure_on'=>'2030-01-01','reached_on'=>'2030-01-02','show_contact_info'=>0,'isActive'=>1,'image'=>'listing.png'];
        $this->send=$this->listing($this->sender);
        $this->carry=$this->listing($this->carrier,['ad_type'=>'Carring','weight'=>12]);
    }

    private function listing(User $owner,array $changes=[]): int
    {
        return DB::table('post_ads')->insertGetId(array_replace($this->fields,['created_by'=>$owner->id],$changes));
    }
    private function headers(string $who='sender'): array
    {
        app('auth')->forgetGuards();
        return ['Authorization'=>'Bearer '.$this->tokens[$who]];
    }
    private function submit(string $who='sender',array $changes=[])
    {
        return $this->postJson($this->base,array_replace(['sender_listing_id'=>$this->send,'carrier_listing_id'=>$this->carry,'requested_kg'=>5],$changes),$this->headers($who));
    }
    private function action(int $id,string $action,string $who='sender')
    {
        return $this->postJson($this->base.'/'.$id.'/'.$action,[],$this->headers($who));
    }
    private function accepted(): int
    {
        $id=$this->submit()->assertCreated()->json('data.id');
        $this->action($id,'accept','carrier')->assertOk();
        return $id;
    }

    public function test_lifecycle_commits_whole_parcel_and_reopens_carrier_without_changing_listing_weight(): void
    {
        $before=DB::table('post_ads')->whereIn('id',[$this->send,$this->carry])->get()->toJson();
        $id=$this->submit()->assertCreated()->assertJsonPath('data.allowed_actions',['cancel'])->json('data.id');
        $this->action($id,'accept')->assertForbidden();
        $hold=$this->action($id,'accept','carrier')->assertOk()->assertJsonPath('data.capacity.carrier.reserved_kg',5)
            ->assertJsonPath('data.capacity.carrier.available',false)->json('data.acceptance_expires_at');
        $this->travel(1)->hours();
        $this->action($id,'accept','carrier')->assertOk()->assertJsonPath('data.acceptance_expires_at',$hold);
        $this->action($id,'confirm','carrier')->assertForbidden();
        $this->action($id,'confirm')->assertOk()->assertJsonPath('data.capacity.carrier.committed_kg',5)
            ->assertJsonPath('data.capacity.carrier.remaining_kg',7)->assertJsonPath('data.capacity.carrier.available',true)
            ->assertJsonPath('data.capacity.sender.available',false);
        $this->action($id,'confirm')->assertOk()->assertJsonPath('data.capacity.carrier.committed_kg',5);
        $this->assertSame($before,DB::table('post_ads')->whereIn('id',[$this->send,$this->carry])->get()->toJson());
        Mail::assertNothingSent(); Notification::assertNothingSent();
    }

    public function test_carrier_can_initiate_and_only_sender_accepts_carrier_confirms(): void
    {
        $id=$this->submit('carrier')->assertCreated()->assertJsonPath('data.initiated_by',$this->carrier->id)->json('data.id');
        $this->action($id,'accept','carrier')->assertForbidden();
        $this->action($id,'accept')->assertOk();
        $this->action($id,'confirm')->assertForbidden();
        $this->action($id,'confirm','carrier')->assertOk();
    }

    public function test_duplicate_requests_and_resubmission_after_rejection_and_cancellation(): void
    {
        $id=$this->submit()->assertCreated()->json('data.id');
        $this->submit('carrier')->assertStatus(409);
        $this->action($id,'reject')->assertForbidden();
        $this->action($id,'reject','carrier')->assertOk();
        $this->action($id,'reject','carrier')->assertOk();
        $second=$this->submit()->assertCreated()->json('data.id');
        $this->assertNotSame($id,$second);
        $this->action($second,'cancel','carrier')->assertForbidden();
        $this->action($second,'cancel')->assertOk();
        $this->submit()->assertCreated();
        $this->assertSame(3,DB::table('listing_deals')->where('sender_listing_id',$this->send)->count());
    }

    public function test_hold_blocks_both_listings_and_release_at_exact_24_hours_is_read_only(): void
    {
        $id=$this->accepted();
        $second=$this->listing($this->other);
        $this->submit('other',['sender_listing_id'=>$second])->assertStatus(409);
        $this->travel(24)->hours();
        $before=DB::table('listing_deals')->find($id);
        $this->getJson($this->base.'/'.$id,$this->headers())->assertOk()->assertJsonPath('data.status','expired')
            ->assertJsonPath('data.capacity.carrier.reserved_kg',0)->assertJsonPath('data.allowed_actions',[]);
        $this->assertEquals($before,DB::table('listing_deals')->find($id));
        $this->action($id,'confirm')->assertStatus(409);
        $this->assertSame('expired',DB::table('listing_deals')->where('id',$id)->value('status'));
        $this->submit()->assertCreated();
    }

    public function test_capacity_multiple_deals_no_overbooking_and_cancellation_releases_committed_capacity(): void
    {
        $id=$this->accepted(); $this->action($id,'confirm')->assertOk();
        $second=$this->listing($this->other,['weight'=>7]);
        $two=$this->submit('other',['sender_listing_id'=>$second,'requested_kg'=>7])->assertCreated()->json('data.id');
        $this->action($two,'accept','carrier')->assertOk();
        $this->action($two,'confirm','other')->assertOk()->assertJsonPath('data.capacity.carrier.remaining_kg',0);
        $third=$this->listing($this->sender,['weight'=>1]);
        $this->submit('sender',['sender_listing_id'=>$third,'requested_kg'=>1])->assertStatus(409);
        $this->action($id,'cancel','carrier')->assertOk()->assertJsonPath('data.capacity.carrier.remaining_kg',5);
        $this->action($id,'cancel')->assertOk()->assertJsonPath('data.capacity.carrier.remaining_kg',5);
        $this->submit()->assertCreated();
    }

    public function test_two_pending_requests_only_one_can_hold_shared_listing(): void
    {
        $one=$this->submit()->assertCreated()->json('data.id');
        $other=$this->listing($this->other);
        $two=$this->submit('other',['sender_listing_id'=>$other])->assertCreated()->json('data.id');
        $this->action($one,'accept','carrier')->assertOk();
        $this->action($two,'accept','carrier')->assertStatus(409);
        $this->action($one,'cancel')->assertOk();
        $this->action($two,'accept','carrier')->assertOk();
    }

    public static function badWeights(): array { return [[0],[-1],[1.5],[6],[4],[null],['five']]; }
    #[DataProvider('badWeights')]
    public function test_sender_splitting_and_invalid_kg_are_denied(mixed $weight): void
    {
        $this->submit('sender',['requested_kg'=>$weight])->assertStatus(in_array($weight,[4,6],true)?409:422);
        $this->assertSame(0,DB::table('listing_deals')->where('sender_listing_id',$this->send)->count());
    }

    public function test_route_eligibility_and_target_ownership_are_checked(): void
    {
        $this->submit('other')->assertNotFound();
        DB::table('post_ads')->where('id',$this->carry)->update(['to_city_id'=>$this->fields['to_city_id']+1]);
        $this->submit()->assertNotFound();
        DB::table('post_ads')->where('id',$this->carry)->update(['to_city_id'=>$this->fields['to_city_id'],'isActive'=>0]);
        $this->submit()->assertNotFound();
        DB::table('post_ads')->where('id',$this->carry)->update(['isActive'=>1,'departure_on'=>'2020-01-01']);
        $this->submit()->assertNotFound();
        DB::table('post_ads')->where('id',$this->carry)->update(['departure_on'=>'2030-01-01']);
        $this->carrier->update(['isVerified'=>0]); $this->submit()->assertNotFound();
    }

    public function test_authentication_idor_validation_and_private_fields(): void
    {
        $this->postJson($this->base,[])->assertUnauthorized();
        $id=$this->submit()->assertCreated()->json('data.id');
        $this->getJson($this->base.'/'.$id,$this->headers('other'))->assertNotFound();
        foreach (['accept','reject','confirm','cancel'] as $action) $this->action($id,$action,'other')->assertNotFound();
        $this->submit('sender',['sender_user_id'=>$this->other->id])->assertUnprocessable();
        $this->getJson($this->base.'?per_page=1',$this->headers())->assertOk()->assertJsonPath('data.pagination.total',1);
        $this->getJson($this->base,$this->headers('other'))->assertOk()->assertJsonPath('data.pagination.total',0);
        $data=$this->getJson($this->base.'/'.$id,$this->headers())->assertOk()->getContent();
        foreach (['phone','email','address','latitude','longitude','password','listing_snapshot'] as $private) $this->assertStringNotContainsString('"'.$private.'"',$data);
        $this->sender->update(['isActive'=>0]); $this->getJson($this->base,$this->headers())->assertForbidden();
    }

    public function test_api_listing_guards_and_existing_delete_409(): void
    {
        $id=$this->accepted();
        $this->putJson('/api/v1/customer/listings/'.$this->carry,['weight'=>13],$this->headers('carrier'))->assertStatus(409);
        $this->putJson('/api/v1/customer/listings/'.$this->carry,['title'=>'Safe edit'],$this->headers('carrier'))->assertOk();
        $this->action($id,'confirm')->assertOk();
        $this->putJson('/api/v1/customer/listings/'.$this->carry,['weight'=>4],$this->headers('carrier'))->assertStatus(409);
        $this->putJson('/api/v1/customer/listings/'.$this->carry,['ad_type'=>'Send'],$this->headers('carrier'))->assertStatus(409);
        $this->putJson('/api/v1/customer/listings/'.$this->carry,['weight'=>14],$this->headers('carrier'))->assertOk();
        $this->deleteJson('/api/v1/customer/listings/'.$this->carry,[],$this->headers('carrier'))->assertStatus(409);
    }

    public function test_website_and_admin_edit_guards_preserve_redirect_and_normal_behavior(): void
    {
        $id=$this->accepted();
        foreach (['user','admin'] as $side) {
            $this->actingAs($this->carrier,'web');
            $before=DB::table('post_ads')->find($this->carry);
            try {
                (new PostAdsController)->editPostAds(Request::create('/'.$side.'/edit-listing/'.$this->carry,'POST',
                    array_replace($this->fields,['ad_type'=>'Carring','weight'=>13,'cur_image'=>'listing.png'])),$this->carry);
                $this->fail('Accepted deal must block material edits.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(409,$e->getStatusCode()); }
            $this->assertEquals($before,DB::table('post_ads')->find($this->carry));
        }
        $this->action($id,'cancel')->assertOk();
        $this->actingAs($this->carrier,'web');
        $response=(new PostAdsController)->editPostAds(Request::create('/user/edit-listing/'.$this->carry,'POST',
            array_replace($this->fields,['ad_type'=>'Carring','weight'=>13,'cur_image'=>'listing.png'])),$this->carry);
        $this->assertSame(url('/user/my-listing'),$response->getTargetUrl());
        $this->assertSame(13,(int)DB::table('post_ads')->where('id',$this->carry)->value('weight'));
    }

    public function test_hard_delete_guard_and_retained_history_after_terminal_deletion(): void
    {
        $id=$this->submit()->assertCreated()->json('data.id');
        try { (new PostAdsController)->deletePostAds($this->send); $this->fail('Pending listing deleted.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(409,$e->getStatusCode()); }
        $this->action($id,'cancel')->assertOk();
        (new PostAdsController)->deletePostAds($this->send);
        $this->assertNull(DB::table('post_ads')->find($this->send));
        $this->getJson($this->base.'/'.$id,$this->headers())->assertOk()->assertJsonPath('data.capacity.sender',null);
        $this->assertNotNull(DB::table('listing_deals')->where('id',$id)->value('listing_snapshot'));
    }

    public function test_missing_table_fails_deal_api_safely_but_legacy_guards_are_noop(): void
    {
        Schema::shouldReceive('hasTable')->with('listing_deals')->andReturn(false);
        $this->getJson($this->base,$this->headers())->assertStatus(503);
        ListingDeals::guardEdit(DB::table('post_ads')->find($this->carry),['weight'=>1]);
        ListingDeals::guardDelete($this->carry);
        $this->assertNotNull(DB::table('post_ads')->find($this->carry));
    }

    public function test_second_connection_cannot_acquire_listing_locks_during_acceptance(): void
    {
        $id=$this->submit()->assertCreated()->json('data.id');
        $config=DB::connection()->getConfig();
        $observer=new \PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'],
            $config['username'],$config['password'],[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);
        $checked=0;
        DB::listen(function ($query) use ($observer,&$checked) {
            if (!str_contains($query->sql,'`post_ads`') || !str_contains(strtolower($query->sql),'for update')) return;
            $observer->beginTransaction();
            try {
                $observer->query('SELECT id FROM post_ads WHERE id IN ('.$this->send.','.$this->carry.') ORDER BY id FOR UPDATE NOWAIT');
                $this->fail('Competing lock must fail.');
            } catch (\PDOException $e) { $this->assertSame(1205,(int)$e->errorInfo[1]); $checked++; }
            finally { $observer->rollBack(); }
        });
        $this->action($id,'accept','carrier')->assertOk();
        $this->assertGreaterThanOrEqual(1,$checked);
    }

    public function test_confirmation_failure_rolls_back_without_committing_capacity(): void
    {
        $id=$this->accepted(); $before=DB::table('listing_deals')->find($id);
        DB::connection()->beforeExecuting(function ($sql) {
            if (str_starts_with(strtolower($sql),'update `listing_deals`')) throw new \RuntimeException('Synthetic deal storage failure');
        });
        $this->action($id,'confirm')->assertStatus(500);
        $this->assertEquals($before,DB::table('listing_deals')->find($id));
        $capacity=ListingDeals::capacity(DB::table('post_ads')->find($this->carry));
        $this->assertSame(0,$capacity['committed_kg']); $this->assertSame(5,$capacity['reserved_kg']);
    }

    public function test_confirm_rechecks_expiry_and_cancellation_after_pickup_is_blocked(): void
    {
        $id=$this->accepted();
        DB::table('post_ads')->where('id',$this->carry)->update(['isActive'=>2]);
        $this->action($id,'confirm')->assertNotFound();
        $this->action($id,'cancel','carrier')->assertOk()->assertJsonPath('data.capacity.carrier.reserved_kg',0);
        DB::table('post_ads')->where('id',$this->carry)->update(['isActive'=>1]);
        $id=$this->accepted(); $this->action($id,'confirm')->assertOk();
        // Future lifecycle state, not an implemented pickup endpoint.
        DB::table('listing_deals')->where('id',$id)->update(['picked_up_at'=>now()]);
        $this->action($id,'cancel')->assertStatus(409);
        $this->getJson($this->base.'/'.$id,$this->headers())->assertOk()->assertJsonPath('data.allowed_actions',[]);
    }

    public function test_pending_requests_cannot_be_accepted_after_route_change_and_cannot_spoof_fields(): void
    {
        $id=$this->submit()->assertCreated()->json('data.id');
        $newCity=DB::table('cities')->insertGetId(['name'=>'Other','country_id'=>$this->fields['from_country_id'],'isActive'=>1]);
        DB::table('post_ads')->where('id',$this->send)->update(['to_city_id'=>$newCity]);
        $this->action($id,'accept','carrier')->assertStatus(409);
        $this->getJson($this->base.'/'.$id,$this->headers('carrier'))->assertOk()->assertJsonPath('data.allowed_actions',['reject']);
        $this->postJson($this->base.'/'.$id.'/accept',['agreed_kg'=>1],$this->headers('carrier'))->assertUnprocessable();
        $this->assertSame('pending',DB::table('listing_deals')->where('id',$id)->value('status'));
    }

    public function test_website_confirmed_guards_and_admin_redirect_without_deal_restrictions(): void
    {
        $id=$this->accepted(); $this->action($id,'confirm')->assertOk();
        $this->actingAs($this->carrier,'web');
        foreach ([['weight'=>4],['ad_type'=>'Send']] as $change) {
            try {
                (new PostAdsController)->editPostAds(Request::create('/admin/edit-listing/'.$this->carry,'POST',
                    array_replace($this->fields,['ad_type'=>'Carring','weight'=>12,'cur_image'=>'listing.png'],$change)),$this->carry);
                $this->fail('Confirmed obligation must be preserved.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(409,$e->getStatusCode()); }
        }
        try { (new PostAdsController)->deletePostAds($this->carry); $this->fail('Confirmed listing deleted.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(409,$e->getStatusCode()); }
        $this->action($id,'cancel')->assertOk();
        // Existing shared controller identifies its admin redirect by ID 1.
        // Use an in-memory principal; do not replace any baseline account.
        $admin=new User; $admin->id=1; $this->actingAs($admin,'web');
        $reply=(new PostAdsController)->editPostAds(Request::create('/admin/edit-listing/'.$this->carry,'POST',
            array_replace($this->fields,['ad_type'=>'Carring','weight'=>12,'cur_image'=>'listing.png'])),$this->carry);
        $this->assertSame(url('/admin/view-listing'),$reply->getTargetUrl());
    }

    public function test_cancelled_pair_unique_slot_is_reusable_and_active_slot_is_database_enforced(): void
    {
        $id=$this->submit()->assertCreated()->json('data.id');
        $row=(array)DB::table('listing_deals')->find($id); unset($row['id']);
        DB::beginTransaction();
        try { DB::table('listing_deals')->insert($row); $this->fail('Active pair must be unique.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertSame('23000',(string)$e->errorInfo[0]); }
        finally { DB::rollBack(); }
        $this->action($id,'cancel')->assertOk();
        $this->assertNull(DB::table('listing_deals')->where('id',$id)->value('active_pair'));
        $this->submit()->assertCreated();
    }

    public function test_all_deal_routes_reject_guests_and_ineligible_roles_and_preserve_route_inventory(): void
    {
        $routes=[];
        foreach (app('router')->getRoutes() as $route) if (str_starts_with($route->uri(),'api/v1/')) $routes[]=$route;
        $this->assertCount(51,$routes);
        foreach (['GET'=>'','POST'=>''] as $method=>$suffix) $this->json($method,$this->base.$suffix)->assertUnauthorized();
        foreach (['accept','reject','confirm','cancel','pickup','complete'] as $action) $this->postJson($this->base.'/1/'.$action)->assertUnauthorized();
        $this->getJson($this->base.'/1')->assertUnauthorized();
        foreach ([['admin'],['user','admin'],[]] as $roles) {
            $this->sender->syncRoles($roles);
            $this->getJson($this->base,$this->headers())->assertForbidden();
        }
        $this->sender->syncRoles(['user']); $this->sender->update(['isVerified'=>0]);
        $this->getJson($this->base,$this->headers())->assertForbidden();
    }

    public function test_carrier_pickup_completion_is_idempotent_and_capacity_stays_committed(): void
    {
        $id=$this->accepted(); $this->action($id,'confirm')->assertOk();
        $before=DB::table('post_ads')->whereIn('id',[$this->send,$this->carry])->get()->toJson();
        $this->getJson($this->base.'/'.$id,$this->headers('carrier'))->assertOk()->assertJsonPath('data.allowed_actions',['cancel','pickup']);
        $picked=$this->action($id,'pickup','carrier')->assertOk()->assertJsonPath('data.status','confirmed')
            ->assertJsonPath('data.allowed_actions',['complete'])->json('data.picked_up_at');
        $this->travel(1)->minutes();
        $this->action($id,'pickup','carrier')->assertOk()->assertJsonPath('data.picked_up_at',$picked);
        foreach (['sender','carrier'] as $who) $this->action($id,'cancel',$who)->assertStatus(409);
        $done=$this->action($id,'complete','carrier')->assertOk()->assertJsonPath('data.status','completed')
            ->assertJsonPath('data.capacity.carrier.committed_kg',5)->assertJsonPath('data.capacity.carrier.remaining_kg',7)
            ->assertJsonPath('data.capacity.sender.remaining_kg',0)->assertJsonPath('data.allowed_actions',[])->json('data.completed_at');
        $this->travel(1)->minutes();
        $this->action($id,'complete','carrier')->assertOk()->assertJsonPath('data.completed_at',$done);
        $this->action($id,'pickup','carrier')->assertOk()->assertJsonPath('data.picked_up_at',$picked);
        $this->getJson($this->base.'/'.$id,$this->headers())->assertOk()->assertJsonPath('data.status','completed');
        $this->assertNull(DB::table('listing_deals')->where('id',$id)->value('active_pair'));
        $this->assertSame($before,DB::table('post_ads')->whereIn('id',[$this->send,$this->carry])->get()->toJson());
        Mail::assertNothingSent(); Notification::assertNothingSent();
    }

    public function test_delivery_requires_pickup_confirmed_state_and_carrier_identity(): void
    {
        $id=$this->submit()->assertCreated()->json('data.id');
        foreach (['pickup','complete'] as $action) {
            $this->action($id,$action,'carrier')->assertStatus(409);
            $this->action($id,$action)->assertForbidden();
            $this->action($id,$action,'other')->assertNotFound();
            $this->action(2147483647,$action,'carrier')->assertNotFound();
        }
        $this->action($id,'accept','carrier')->assertOk();
        $this->action($id,'pickup','carrier')->assertStatus(409);
        $this->action($id,'confirm')->assertOk();
        $this->action($id,'complete','carrier')->assertStatus(409);
        $this->action($id,'pickup')->assertForbidden();
        $this->postJson($this->base.'/'.$id.'/pickup',['carrier_user_id'=>$this->sender->id],$this->headers('carrier'))->assertUnprocessable();
        $this->action($id,'pickup','carrier')->assertOk();
        $this->action($id,'complete')->assertForbidden();
        $this->action($id,'complete','carrier')->assertOk();
        $this->action($id,'complete')->assertForbidden();
    }

    public function test_cancelled_deal_cannot_be_picked_up_or_completed(): void
    {
        $id=$this->accepted(); $this->action($id,'confirm')->assertOk(); $this->action($id,'cancel')->assertOk();
        $before=DB::table('listing_deals')->find($id);
        $this->action($id,'pickup','carrier')->assertStatus(409);
        $this->action($id,'complete','carrier')->assertStatus(409);
        $this->assertEquals($before,DB::table('listing_deals')->find($id));
    }

    public function test_history_filters_snapshot_survival_pagination_and_privacy(): void
    {
        $id=$this->accepted(); $this->action($id,'confirm')->assertOk();
        $this->action($id,'pickup','carrier')->assertOk(); $this->action($id,'complete','carrier')->assertOk();
        DB::table('post_ads')->where('id',$this->send)->update(['title'=>'Changed','isActive'=>2]);
        DB::table('post_ads')->where('id',$this->carry)->delete();
        $before=DB::table('listing_deals')->find($id);
        foreach (['sender','carrier'] as $who) {
            $body=$this->getJson($this->base.'?status=completed&per_page=1',$this->headers($who))->assertOk()
                ->assertJsonPath('data.pagination.total',1)->assertJsonPath('data.items.0.id',$id)
                ->assertJsonPath('data.items.0.history.sender.listing.title','Parcel')
                ->assertJsonPath('data.items.0.history.carrier.listing.ad_type','Carring')
                ->assertJsonPath('data.items.0.history.sender.user.id',$this->sender->id)
                ->assertJsonPath('data.items.0.capacity.carrier',null)->getContent();
            foreach (['phone','email','address','latitude','longitude','password'] as $field) $this->assertStringNotContainsString('"'.$field.'"',$body);
        }
        $this->getJson($this->base.'?status=completed',$this->headers('other'))->assertOk()->assertJsonPath('data.pagination.total',0);
        $this->getJson($this->base.'?status=pending',$this->headers())->assertOk()->assertJsonPath('data.pagination.total',0);
        $this->getJson($this->base.'?status=unknown',$this->headers())->assertUnprocessable();
        $this->getJson($this->base.'?per_page=51',$this->headers())->assertUnprocessable();
        $this->getJson($this->base.'?status=completed&page=2&per_page=1',$this->headers())->assertOk()->assertJsonPath('data.items',[]);
        $this->assertEquals($before,DB::table('listing_deals')->find($id));
    }

    public function test_delivery_survives_departure_expiry_and_missing_parent_listing(): void
    {
        $id=$this->accepted(); $this->action($id,'confirm')->assertOk();
        DB::table('post_ads')->where('id',$this->carry)->update(['isActive'=>2,'departure_on'=>'2020-01-01']);
        $this->action($id,'pickup','carrier')->assertOk();
        DB::table('post_ads')->where('id',$this->send)->delete();
        $this->action($id,'complete','carrier')->assertOk()->assertJsonPath('data.status','completed');
        $this->getJson($this->base.'/'.$id,$this->headers())->assertOk()->assertJsonPath('data.history.sender.listing.weight_kg',5);
    }

    public function test_completion_storage_failure_rolls_back_and_does_not_release_capacity(): void
    {
        $id=$this->accepted(); $this->action($id,'confirm')->assertOk(); $this->action($id,'pickup','carrier')->assertOk();
        $before=DB::table('listing_deals')->find($id);
        DB::connection()->beforeExecuting(function ($sql) {
            if (str_starts_with(strtolower($sql),'update `listing_deals`') && str_contains($sql,'`completed_at`')) throw new \RuntimeException('Synthetic completion failure');
        });
        $this->action($id,'complete','carrier')->assertStatus(500);
        $this->assertEquals($before,DB::table('listing_deals')->find($id));
        $this->assertSame(5,ListingDeals::capacity(DB::table('post_ads')->find($this->carry))['committed_kg']);
    }

    public function test_delivery_operations_hold_listing_and_deal_locks_against_competing_transitions(): void
    {
        $id=$this->accepted(); $this->action($id,'confirm')->assertOk();
        $config=DB::connection()->getConfig();
        $observer=new \PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'],
            $config['username'],$config['password'],[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);
        $checked=[];
        DB::listen(function ($query) use ($observer,&$checked,$id) {
            if (!str_contains(strtolower($query->sql),'for update')) return;
            $table=str_contains($query->sql,'`post_ads`')?'post_ads':(str_contains($query->sql,'`listing_deals`')?'listing_deals':null);
            if (!$table) return;
            $observer->beginTransaction();
            try {
                $ids=$table==='post_ads'?$this->send.','.$this->carry:(string)$id;
                $observer->query('SELECT id FROM '.$table.' WHERE id IN ('.$ids.') ORDER BY id FOR UPDATE NOWAIT');
                $this->fail('Concurrent transition must not bypass row locks.');
            } catch (\PDOException $e) { $this->assertSame(1205,(int)$e->errorInfo[1]); $checked[]=$table; }
            finally { $observer->rollBack(); }
        });
        $this->action($id,'pickup','carrier')->assertOk();
        $this->action($id,'complete','carrier')->assertOk();
        $this->assertContains('post_ads',$checked); $this->assertContains('listing_deals',$checked);
    }

    public function test_pending_history_filter_is_stable_and_does_not_rewrite_legacy_snapshots_or_expired_holds(): void
    {
        $first=$this->submit()->assertCreated()->json('data.id');
        $record=(array)DB::table('listing_deals')->find($first); unset($record['id']);
        $expired=DB::table('listing_deals')->insertGetId(array_replace($record,['active_pair'=>null,'status'=>'accepted',
            'acceptance_expires_at'=>now()->subSecond(),'listing_snapshot'=>'{}']));
        $legacy=DB::table('listing_deals')->insertGetId(array_replace($record,['active_pair'=>null,'status'=>'completed','listing_snapshot'=>'{}']));
        $this->getJson($this->base.'?status=pending&per_page=1',$this->headers())->assertOk()
            ->assertJsonPath('data.pagination.total',1)->assertJsonPath('data.items.0.id',$first);
        $this->getJson($this->base.'?per_page=1',$this->headers())->assertOk()->assertJsonPath('data.items.0.id',$legacy)
            ->assertJsonPath('data.items.0.history.sender.listing.title',null);
        $this->getJson($this->base.'?per_page=1&page=2',$this->headers())->assertOk()->assertJsonPath('data.items.0.id',$expired)
            ->assertJsonPath('data.items.0.status','expired');
        $this->assertSame('{}',DB::table('listing_deals')->where('id',$legacy)->value('listing_snapshot'));
        $this->assertSame('accepted',DB::table('listing_deals')->where('id',$expired)->value('status'));
    }

    public function test_new_deal_snapshots_route_names_and_history_keeps_them_after_reference_changes(): void
    {
        $id=$this->submit()->assertCreated()->assertJsonPath('data.history.sender.listing.route.from_country_name','Alpha')
            ->assertJsonPath('data.history.carrier.listing.route.to_city_name','City')->json('data.id');
        $snapshot=DB::table('listing_deals')->where('id',$id)->value('listing_snapshot');
        $this->assertSame('Alpha',json_decode($snapshot,true)['sender']['from_country_name']);
        $this->assertSame('City',json_decode($snapshot,true)['carrier']['to_city_name']);
        DB::table('countries')->where('id',$this->fields['from_country_id'])->update(['name'=>'Renamed Country']);
        DB::table('cities')->where('id',$this->fields['from_city_id'])->update(['name'=>'Renamed City']);
        DB::table('post_ads')->whereIn('id',[$this->send,$this->carry])->update(['isActive'=>2]);
        foreach (['sender','carrier'] as $who) {
            $this->getJson($this->base.'/'.$id,$this->headers($who))->assertOk()
                ->assertJsonPath('data.history.sender.listing.route.from_country_name','Alpha')
                ->assertJsonPath('data.history.carrier.listing.route.to_city_name','City')
                ->assertJsonPath('data.history.sender.listing.route.from_country_id',$this->fields['from_country_id']);
            $this->getJson($this->base,$this->headers($who))->assertOk()->assertJsonPath('data.items.0.history.sender.listing.route.to_city_name','City');
        }
        $this->assertSame($snapshot,DB::table('listing_deals')->where('id',$id)->value('listing_snapshot'));
    }

    public function test_legacy_route_names_use_snapshot_ids_not_edited_listings_and_missing_names_are_null(): void
    {
        $id=$this->submit()->assertCreated()->json('data.id');
        $snapshot=json_decode(DB::table('listing_deals')->where('id',$id)->value('listing_snapshot'),true);
        foreach (['sender','carrier'] as $side) foreach (['from_country_name','from_city_name','to_country_name','to_city_name'] as $key) unset($snapshot[$side][$key]);
        $snapshot['sender']['to_city_id']=2147483647;
        $snapshot['carrier']['to_country_id']=2147483647;
        $json=json_encode($snapshot,JSON_THROW_ON_ERROR);
        DB::table('listing_deals')->where('id',$id)->update(['listing_snapshot'=>$json,'status'=>'completed']);
        DB::table('countries')->where('id',$this->fields['from_country_id'])->update(['isActive'=>0]);
        DB::table('post_ads')->whereIn('id',[$this->send,$this->carry])->delete();
        DB::connection()->enableQueryLog(); DB::connection()->flushQueryLog();
        $body=$this->getJson($this->base.'/'.$id,$this->headers())->assertOk()
            ->assertJsonPath('data.history.sender.listing.route.from_country_name','Alpha')
            ->assertJsonPath('data.history.sender.listing.route.from_city_name','City')
            ->assertJsonPath('data.history.sender.listing.route.to_city_name',null)
            ->assertJsonPath('data.history.carrier.listing.route.to_country_name',null)
            ->assertJsonPath('data.history.sender.listing.route.to_city_id',2147483647)->getContent();
        foreach (DB::connection()->getQueryLog() as $query) $this->assertMatchesRegularExpression('/^select\\b/i',$query['query']);
        foreach (['phone','email','latitude','longitude','address','listing_snapshot'] as $field) $this->assertStringNotContainsString('"'.$field.'"',$body);
        $this->assertSame($json,DB::table('listing_deals')->where('id',$id)->value('listing_snapshot'));
        $this->getJson($this->base.'?status=completed',$this->headers())->assertOk()->assertJsonPath('data.items.0.history.sender.listing.route.from_city_name','City');
        $this->getJson($this->base.'/'.$id,$this->headers('other'))->assertNotFound();
    }

    public function test_history_route_lookup_is_batched_for_a_whole_page(): void
    {
        $id=$this->submit()->assertCreated()->json('data.id');
        $row=(array)DB::table('listing_deals')->find($id); unset($row['id']);
        $snapshot=json_decode($row['listing_snapshot'],true);
        foreach (['sender','carrier'] as $side) foreach (['from_country_name','from_city_name','to_country_name','to_city_name'] as $key) unset($snapshot[$side][$key]);
        $row['listing_snapshot']=json_encode($snapshot,JSON_THROW_ON_ERROR); $row['active_pair']=null; $row['status']='completed';
        for ($i=0;$i<5;$i++) DB::table('listing_deals')->insert($row);
        foreach ([1,5] as $perPage) {
            DB::connection()->enableQueryLog(); DB::connection()->flushQueryLog();
            $this->getJson($this->base.'?status=completed&per_page='.$perPage,$this->headers())->assertOk()
                ->assertJsonCount($perPage,'data.items')->assertJsonPath('data.items.0.history.sender.listing.route.from_country_name','Alpha');
            $lookups=array_filter(DB::connection()->getQueryLog(),fn ($q)=>preg_match('/^select `name`, `id` from `(countries|cities)`/i',$q['query']));
            $this->assertCount(2,$lookups,'Route name queries must stay constant as the page grows.');
        }
    }

    public function test_snapshot_null_names_are_not_replaced_with_later_labels_and_unrecorded_routes_stay_null(): void
    {
        $id=$this->submit()->assertCreated()->json('data.id');
        $snapshot=json_decode(DB::table('listing_deals')->where('id',$id)->value('listing_snapshot'),true);
        $snapshot['sender']['from_country_name']=null;
        $snapshot['carrier']=[];
        DB::table('listing_deals')->where('id',$id)->update(['listing_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR)]);
        $this->getJson($this->base.'/'.$id,$this->headers())->assertOk()
            ->assertJsonPath('data.history.sender.listing.route.from_country_name',null)
            ->assertJsonPath('data.history.sender.listing.route.from_country_id',$this->fields['from_country_id'])
            ->assertJsonPath('data.history.carrier.listing.route.to_city_id',null)
            ->assertJsonPath('data.history.carrier.listing.route.to_city_name',null);
    }
}
