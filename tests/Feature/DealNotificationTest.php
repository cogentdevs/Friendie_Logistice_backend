<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DealNotificationTest extends TestCase
{
    use DatabaseTransactions;
    private array $users=[];
    private array $tokens=[];
    private int $senderListing;
    private int $carrierListing;
    private string $base='/api/v1/customer/notifications';

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(Schema::hasTable('deal_notifications'));
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-25 12:00:00','UTC'));
        Role::findOrCreate('user','web'); Role::findOrCreate('admin','web');
        foreach (['sender','carrier','other'] as $who) {
            $user=User::factory()->create(['isActive'=>1,'isVerified'=>1]); $user->assignRole('user');
            $this->users[$who]=$user; $this->tokens[$who]=$user->createToken('mobile',['mobile:account'])->plainTextToken;
        }
        $category=DB::table('categories')->insertGetId(['name'=>'Parcel','isActive'=>1]);
        $country=DB::table('countries')->insertGetId(['name'=>'Alpha','isActive'=>1,'currency_name'=>'USD']);
        $city=DB::table('cities')->insertGetId(['name'=>'City','country_id'=>$country,'isActive'=>1]);
        $fields=['category_id'=>$category,'title'=>'Parcel','description'=>'Safe','from_country_id'=>$country,
            'to_country_id'=>$country,'from_city_id'=>$city,'to_city_id'=>$city,'isActive'=>1,
            'departure_on'=>'2030-01-01','reached_on'=>'2030-01-02','weight'=>5,'weight_price'=>1,'currency'=>'USD'];
        $this->senderListing=DB::table('post_ads')->insertGetId($fields+['ad_type'=>'Send','created_by'=>$this->users['sender']->id]);
        $this->carrierListing=DB::table('post_ads')->insertGetId(array_replace($fields,['weight'=>12,'ad_type'=>'Carring','created_by'=>$this->users['carrier']->id]));
    }

    private function headers(string $who='sender'): array
    {
        app('auth')->forgetGuards(); return ['Authorization'=>'Bearer '.$this->tokens[$who]];
    }
    private function submit(string $who='sender')
    {
        return $this->postJson('/api/v1/customer/deals',['sender_listing_id'=>$this->senderListing,
            'carrier_listing_id'=>$this->carrierListing,'requested_kg'=>5],$this->headers($who));
    }
    private function action(int $deal,string $action,string $who='sender')
    {
        return $this->postJson('/api/v1/customer/deals/'.$deal.'/'.$action,[],$this->headers($who));
    }
    private function notice(int $deal,string $event,string $who): int
    {
        return (int)DB::table('deal_notifications')->where(['deal_id'=>$deal,'event'=>$event,'recipient_user_id'=>$this->users[$who]->id])->value('id');
    }
    private function read(int $id,string $who='sender')
    {
        return $this->postJson($this->base.'/'.$id.'/read',[],$this->headers($who));
    }
    private function counts(string $who,int $unread,int $tasks): void
    {
        $this->getJson($this->base.'/counts',$this->headers($who))->assertOk()->assertExactJson([
            'success'=>true,'message'=>'Notification counts retrieved.','data'=>['unread_count'=>$unread,'pending_task_count'=>$tasks]]);
    }

    public function test_whole_lifecycle_read_tasks_stay_visible_until_resolved_and_completion_disappears_on_view(): void
    {
        $deal=$this->submit()->assertCreated()->json('data.id');
        $this->counts('sender',0,0); $this->counts('carrier',1,1);
        $request=$this->notice($deal,'request_submitted','carrier');
        $time=$this->read($request,'carrier')->assertOk()->assertJsonPath('data.visible',true)->assertJsonPath('data.allowed_actions',['accept','reject'])->json('data.read_at');
        $this->travel(1)->minutes();
        $this->read($request,'carrier')->assertOk()->assertJsonPath('data.read_at',$time);
        $this->counts('carrier',0,1);
        $this->action($deal,'accept','carrier')->assertOk(); $this->counts('carrier',0,0); $this->counts('sender',1,1);
        $this->getJson($this->base,$this->headers('carrier'))->assertOk()->assertJsonPath('data.items',[]);
        $this->read($this->notice($deal,'request_accepted','sender'))->assertOk()->assertJsonPath('data.visible',true);
        $this->action($deal,'confirm')->assertOk(); $this->counts('sender',1,0); $this->counts('carrier',1,1);
        $this->read($this->notice($deal,'deal_confirmed','carrier'),'carrier')->assertOk()->assertJsonPath('data.allowed_actions',['cancel','pickup']);
        $this->read($this->notice($deal,'deal_confirmed','sender'))->assertOk()->assertJsonPath('data.visible',false);
        $this->action($deal,'pickup','carrier')->assertOk(); $this->counts('carrier',1,1);
        $this->read($this->notice($deal,'parcel_picked_up','carrier'),'carrier')->assertOk()->assertJsonPath('data.allowed_actions',['complete']);
        $this->read($this->notice($deal,'parcel_picked_up','sender'))->assertOk()->assertJsonPath('data.visible',false);
        $this->action($deal,'complete','carrier')->assertOk();
        foreach (['sender','carrier'] as $who) {
            $this->counts($who,1,0);
            $this->read($this->notice($deal,'delivery_completed',$who),$who)->assertOk()->assertJsonPath('data.visible',false);
            $this->counts($who,0,0);
            $this->getJson($this->base,$this->headers($who))->assertOk()->assertJsonPath('data.items',[]);
            $this->getJson('/api/v1/customer/deals?status=completed',$this->headers($who))->assertOk()->assertJsonPath('data.items.0.id',$deal);
        }
        $this->assertSame(8,DB::table('deal_notifications')->where('deal_id',$deal)->count());
        Mail::assertNothingSent(); Notification::assertNothingSent();
    }

    public function test_rejection_cancellation_and_carrier_initiated_requests_use_correct_recipients(): void
    {
        $first=$this->submit('carrier')->assertCreated()->json('data.id');
        $this->assertGreaterThan(0,$this->notice($first,'request_submitted','sender'));
        $this->action($first,'reject')->assertOk();
        $this->assertGreaterThan(0,$this->notice($first,'request_rejected','carrier'));
        // Unread resolved task stays informational until viewed, without actions.
        $this->getJson($this->base,$this->headers())->assertOk()->assertJsonPath('data.items.0.is_pending_task',false)->assertJsonPath('data.items.0.allowed_actions',[]);
        $this->read($this->notice($first,'request_submitted','sender'))->assertOk()->assertJsonPath('data.visible',false);
        $second=$this->submit('carrier')->assertCreated()->json('data.id');
        $this->action($second,'accept')->assertOk();
        $this->assertGreaterThan(0,$this->notice($second,'request_accepted','carrier'));
        $this->action($second,'cancel')->assertOk();
        $this->assertGreaterThan(0,$this->notice($second,'deal_cancelled','carrier'));
        $third=$this->submit()->assertCreated()->json('data.id');
        $this->action($third,'cancel')->assertOk();
        $this->assertGreaterThan(0,$this->notice($third,'deal_cancelled','carrier'));
    }

    public function test_acceptance_expiry_resolves_read_task_without_get_writes(): void
    {
        $deal=$this->submit()->assertCreated()->json('data.id'); $this->action($deal,'accept','carrier')->assertOk();
        $this->read($this->notice($deal,'request_accepted','sender'))->assertOk();
        $this->counts('sender',0,1); $this->travel(24)->hours();
        $before=DB::table('listing_deals')->find($deal);
        DB::connection()->enableQueryLog(); DB::connection()->flushQueryLog();
        $this->counts('sender',0,0);
        $this->getJson($this->base,$this->headers())->assertOk()->assertJsonPath('data.items',[]);
        foreach (DB::connection()->getQueryLog() as $q) $this->assertMatchesRegularExpression('/^select\\b/i',$q['query']);
        $this->assertEquals($before,DB::table('listing_deals')->find($deal));
    }

    public function test_recipient_idor_guests_ineligible_accounts_and_unknown_inputs_are_rejected(): void
    {
        $deal=$this->submit()->assertCreated()->json('data.id'); $notice=$this->notice($deal,'request_submitted','carrier');
        foreach (['sender','other'] as $who) $this->read($notice,$who)->assertNotFound();
        $this->read(2147483647,'carrier')->assertNotFound();
        $this->getJson($this->base,$this->headers('other'))->assertOk()->assertJsonPath('data.items',[]); $this->counts('other',0,0);
        app('auth')->forgetGuards();
        $this->getJson($this->base)->assertUnauthorized(); $this->getJson($this->base.'/counts')->assertUnauthorized();
        $this->postJson($this->base.'/'.$notice.'/read')->assertUnauthorized();
        $this->getJson($this->base,['Authorization'=>'Bearer invalid'])->assertUnauthorized();
        foreach ([['admin'],['user','admin'],[]] as $roles) {
            $this->users['carrier']->syncRoles($roles);
            $this->getJson($this->base,$this->headers('carrier'))->assertForbidden();
            $this->read($notice,'carrier')->assertForbidden();
        }
        $this->users['carrier']->syncRoles(['user']);
        foreach (['isActive','isVerified'] as $field) {
            $this->users['carrier']->update([$field=>0]);
            $this->getJson($this->base.'/counts',$this->headers('carrier'))->assertForbidden();
            $this->users['carrier']->update([$field=>1]);
        }
        $this->postJson($this->base.'/'.$notice.'/read',['recipient_user_id'=>$this->users['sender']->id],$this->headers('carrier'))->assertUnprocessable();
        $this->getJson($this->base.'?recipient_user_id=1',$this->headers('carrier'))->assertUnprocessable();
        $this->assertNull(DB::table('deal_notifications')->where('id',$notice)->value('read_at'));
        $this->users['carrier']->tokens()->delete();
        $this->read($notice,'carrier')->assertUnauthorized();
    }

    public function test_pagination_filters_privacy_and_join_query_count_are_bounded(): void
    {
        $deal=$this->submit()->assertCreated()->json('data.id');
        $record=(array)DB::table('listing_deals')->find($deal); unset($record['id']);
        $record['active_pair']=null;
        $snapshot=json_decode($record['listing_snapshot'],true);
        $snapshot['sender']['title']='Contact secret@example.test'; $snapshot['sender']['phone']='PRIVATE';
        $record['listing_snapshot']=json_encode($snapshot,JSON_THROW_ON_ERROR);
        $ids=[];
        for ($i=0;$i<5;$i++) {
            $other=DB::table('listing_deals')->insertGetId($record);
            $ids[]=DB::table('deal_notifications')->insertGetId(['deal_id'=>$other,'recipient_user_id'=>$this->users['carrier']->id,
                'event'=>'request_submitted','created_at'=>now()]);
        }
        foreach ([1,5] as $perPage) {
            DB::connection()->enableQueryLog(); DB::connection()->flushQueryLog();
            $body=$this->getJson($this->base.'?filter=tasks&per_page='.$perPage,$this->headers('carrier'))->assertOk()
                ->assertJsonCount($perPage,'data.items')->assertJsonPath('data.pagination.total',6)
                ->assertJsonPath('data.items.0.id',end($ids))->assertJsonPath('data.items.0.summary.parcel_title',null)->getContent();
            $reads=array_filter(DB::connection()->getQueryLog(),fn($q)=>str_contains($q['query'],'from `deal_notifications`'));
            $this->assertCount(2,$reads); // Count and joined page query, independent of item count.
            foreach (['phone','email','latitude','longitude','address','listing_snapshot','PRIVATE','secret@example.test'] as $field) $this->assertStringNotContainsString($field,$body);
        }
        $this->read(end($ids),'carrier')->assertOk();
        $this->getJson($this->base.'?filter=unread',$this->headers('carrier'))->assertOk()->assertJsonPath('data.pagination.total',5);
        $this->getJson($this->base.'?filter=tasks',$this->headers('carrier'))->assertOk()->assertJsonPath('data.pagination.total',6);
        $this->getJson($this->base.'?filter=all&page=2&per_page=5',$this->headers('carrier'))->assertOk()->assertJsonCount(1,'data.items');
        foreach (['filter=invalid','per_page=51','page=0'] as $bad) $this->getJson($this->base.'?'.$bad,$this->headers('carrier'))->assertUnprocessable();
    }

    public function test_transition_retries_and_unique_index_prevent_duplicate_notifications(): void
    {
        $deal=$this->submit()->assertCreated()->json('data.id');
        foreach ([['accept','carrier'],['confirm','sender'],['pickup','carrier'],['complete','carrier']] as [$action,$who]) {
            $this->action($deal,$action,$who)->assertOk();
            $count=DB::table('deal_notifications')->where('deal_id',$deal)->count();
            $this->action($deal,$action,$who)->assertOk();
            $this->assertSame($count,DB::table('deal_notifications')->where('deal_id',$deal)->count());
        }
        $row=(array)DB::table('deal_notifications')->where('deal_id',$deal)->first(); unset($row['id']);
        DB::beginTransaction();
        try { DB::table('deal_notifications')->insert($row); $this->fail('Duplicate notification accepted.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertSame('23000',(string)$e->errorInfo[0]); }
        finally { DB::rollBack(); }
    }

    public function test_notification_insert_failure_rolls_back_request_creation(): void
    {
        $before=DB::table('listing_deals')->count();
        DB::connection()->beforeExecuting(function ($sql) {
            if (str_starts_with(strtolower($sql),'insert into `deal_notifications`')) throw new \RuntimeException('Synthetic notice storage failure');
        });
        $this->submit()->assertStatus(500);
        $this->assertSame($before,DB::table('listing_deals')->count());
        $this->assertSame(0,DB::table('deal_notifications')->where('recipient_user_id',$this->users['carrier']->id)->count());
    }

    public function test_notification_insert_failure_rolls_back_deal_transition_and_reservation(): void
    {
        $deal=$this->submit()->assertCreated()->json('data.id'); $before=DB::table('listing_deals')->find($deal);
        DB::connection()->beforeExecuting(function ($sql) {
            if (str_starts_with(strtolower($sql),'insert into `deal_notifications`')) throw new \RuntimeException('Synthetic transition notice failure');
        });
        $this->action($deal,'accept','carrier')->assertStatus(500);
        $this->assertEquals($before,DB::table('listing_deals')->find($deal));
        $this->assertSame(1,DB::table('deal_notifications')->where('deal_id',$deal)->count());
    }

    public function test_missing_notification_table_fails_closed_without_creating_or_transitioning_deals(): void
    {
        $deal=$this->submit()->assertCreated()->json('data.id'); $before=DB::table('listing_deals')->find($deal);
        $schema=Schema::getFacadeRoot();
        Schema::shouldReceive('hasTable')->andReturnUsing(fn($table)=>$table==='deal_notifications'?false:$schema->hasTable($table));
        $this->getJson($this->base,$this->headers())->assertStatus(503);
        $this->getJson($this->base.'/counts',$this->headers())->assertStatus(503);
        $this->action($deal,'accept','carrier')->assertStatus(503);
        $this->assertEquals($before,DB::table('listing_deals')->find($deal));
    }

    public function test_notifications_survive_parent_listing_deletion_without_revealing_unrelated_recipients(): void
    {
        $deal=$this->submit()->assertCreated()->json('data.id');
        $this->action($deal,'accept','carrier')->assertOk(); $this->action($deal,'confirm')->assertOk();
        $this->action($deal,'pickup','carrier')->assertOk(); $this->action($deal,'complete','carrier')->assertOk();
        DB::table('post_ads')->whereIn('id',[$this->senderListing,$this->carrierListing])->delete();
        $this->getJson($this->base,$this->headers())->assertOk()->assertJsonPath('data.items.0.event','delivery_completed')
            ->assertJsonPath('data.items.0.summary.parcel_title','Parcel');
        $bad=DB::table('deal_notifications')->insertGetId(['deal_id'=>$deal,'recipient_user_id'=>$this->users['other']->id,
            'event'=>'delivery_completed','created_at'=>now()]);
        $this->read($bad,'other')->assertNotFound(); $this->counts('other',0,0);
    }

    public function test_existing_deals_are_not_backfilled_and_only_future_actual_events_generate_notifications(): void
    {
        $deal=$this->submit()->assertCreated()->json('data.id');
        DB::table('deal_notifications')->where('deal_id',$deal)->delete(); // Synthetic predeployment deal.
        $this->getJson($this->base,$this->headers('carrier'))->assertOk()->assertJsonPath('data.items',[]);
        $this->action($deal,'accept','carrier')->assertOk();
        $this->assertSame(['request_accepted'],DB::table('deal_notifications')->where('deal_id',$deal)->pluck('event')->all());
        $before=DB::table('deal_notifications')->where('deal_id',$deal)->get()->toJson();
        $this->action($deal,'accept','carrier')->assertOk();
        $this->assertSame($before,DB::table('deal_notifications')->where('deal_id',$deal)->get()->toJson());
    }

    public function test_read_storage_failure_preserves_unread_state_and_rate_limit_is_scoped(): void
    {
        $deal=$this->submit()->assertCreated()->json('data.id'); $notice=$this->notice($deal,'request_submitted','carrier');
        DB::connection()->beforeExecuting(function ($sql) {
            if (str_starts_with(strtolower($sql),'update `deal_notifications`')) throw new \RuntimeException('Synthetic read failure');
        });
        $this->read($notice,'carrier')->assertStatus(500);
        $this->assertNull(DB::table('deal_notifications')->where('id',$notice)->value('read_at'));
        $this->counts('carrier',1,1);
        $key='deal-notification-read:user:'.$this->users['carrier']->id;
        for ($i=0;$i<120;$i++) \Illuminate\Support\Facades\RateLimiter::hit($key,60);
        $this->read($notice,'carrier')->assertStatus(429)->assertHeader('Retry-After');
    }
}
