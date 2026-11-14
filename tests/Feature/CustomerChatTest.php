<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerChatTest extends TestCase
{
    use DatabaseTransactions;
    private User $actor;
    private User $owner;
    private User $stranger;
    private string $token;
    private int $listing;
    private string $base = '/api/v1/customer/conversations';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-14 12:00:00','UTC'));
        Role::findOrCreate('user','web');Role::findOrCreate('admin','web');
        foreach (['actor','owner','stranger'] as $i=>$field) {
            $this->$field=User::factory()->create(['isActive'=>1,'isVerified'=>1,'email'=>$field.'-private@example.test','phone'=>'+1000000000'.$i]);
            $this->$field->assignRole('user');
        }
        $this->token=$this->actor->createToken('mobile',['mobile:account'])->plainTextToken;
        $category=DB::table('categories')->insertGetId(['name'=>'Parcels','isActive'=>1]);
        $country=DB::table('countries')->insertGetId(['name'=>'Alpha','isActive'=>1]);
        $city=DB::table('cities')->insertGetId(['name'=>'Alpha City','country_id'=>$country,'isActive'=>1]);
        $this->listing=DB::table('post_ads')->insertGetId(['created_by'=>$this->owner->id,'category_id'=>$category,
            'from_country_id'=>$country,'to_country_id'=>$country,'from_city_id'=>$city,'to_city_id'=>$city,
            'ad_type'=>'Carring','title'=>'Parcel capacity','show_contact_info'=>0,'isActive'=>1,'departure_on'=>'2030-01-01']);
    }

    private function headers(?string $token=null): array
    {
        app('auth')->forgetGuards();return ['Authorization'=>'Bearer '.($token??$this->token)];
    }
    private function room(?int $from=null,?int $to=null): int
    {
        return DB::table('post_ad_rooms')->insertGetId(['post_ads_id'=>$this->listing,
            'from_user_id'=>$from??$this->actor->id,'to_user_id'=>$to??$this->owner->id,'created_at'=>now()]);
    }
    private function message(int $room,int $from,int $to,?string $read=null,string $text='Hello'): int
    {
        return DB::table('post_ad_room_messages')->insertGetId(['post_ad_room_id'=>$room,'from_user_id'=>$from,'to_user_id'=>$to,
            'message'=>$text,'message_on'=>'14 Nov 2026','read_by'=>$read,'created_at'=>now(),'updated_at'=>now()]);
    }
    public static function routes(): array
    {
        return [['GET',''],['GET','/1'],['GET','/1/messages'],['POST',''],['POST','/1/messages'],['POST','/1/read']];
    }
    #[DataProvider('routes')]
    public function test_authentication_on_all_endpoints(string $method,string $suffix): void
    {
        $this->json($method,$this->base.$suffix)->assertUnauthorized();
        $this->json($method,$this->base.$suffix,[],$this->headers('invalid'))->assertUnauthorized();
        $revoked=$this->actor->createToken('revoked',['mobile:account']);$revoked->accessToken->delete();
        $this->json($method,$this->base.$suffix,[],$this->headers($revoked->plainTextToken))->assertUnauthorized();
        foreach ([['isActive'=>0],['isActive'=>1,'isVerified'=>0]] as $state) {
            $this->actor->forceFill($state)->save();$this->json($method,$this->base.$suffix,[],$this->headers())->assertForbidden();
        }
        $this->actor->forceFill(['isActive'=>1,'isVerified'=>1])->save();
        foreach ([['admin'],['user','admin'],[]] as $roles) {
            $this->actor->syncRoles($roles);$this->json($method,$this->base.$suffix,[],$this->headers())->assertForbidden();
        }
    }
    public function test_creation_is_atomic_neutral_about_contacts_and_reuses_room_without_duplicate_message(): void
    {
        $data=['post_ads_id'=>$this->listing,'message'=>'Hello'];
        $r=$this->postJson($this->base,$data,$this->headers())->assertCreated();$id=$r->json('data.id');
        $r->assertJsonPath('data.other_participant.id',$this->owner->id);
        $this->postJson($this->base,$data,$this->headers())->assertOk()->assertJsonPath('data.id',$id);
        $this->assertSame(1,DB::table('post_ad_rooms')->count());$this->assertSame(1,DB::table('post_ad_room_messages')->count());
        $m=DB::table('post_ad_room_messages')->first();$this->assertSame($this->actor->id,$m->from_user_id);
        $this->assertSame($this->owner->id,$m->to_user_id);$this->assertNull($m->read_by);
        $this->assertStringNotContainsString('private@example.test',$r->getContent());
        $this->assertStringNotContainsString('+1000000000',$r->getContent());Mail::assertNothingSent();Notification::assertNothingSent();
    }
    public function test_invalid_private_expired_self_and_ineligible_targets_cannot_create(): void
    {
        $this->postJson($this->base,['post_ads_id'=>999999,'message'=>'Hello'],$this->headers())->assertNotFound();
        foreach ([0,2] as $status) {
            DB::table('post_ads')->where('id',$this->listing)->update(['isActive'=>$status]);
            $this->postJson($this->base,['post_ads_id'=>$this->listing,'message'=>'Hello'],$this->headers())->assertNotFound();
        }
        DB::table('post_ads')->where('id',$this->listing)->update(['isActive'=>1,'departure_on'=>'2026-11-14']);
        $this->postJson($this->base,['post_ads_id'=>$this->listing,'message'=>'Hello'],$this->headers())->assertNotFound();
        DB::table('post_ads')->where('id',$this->listing)->update(['departure_on'=>'2030-01-01']);
        $this->owner->syncRoles(['admin']);
        $this->postJson($this->base,['post_ads_id'=>$this->listing,'message'=>'Hello'],$this->headers())->assertNotFound();
        DB::table('post_ads')->where('id',$this->listing)->update(['created_by'=>$this->actor->id]);
        $this->postJson($this->base,['post_ads_id'=>$this->listing,'message'=>'Hello'],$this->headers())->assertUnprocessable();
        $this->assertSame(0,DB::table('post_ad_rooms')->count());
    }
    public static function invalidMessages(): array
    {
        return [[[]],[['message'=>'']],[['message'=>'   ']],[['message'=>['x']]],[['message'=>str_repeat('a',10001)]],
            [['message'=>"bad\0text"]],[['message'=>'Hi','sender_id'=>1]],[['message'=>'Hi','user_id'=>1]],
            [['message'=>'Hi','from_user_id'=>1]],[['message'=>'Hi','to_user_id'=>1]],[['message'=>'Hi','attachment'=>'x']]];
    }
    #[DataProvider('invalidMessages')]
    public function test_invalid_messages_and_impersonation_rejected(array $data): void
    {
        $id=$this->room();$this->postJson($this->base.'/'.$id.'/messages',$data,$this->headers())->assertUnprocessable();
        $this->postJson($this->base,$data+['post_ads_id'=>$this->listing],$this->headers())->assertUnprocessable();
        $this->assertSame(0,DB::table('post_ad_room_messages')->count());
    }
    public function test_idor_and_forged_room_ownership_fail_closed(): void
    {
        $id=$this->room($this->stranger->id);
        foreach ([['GET',''],['GET','/messages'],['POST','/messages'],['POST','/read']] as [$method,$suffix]) {
            $this->json($method,$this->base.'/'.$id.$suffix,['message'=>'Hello'],$this->headers())->assertNotFound();
        }
        $bad=$this->room($this->actor->id,$this->stranger->id);
        $this->getJson($this->base.'/'.$bad,$this->headers())->assertNotFound();
        $this->getJson($this->base,$this->headers())->assertJsonPath('data.items',[]);
    }
    public function test_index_history_stable_pagination_privacy_no_get_writes_or_views(): void
    {
        $first=$this->room();$second=$this->room();$unrelated=$this->room($this->stranger->id);
        $this->message($first,$this->owner->id,$this->actor->id);
        $latest=$this->message($second,$this->owner->id,$this->actor->id);
        $this->message($second,$this->stranger->id,$this->actor->id,null,'Forged message');
        $this->message($unrelated,$this->owner->id,$this->stranger->id);
        $writes=[];$views=0;view()->composer('*',function () use (&$views) {$views++;});
        DB::listen(function ($q) use (&$writes) {if(!str_starts_with(strtolower(ltrim($q->sql)),'select'))$writes[]=$q->sql;});
        $r=$this->getJson($this->base.'?per_page=1',$this->headers())->assertOk()
            ->assertJsonPath('data.items.0.id',$second)->assertJsonPath('data.items.0.unread_count',1)
            ->assertJsonPath('data.pagination.total',2);
        $this->getJson($this->base.'?per_page=1&page=2',$this->headers())->assertJsonPath('data.items.0.id',$first);
        $this->getJson($this->base.'/'.$second.'/messages',$this->headers())->assertOk()
            ->assertJsonCount(1,'data.items')->assertJsonPath('data.items.0.id',$latest)->assertJsonPath('data.items.0.is_read',false);
        $this->getJson($this->base.'/'.$second,$this->headers())->assertOk();
        $this->assertSame([],$writes);$this->assertSame(0,$views);
        foreach (['email','phone','password','read_by','from_user_id','to_user_id'] as $key) $this->assertStringNotContainsString('"'.$key.'":',$r->getContent());
    }
    public function test_sending_both_directions_history_and_read_are_scoped_idempotent_and_preserve_existing_reader(): void
    {
        $room=$this->room();$otherRoom=$this->room();
        $incoming=$this->message($room,$this->owner->id,$this->actor->id);
        $existing=$this->message($room,$this->owner->id,$this->actor->id,'legacy-other-reader');
        $outside=$this->message($otherRoom,$this->owner->id,$this->actor->id);
        $r=$this->postJson($this->base.'/'.$room.'/messages',['message'=>'A reply'],$this->headers())->assertCreated()
            ->assertJsonPath('data.sender.id',$this->actor->id);
        $this->postJson($this->base.'/'.$room.'/read',[],$this->headers())->assertOk()->assertJsonPath('data.marked_read',1)->assertJsonPath('data.unread_count',0);
        $this->postJson($this->base.'/'.$room.'/read',[],$this->headers())->assertJsonPath('data.marked_read',0);
        $this->assertSame((string)$this->actor->id,DB::table('post_ad_room_messages')->where('id',$incoming)->value('read_by'));
        $this->assertSame('legacy-other-reader',DB::table('post_ad_room_messages')->where('id',$existing)->value('read_by'));
        $this->assertNull(DB::table('post_ad_room_messages')->where('id',$outside)->value('read_by'));
        $this->assertNull(DB::table('post_ad_room_messages')->where('id',$r->json('data.id'))->value('read_by'));
        $ownerToken=$this->owner->createToken('mobile',['mobile:account'])->plainTextToken;
        $this->postJson($this->base.'/'.$room.'/messages',['message'=>'Owner reply'],$this->headers($ownerToken))
            ->assertCreated()->assertJsonPath('data.sender.id',$this->owner->id);
        $history=$this->getJson($this->base.'/'.$room.'/messages?per_page=2',$this->headers())->assertOk();
        $this->assertSame([$incoming,$existing],array_column($history->json('data.items'),'id'));Mail::assertNothingSent();
    }
    public function test_bad_pagination_unknown_parameters_and_empty_results(): void
    {
        $this->getJson($this->base,$this->headers())->assertJsonPath('data.items',[]);
        $id=$this->room();foreach (['per_page=51','page=0','page[]=1','search=x'] as $query) {
            $this->getJson($this->base.'?'.$query,$this->headers())->assertUnprocessable();
            $this->getJson($this->base.'/'.$id.'/messages?'.$query,$this->headers())->assertUnprocessable();
        }
        $this->getJson($this->base.'/'.$id.'/messages',$this->headers())->assertJsonPath('data.items',[]);
        $this->postJson($this->base.'/'.$id.'/read',['user_id'=>1],$this->headers())->assertUnprocessable();
    }
    public function test_message_failure_rolls_back_new_room_and_no_mail_or_listing_writes(): void
    {
        $before=DB::table('post_ads')->get()->toJson();
        DB::connection()->beforeExecuting(function ($sql) {if(str_starts_with(strtolower($sql),'insert into `post_ad_room_messages`'))throw new \RuntimeException('Synthetic failure');});
        $this->postJson($this->base,['post_ads_id'=>$this->listing,'message'=>'Hello'],$this->headers())->assertStatus(500);
        $this->assertSame(0,DB::table('post_ad_rooms')->count());$this->assertSame($before,DB::table('post_ads')->get()->toJson());Mail::assertNothingSent();
    }
    public function test_rate_limiting_mutations(): void
    {
        $id=$this->room();for($i=0;$i<60;$i++)$this->postJson($this->base.'/'.$id.'/read',[],$this->headers())->assertOk();
        $this->postJson($this->base.'/'.$id.'/read',[],$this->headers())->assertStatus(429)->assertHeader('Retry-After');
    }
}
