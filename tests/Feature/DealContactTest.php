<?php

namespace Tests\Feature;

use App\Http\Controllers\PostAdMessageController;
use App\Http\Controllers\UserController;
use App\Models\User;
use App\Services\DealContactAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DealContactTest extends TestCase
{
    use DatabaseTransactions;
    private User $sender;
    private User $carrier;
    private User $other;
    private array $tokens=[];
    private int $send;
    private int $carry;
    private int $deal;

    protected function setUp(): void
    {
        parent::setUp(); $this->assertTrue(DealContactAccess::ready());
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-25 12:00:00','UTC'));
        Role::findOrCreate('user','web'); Role::findOrCreate('admin','web');
        foreach (['sender','carrier','other'] as $i=>$name) {
            $this->$name=User::factory()->create(['isActive'=>1,'isVerified'=>1,'phone'=>'+1000000000'.$i]);
            $this->$name->assignRole('user'); $this->tokens[$name]=$this->$name->createToken('mobile',['mobile:account'])->plainTextToken;
        }
        $cat=DB::table('categories')->insertGetId(['name'=>'Parcels','isActive'=>1]);
        $country=DB::table('countries')->insertGetId(['name'=>'Alpha','isActive'=>1,'currency_name'=>'USD']);
        $city=DB::table('cities')->insertGetId(['name'=>'City','country_id'=>$country,'isActive'=>1]);
        $fields=['category_id'=>$cat,'ad_type'=>'Send','weight'=>5,'title'=>'Parcel','description'=>'Safe',
            'from_country_id'=>$country,'to_country_id'=>$country,'from_city_id'=>$city,'to_city_id'=>$city,
            'from_latitude'=>0,'from_longitude'=>0,'to_latitude'=>12.1234567,'to_longitude'=>23.1234567,
            'departure_on'=>'2030-01-01','reached_on'=>'2030-01-02','show_contact_info'=>1,'isActive'=>1];
        $this->send=DB::table('post_ads')->insertGetId($fields+['created_by'=>$this->sender->id]);
        $this->carry=DB::table('post_ads')->insertGetId(array_replace($fields,['ad_type'=>'Carring','weight'=>20,
            'created_by'=>$this->carrier->id,'from_latitude'=>24.8607343,'from_longitude'=>67.0011364]));
        $this->deal=$this->makeDeal();
    }

    private function makeDeal(array $changes=[]): int
    {
        return DB::table('listing_deals')->insertGetId(array_replace(['sender_listing_id'=>$this->send,'carrier_listing_id'=>$this->carry,
            'sender_user_id'=>$this->sender->id,'carrier_user_id'=>$this->carrier->id,'initiated_by'=>$this->sender->id,
            'requested_kg'=>5,'agreed_kg'=>5,'status'=>'accepted','accepted_at'=>now(),'acceptance_expires_at'=>now()->addHours(24),
            'listing_snapshot'=>'{}','created_at'=>now(),'updated_at'=>now()],$changes));
    }
    private function headers(string $who='sender'): array
    {
        app('auth')->forgetGuards(); return ['Authorization'=>'Bearer '.$this->tokens[$who]];
    }
    private function open(string $who='sender',?int $deal=null)
    {
        return $this->postJson('/api/v1/customer/conversations',['deal_id'=>$deal??$this->deal,'message'=>'Hello'],$this->headers($who));
    }
    private function contact(string $who='sender',?int $deal=null)
    {
        return $this->getJson('/api/v1/customer/deals/'.($deal??$this->deal).'/contact',$this->headers($who));
    }
    private function dealStatus(string $state): void
    {
        DB::table('listing_deals')->where('id',$this->deal)->update(['status'=>$state]);
    }

    public function test_actual_delivery_completion_revokes_contact_and_message_writes_but_preserves_history(): void
    {
        $room=$this->open()->assertCreated()->json('data.id');
        $base='/api/v1/customer/deals/'.$this->deal;
        $this->postJson($base.'/confirm',[],$this->headers())->assertOk();
        $this->postJson($base.'/pickup',[],$this->headers('carrier'))->assertOk();
        $this->contact()->assertOk();
        $this->postJson($base.'/complete',[],$this->headers('carrier'))->assertOk();
        foreach (['sender','carrier'] as $who) {
            $this->contact($who)->assertForbidden();
            $this->open($who)->assertForbidden();
            $this->postJson('/api/v1/customer/conversations/'.$room.'/messages',['message'=>'Denied'],$this->headers($who))->assertForbidden();
            $this->getJson('/api/v1/customer/conversations/'.$room.'/messages',$this->headers($who))->assertOk();
            $this->getJson('/api/v1/customer/deals?status=completed',$this->headers($who))->assertOk()->assertJsonPath('data.items.0.status','completed');
        }
        $this->assertSame(1,DB::table('post_ad_room_messages')->where('post_ad_room_id',$room)->count());
    }

    public function test_private_contact_and_pins_are_counterpart_only_and_get_does_not_write(): void
    {
        $before=DB::table('listing_deals')->find($this->deal);
        $tokens=DB::table('personal_access_tokens')->orderBy('id')->get()->toJson();
        DB::connection()->enableQueryLog(); DB::connection()->flushQueryLog();
        $r=$this->contact()->assertOk()->assertHeader('Cache-Control','no-store, private')
            ->assertExactJson(['success'=>true,'message'=>'Deal contact retrieved.','data'=>['deal_id'=>$this->deal,
                'counterpart'=>['id'=>$this->carrier->id,'phone'=>$this->carrier->phone],
                'listing'=>['id'=>$this->carry,'from_latitude'=>24.8607343,'from_longitude'=>67.0011364,
                    'to_latitude'=>12.1234567,'to_longitude'=>23.1234567]]]);
        $this->assertStringNotContainsString('email',$r->getContent());
        foreach (DB::connection()->getQueryLog() as $q) $this->assertMatchesRegularExpression('/^select\b/i',$q['query']);
        $this->contact('carrier')->assertOk()->assertJsonPath('data.counterpart.phone',$this->sender->phone)
            ->assertJsonPath('data.listing.id',$this->send)->assertJsonPath('data.listing.from_latitude',0);
        $this->assertEquals($before,DB::table('listing_deals')->find($this->deal));
        $this->assertSame($tokens,DB::table('personal_access_tokens')->orderBy('id')->get()->toJson());
    }

    public static function closedStates(): array { return [['pending'],['rejected'],['cancelled'],['expired'],['completed']]; }
    #[DataProvider('closedStates')]
    public function test_closed_deals_cannot_disclose_create_or_send_but_bound_history_is_read_only(string $status): void
    {
        $room=$this->open()->assertCreated()->json('data.id'); $this->dealStatus($status);
        $before=DB::table('post_ad_room_messages')->get()->toJson();
        $this->contact()->assertForbidden();$this->open()->assertForbidden();
        $this->postJson('/api/v1/customer/conversations/'.$room.'/messages',['message'=>'Blocked'],$this->headers())->assertForbidden();
        $this->getJson('/api/v1/customer/conversations/'.$room.'/messages',$this->headers())->assertOk()->assertJsonCount(1,'data.items');
        $this->assertSame($before,DB::table('post_ad_room_messages')->get()->toJson());
    }

    public function test_acceptance_deadline_revokes_access_without_lazy_expiry_mutation(): void
    {
        $room=$this->open()->assertCreated()->json('data.id');$this->travel(24)->hours();
        $before=DB::table('listing_deals')->find($this->deal);
        $this->contact()->assertForbidden();$this->open()->assertForbidden();
        $this->postJson('/api/v1/customer/conversations/'.$room.'/messages',['message'=>'Late'],$this->headers())->assertForbidden();
        $this->assertEquals($before,DB::table('listing_deals')->find($this->deal));
    }

    public function test_actual_confirm_cancel_transitions_and_confirmed_contact_after_departure(): void
    {
        $room=$this->open()->assertCreated()->json('data.id');
        $this->postJson('/api/v1/customer/deals/'.$this->deal.'/confirm',[],$this->headers())->assertOk();
        DB::table('post_ads')->whereIn('id',[$this->send,$this->carry])->update(['departure_on'=>'2020-01-01','isActive'=>2]);
        $this->contact()->assertOk();
        $this->postJson('/api/v1/customer/conversations/'.$room.'/messages',['message'=>'During journey'],$this->headers())->assertCreated();
        $this->postJson('/api/v1/customer/deals/'.$this->deal.'/cancel',[],$this->headers())->assertOk();
        $this->contact()->assertForbidden();
        $this->postJson('/api/v1/customer/conversations/'.$room.'/messages',['message'=>'After cancel'],$this->headers())->assertForbidden();
    }

    public function test_each_new_deal_gets_new_room_and_never_reactivates_historical_room(): void
    {
        $old=$this->open()->assertCreated()->json('data.id');$this->dealStatus('cancelled');
        $newDeal=$this->makeDeal();$new=$this->open('carrier',$newDeal)->assertCreated()->json('data.id');
        $this->assertNotSame($old,$new);
        $this->open('sender',$newDeal)->assertOk()->assertJsonPath('data.id',$new);
        $this->assertSame(1,DB::table('post_ad_room_messages')->where('post_ad_room_id',$new)->count());
        $this->postJson('/api/v1/customer/conversations/'.$old.'/messages',['message'=>'Old room'],$this->headers())->assertForbidden();
        $this->postJson('/api/v1/customer/conversations/'.$new.'/messages',['message'=>'New room'],$this->headers())->assertCreated();
        $this->assertSame($new,(int)DB::table('listing_deals')->where('id',$newDeal)->value('conversation_id'));
    }

    public function test_nonparticipants_missing_ids_and_impersonation_do_not_mutate(): void
    {
        $this->contact('other')->assertNotFound();$this->contact('sender',999999)->assertNotFound();$this->open('other')->assertNotFound();
        $room=$this->open()->assertCreated()->json('data.id');
        foreach (['','/messages'] as $suffix) $this->getJson('/api/v1/customer/conversations/'.$room.$suffix,$this->headers('other'))->assertNotFound();
        $this->postJson('/api/v1/customer/conversations/'.$room.'/messages',['message'=>'X','deal_id'=>$this->deal],$this->headers())->assertUnprocessable();
        $this->postJson('/api/v1/customer/conversations',['deal_id'=>$this->deal,'message'=>'X','from_user_id'=>$this->other->id],$this->headers())->assertUnprocessable();
        $this->assertSame(1,DB::table('post_ad_room_messages')->count());
    }

    public function test_corrupt_relationships_and_ineligible_counterpart_fail_closed(): void
    {
        $this->carrier->update(['isActive'=>0]);$this->contact()->assertForbidden();$this->open()->assertForbidden();
        $this->carrier->update(['isActive'=>1]);$this->carrier->syncRoles(['user','admin']);$this->contact()->assertForbidden();
        $this->carrier->syncRoles(['user']);
        DB::table('post_ads')->where('id',$this->carry)->update(['created_by'=>$this->other->id]);$this->contact()->assertForbidden();
        DB::table('post_ads')->where('id',$this->carry)->update(['created_by'=>$this->carrier->id,'ad_type'=>'Send']);$this->open()->assertForbidden();
    }

    public function test_legacy_rooms_are_not_adopted_and_are_read_only_even_with_a_new_accepted_deal(): void
    {
        $legacy=DB::table('post_ad_rooms')->insertGetId(['post_ads_id'=>$this->carry,'from_user_id'=>$this->sender->id,'to_user_id'=>$this->carrier->id]);
        $this->getJson('/api/v1/customer/conversations/'.$legacy,$this->headers())->assertOk()->assertJsonPath('data.deal_id',null);
        $this->postJson('/api/v1/customer/conversations/'.$legacy.'/messages',['message'=>'Bypass'],$this->headers())->assertForbidden();
        $new=$this->open()->assertCreated()->json('data.id');$this->assertNotSame($legacy,$new);
    }

    public function test_completed_history_survives_listing_deletion_and_owner_pin_access_is_unchanged(): void
    {
        $room=$this->open()->assertCreated()->json('data.id');$this->dealStatus('completed');
        $this->getJson('/api/v1/customer/listings/'.$this->send,$this->headers())->assertOk()->assertJsonPath('data.from_latitude',0);
        DB::table('post_ads')->whereIn('id',[$this->send,$this->carry])->delete();
        $this->getJson('/api/v1/customer/conversations/'.$room.'/messages',$this->headers())->assertOk()->assertJsonCount(1,'data.items');
        $this->contact()->assertForbidden();
    }

    public function test_public_and_unrelated_deal_responses_never_include_contact_or_exact_pins(): void
    {
        foreach (['/api/v1/home','/api/v1/listings','/api/v1/listings/'.$this->carry,'/api/v1/customer/deals/'.$this->deal] as $url) {
            $r=$this->getJson($url,$this->headers())->assertOk();
            foreach (['from_latitude','from_longitude','to_latitude','to_longitude','phone','email'] as $field) $r->assertDontSee('"'.$field.'":',false);
        }
        DB::table('post_ads')->where('id',$this->carry)->update(['from_latitude'=>null]);
        $this->contact()->assertOk()->assertJsonPath('data.listing.from_latitude',null)->assertJsonPath('data.listing.from_longitude',null);
    }

    public function test_website_create_send_and_spoofing_use_same_lifecycle_gate_and_preserve_redirect(): void
    {
        $this->actingAs($this->sender,'web');$controller=new PostAdMessageController;
        $request=Request::create('/user/create/room','POST',['post_ads_id'=>$this->carry,'message'=>'Web hello',
            'from_user_id'=>$this->sender->id,'to_user_id'=>$this->carrier->id]);
        $reply=$controller->createMessageRoom($request);$room=(int)DB::table('listing_deals')->where('id',$this->deal)->value('conversation_id');
        $this->assertStringContainsString('/'.$room.'='.md5((string)$room),$reply->getTargetUrl());
        $controller->createMessageRoom($request);$this->assertSame(1,DB::table('post_ad_room_messages')->count());Mail::assertSentCount(1);
        $body=['post_ad_room_id'=>$room,'message'=>'Web reply','from_user_id'=>$this->sender->id,'to_user_id'=>$this->carrier->id];
        $this->assertSame('send',$controller->userMessageSend(Request::create('/user/msg/send','POST',$body)));
        try {$controller->userMessageSend(Request::create('/user/msg/send','POST',array_replace($body,['from_user_id'=>$this->other->id])));$this->fail('Spoof accepted');}
        catch(HttpException $e){$this->assertSame(403,$e->getStatusCode());}
        $this->dealStatus('cancelled');
        try {$controller->userMessageSend(Request::create('/user/msg/send','POST',$body));$this->fail('Cancelled send accepted');}
        catch(HttpException $e){$this->assertSame(403,$e->getStatusCode());}
        $this->assertSame(2,DB::table('post_ad_room_messages')->count());
    }

    public function test_website_room_polling_and_read_routes_reject_idor_before_read_state_changes(): void
    {
        $room=$this->open()->assertCreated()->json('data.id');$this->actingAs($this->other,'web');
        $before=DB::table('post_ad_room_messages')->get()->toJson();$controller=new PostAdMessageController;
        foreach ([fn()=>$controller->getUserChats($room),fn()=>$controller->getRoomMessages($this->carry,'parcel',$room,md5((string)$room)),
            fn()=>$controller->getUserChatList($this->carry),fn()=>$controller->getSearchChatUsers($this->carry,'x'),
            fn()=>(new UserController)->messagesPostAdsUserList($this->carry)] as $call) {
            try {$call();$this->fail('Unrelated website access accepted');}catch(HttpException $e){$this->assertSame(404,$e->getStatusCode());}
        }
        $this->assertSame($before,DB::table('post_ad_room_messages')->get()->toJson());
    }

    public function test_website_contact_card_is_owner_only_even_with_show_contact_info_and_accepted_deal(): void
    {
        $source=file_get_contents(base_path('resources/views/frontend/listing-details.blade.php'));
        $start=strpos($source,'@if($listing->show_contact_info == 1');$end=strpos($source,'@endif',$start)+strlen('@endif');
        $compiled=app('blade.compiler')->compileString(substr($source,$start,$end-$start));
        $listing=(object)['show_contact_info'=>1,'created_by'=>$this->carrier->id,'createdByPhone'=>$this->carrier->phone,'createdByEmail'=>'private-owner@example.test'];
        foreach ([null,$this->sender,$this->other,$this->carrier] as $viewer) {
            Auth::guard('web')->logout();if($viewer)$this->actingAs($viewer,'web');
            ob_start();eval('?>'.$compiled);$html=ob_get_clean();
            if ($viewer===$this->carrier) {$this->assertStringContainsString($this->carrier->phone,$html);$this->assertStringContainsString('class="card"',$html);}
            else {$this->assertStringNotContainsString($this->carrier->phone,$html);$this->assertStringNotContainsString('private-owner@example.test',$html);}
        }
    }

    public function test_missing_column_fails_closed_and_contact_route_requires_authentication(): void
    {
        $this->getJson('/api/v1/customer/deals/'.$this->deal.'/contact')->assertUnauthorized();
        Schema::shouldReceive('hasColumns')->with('listing_deals',['conversation_id'])->andReturn(false);
        $this->contact()->assertStatus(503);$this->open()->assertStatus(503);
    }

    public function test_atomic_room_binding_and_first_message_rollback_on_storage_failure(): void
    {
        DB::connection()->beforeExecuting(function ($sql) {
            if (str_starts_with(strtolower($sql),'insert into `post_ad_room_messages`')) throw new \RuntimeException('Synthetic failure');
        });
        $this->open()->assertStatus(500);
        $this->assertNull(DB::table('listing_deals')->where('id',$this->deal)->value('conversation_id'));
        $this->assertSame(0,DB::table('post_ad_rooms')->count());Mail::assertNothingSent();Notification::assertNothingSent();
    }

    public function test_contact_is_deal_scoped_not_show_contact_flag_or_client_selected_user_and_listing(): void
    {
        DB::table('post_ads')->where('id',$this->carry)->update(['show_contact_info'=>0]);
        $this->contact()->assertOk()->assertJsonPath('data.counterpart.phone',$this->carrier->phone);
        foreach (['listing_id='.$this->send,'user_id='.$this->other->id,'deal_id=999999'] as $query) {
            $this->getJson('/api/v1/customer/deals/'.$this->deal.'/contact?'.$query,$this->headers())->assertUnprocessable();
        }
        $this->carrier->update(['isVerified'=>0]);$this->contact()->assertForbidden();
    }

    public function test_web_creation_cannot_choose_ambiguous_deals_without_explicit_id_or_spoof_identity(): void
    {
        $this->makeDeal(['status'=>'confirmed']);$this->actingAs($this->sender,'web');$controller=new PostAdMessageController;
        $body=['post_ads_id'=>$this->carry,'message'=>'Hello'];
        try {$controller->createMessageRoom(Request::create('/user/create/room','POST',$body));$this->fail('Ambiguous deal chosen');}
        catch(HttpException $e){$this->assertSame(409,$e->getStatusCode());}
        $body['deal_id']=$this->deal;
        try {$controller->createMessageRoom(Request::create('/user/create/room','POST',$body+['to_user_id'=>$this->other->id]));$this->fail('Forged recipient accepted');}
        catch(HttpException $e){$this->assertSame(403,$e->getStatusCode());}
        $this->assertSame(0,DB::table('post_ad_rooms')->count());
        $controller->createMessageRoom(Request::create('/user/create/room','POST',$body));
        $this->assertSame(1,DB::table('post_ad_rooms')->count());
    }

    public function test_database_prevents_binding_one_conversation_to_two_deals(): void
    {
        $room=$this->open()->assertCreated()->json('data.id');
        DB::beginTransaction();
        try {$this->makeDeal(['conversation_id'=>$room]);$this->fail('Conversation reused by another deal');}
        catch(\Illuminate\Database\QueryException $e){$this->assertSame('23000',(string)$e->errorInfo[0]);}
        finally {DB::rollBack();}
    }

    public function test_deleted_completed_room_remains_website_readable_to_participants_only(): void
    {
        $room=$this->open()->assertCreated()->json('data.id');$this->dealStatus('completed');
        DB::table('post_ads')->whereIn('id',[$this->send,$this->carry])->delete();
        $this->actingAs($this->sender,'web');
        $this->assertSame($room,(int)DealContactAccess::room($room,$this->sender->id)->id);
        $this->assertSame(1,DealContactAccess::messages(DealContactAccess::room($room,$this->sender->id))->count());
    }

    public function test_contact_enforces_existing_mobile_token_and_customer_eligibility(): void
    {
        $url='/api/v1/customer/deals/'.$this->deal.'/contact';
        $good=$this->tokens['sender'];$this->tokens['sender']='invalid';$this->contact()->assertUnauthorized();
        $revoked=$this->sender->createToken('mobile',['mobile:account']);$revoked->accessToken->delete();
        $this->tokens['sender']=$revoked->plainTextToken;$this->contact()->assertUnauthorized();
        $this->tokens['sender']=$this->sender->createToken('other',['other'])->plainTextToken;$this->contact()->assertForbidden();
        $this->tokens['sender']=$good;
        foreach ([['isActive'=>0],['isActive'=>1,'isVerified'=>0]] as $change) {$this->sender->update($change);$this->contact()->assertForbidden();}
        $this->sender->update(['isActive'=>1,'isVerified'=>1]);
        foreach ([['admin'],['user','admin']] as $roles) {$this->sender->syncRoles($roles);$this->contact()->assertForbidden();}
    }

    public function test_website_text_storage_policy_is_preserved_without_mobile_character_cap(): void
    {
        $this->actingAs($this->sender,'web');
        (new PostAdMessageController)->createMessageRoom(Request::create('/user/create/room','POST',[
            'post_ads_id'=>$this->carry,'message'=>str_repeat('a',12000)]));
        $this->assertSame(12000,strlen(DB::table('post_ad_room_messages')->value('message')));
    }

    public function test_completed_history_uses_retained_participants_when_counterpart_account_is_missing(): void
    {
        $room=$this->open()->assertCreated()->json('data.id');$this->dealStatus('completed');
        $this->carrier->delete();
        $this->getJson('/api/v1/customer/conversations/'.$room,$this->headers())->assertOk()->assertJsonPath('data.other_participant.display_name',null);
        $this->getJson('/api/v1/customer/conversations/'.$room.'/messages',$this->headers())->assertOk()->assertJsonCount(1,'data.items');
        $this->contact()->assertForbidden();
    }
}
