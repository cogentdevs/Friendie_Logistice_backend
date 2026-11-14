<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerReviewTest extends TestCase
{
    use DatabaseTransactions;
    private User $actor;
    private User $owner;
    private User $other;
    private string $token;
    private int $listing;
    private int $category;
    private int $country;
    private int $city;
    private string $base='/api/v1/customer/reviews';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-14 12:00:00','UTC'));
        Role::findOrCreate('user','web');Role::findOrCreate('admin','web');
        foreach (['actor','owner','other'] as $i=>$name) {
            $this->$name=User::factory()->create(['name'=>ucfirst($name),'email'=>$name.'-private@example.test',
                'phone'=>'+1000000000'.$i,'isActive'=>1,'isVerified'=>1]);
            $this->$name->assignRole('user');
        }
        $this->token=$this->actor->createToken('mobile',['mobile:account'])->plainTextToken;
        $this->category=DB::table('categories')->insertGetId(['name'=>'Parcels','isActive'=>1]);
        $this->country=DB::table('countries')->insertGetId(['name'=>'Alpha','isActive'=>1]);
        $this->city=DB::table('cities')->insertGetId(['name'=>'Alpha City','country_id'=>$this->country,'isActive'=>1]);
        $this->listing=DB::table('post_ads')->insertGetId(['ad_type'=>'Carring','title'=>'Parcel capacity','created_by'=>$this->owner->id,
            'show_contact_info'=>0,'isActive'=>1,'departure_on'=>'2030-01-01','category_id'=>$this->category,
            'from_country_id'=>$this->country,'to_country_id'=>$this->country,'from_city_id'=>$this->city,'to_city_id'=>$this->city]);
    }
    private function headers(?string $token=null): array
    {
        app('auth')->forgetGuards();return ['Authorization'=>'Bearer '.($token??$this->token)];
    }
    private function review(User $from,User $to,array $extra=[]): int
    {
        return DB::table('reviews')->insertGetId(array_replace(['post_ads_id'=>$this->listing,'from_user_id'=>$from->id,
            'to_user_id'=>$to->id,'rating'=>4,'review'=>'Helpful communication.','date_time'=>'2026-11-14',
            'created_at'=>now(),'updated_at'=>now()],$extra));
    }
    private function body(array $extra=[]): array
    {
        return array_replace(['post_ads_id'=>$this->listing,'rating'=>5,'review'=>'Helpful communication.'],$extra);
    }
    public static function endpoints(): array {return [['GET','/received'],['GET','/given'],['POST','']];}
    #[DataProvider('endpoints')]
    public function test_every_route_requires_eligible_bearer_customer(string $method,string $suffix): void
    {
        $this->json($method,$this->base.$suffix)->assertUnauthorized();
        $this->json($method,$this->base.$suffix,[],$this->headers('invalid'))->assertUnauthorized();
        $revoked=$this->actor->createToken('revoked',['mobile:account']);$revoked->accessToken->delete();
        $this->json($method,$this->base.$suffix,[],$this->headers($revoked->plainTextToken))->assertUnauthorized();
        $wrong=$this->actor->createToken('wrong',['other'])->plainTextToken;
        $this->json($method,$this->base.$suffix,[],$this->headers($wrong))->assertForbidden();
        foreach ([['isActive'=>0],['isActive'=>1,'isVerified'=>0]] as $state) {
            $this->actor->forceFill($state)->save();$this->json($method,$this->base.$suffix,[],$this->headers())->assertForbidden();
        }
        $this->actor->forceFill(['isActive'=>1,'isVerified'=>1])->save();
        foreach ([['admin'],['user','admin'],[]] as $roles) {
            $this->actor->syncRoles($roles);$this->json($method,$this->base.$suffix,[],$this->headers())->assertForbidden();
        }
    }
    public function test_received_given_identity_content_stable_pagination_and_allowlist(): void
    {
        $received=$this->review($this->owner,$this->actor);
        $newest=$this->review($this->other,$this->actor,['rating'=>3,'review'=>'Clear information.']);
        $given=$this->review($this->actor,$this->owner);
        $this->review($this->owner,$this->other);
        $r=$this->getJson($this->base.'/received?per_page=1',$this->headers())->assertOk()
            ->assertJsonPath('data.pagination.total',2)->assertJsonPath('data.items.0.id',$newest)
            ->assertJsonPath('data.items.0.reviewer.display_name','Other')->assertJsonPath('data.items.0.rating',3)
            ->assertJsonPath('data.items.0.review','Clear information.')->assertJsonPath('data.items.0.date_time','2026-11-14');
        $this->getJson($this->base.'/received?per_page=1&page=2',$this->headers())->assertJsonPath('data.items.0.id',$received);
        $this->getJson($this->base.'/given',$this->headers())->assertOk()->assertJsonCount(1,'data.items')
            ->assertJsonPath('data.items.0.id',$given)->assertJsonPath('data.items.0.reviewed_customer.id',$this->owner->id)
            ->assertJsonPath('data.items.0.reviewed_customer.display_name','Owner')->assertJsonPath('data.items.0.listing.id',$this->listing);
        foreach (['phone','email','password','remember_token','isActive','isVerified','from_user_id','to_user_id'] as $key) {
            $this->assertStringNotContainsString('"'.$key.'":',$r->getContent());
        }
        $this->assertStringNotContainsString('private@example.test',$r->getContent());
        $this->assertStringNotContainsString('+1000000000',$r->getContent());
    }
    public function test_empty_results_null_legacy_values_and_missing_join_behavior(): void
    {
        $this->getJson($this->base.'/received',$this->headers())->assertJsonPath('data.items',[])->assertJsonPath('data.pagination.last_page',1);
        $this->getJson($this->base.'/given',$this->headers())->assertJsonPath('data.items',[]);
        $this->review($this->actor,$this->owner,['post_ads_id'=>999999]);
        $this->review($this->actor,$this->owner,['review'=>null,'created_at'=>null,'rating'=>0]);
        $this->getJson($this->base.'/given',$this->headers())->assertJsonCount(1,'data.items')
            ->assertJsonPath('data.items.0.review',null)->assertJsonPath('data.items.0.created_at',null)->assertJsonPath('data.items.0.rating',0);
    }
    public function test_list_gets_are_select_only_do_not_render_blade_and_preserve_existing_duplicate_history(): void
    {
        $this->review($this->owner,$this->actor);$this->review($this->owner,$this->actor);
        $writes=[];$views=0;
        view()->composer('*',function () use (&$views) {$views++;});
        DB::listen(function ($q) use (&$writes) {if(!str_starts_with(strtolower(ltrim($q->sql)),'select'))$writes[]=$q->sql;});
        $this->getJson($this->base.'/received',$this->headers())->assertJsonCount(2,'data.items')->assertHeader('Cache-Control','no-store, private');
        $this->getJson($this->base.'/given',$this->headers())->assertOk();
        $this->assertSame([],$writes);$this->assertSame(0,$views);Mail::assertNothingSent();
    }
    public static function badQuery(): array {return [['page=0'],['per_page=51'],['page[]=1'],['customer_id=1'],['reviewer_id=1'],['sort=x']];}
    #[DataProvider('badQuery')]
    public function test_invalid_queries_cannot_select_another_customer(string $query): void
    {
        foreach (['received','given'] as $action)$this->getJson($this->base.'/'.$action.'?'.$query,$this->headers())->assertUnprocessable();
    }
    public static function ratings(): array {return [[0],[1],[2],[3],[4],[5]];}
    #[DataProvider('ratings')]
    public function test_form_rating_values_store_exactly_for_public_listing_without_acceptance_or_chat_requirements(int $rating): void
    {
        // Approved API public-visibility check adds no chat or acceptance requirement.
        $before=DB::table('post_ads')->get()->toJson();
        $response=$this->postJson($this->base,$this->body(['rating'=>$rating]),$this->headers())->assertCreated()
            ->assertJsonPath('data.reviewer.id',$this->actor->id)->assertJsonPath('data.reviewed_customer.id',$this->owner->id)
            ->assertJsonPath('data.rating',$rating)->assertJsonPath('data.date_time','2026-11-14');
        $row=DB::table('reviews')->where('id',$response->json('data.id'))->first();
        $this->assertEqualsCanonicalizing(['id','listing','reviewer','reviewed_customer','rating','review','date_time','created_at','updated_at'],
            array_keys($response->json('data')));
        $this->assertSame($rating,$row->rating);$this->assertSame($this->actor->id,$row->from_user_id);
        $this->assertSame($this->owner->id,$row->to_user_id);$this->assertSame(0,DB::table('post_ad_rooms')->count());
        $this->assertSame(0,DB::table('post_ad_room_messages')->count());$this->assertSame($before,DB::table('post_ads')->get()->toJson());
        $this->assertSame((float)$rating,(float)DB::table('reviews')->where('to_user_id',$this->owner->id)->avg('rating'));
        Mail::assertNothingSent();Notification::assertNothingSent();Queue::assertNothingPushed();
    }
    public static function invalid(): array
    {
        return [[['rating'=>-1]],[['rating'=>6]],[['rating'=>2.5]],[['rating'=>[]]],[['rating'=>null]],
            [['review'=>'']],[['review'=>'  ']],[['review'=>[]]],[['review'=>null]],[['review'=>"bad\0value"]],
            [['post_ads_id'=>0]],[['post_ads_id'=>'1 OR 1=1']],[['from_user_id'=>1]],[['to_user_id'=>1]],
            [['reviewer_id'=>1]],[['customer_id'=>1]],[['user_id'=>1]],[['isApproved'=>1]],[['attachment'=>'x']]];
    }
    #[DataProvider('invalid')]
    public function test_invalid_rating_text_and_impersonation_cannot_write(array $change): void
    {
        $this->postJson($this->base,$this->body($change),$this->headers())->assertUnprocessable();
        $this->assertSame(0,DB::table('reviews')->count());
    }
    public static function required(): array {return [['rating'],['review'],['post_ads_id']];}
    #[DataProvider('required')]
    public function test_required_fields(string $field): void
    {
        $data=$this->body();unset($data[$field]);$this->postJson($this->base,$data,$this->headers())->assertUnprocessable();
    }
    public function test_missing_listing_or_owner_self_review_and_duplicate_are_rejected(): void
    {
        $this->postJson($this->base,$this->body(['post_ads_id'=>999999]),$this->headers())->assertNotFound();
        DB::table('post_ads')->where('id',$this->listing)->update(['created_by'=>999999]);
        $this->postJson($this->base,$this->body(),$this->headers())->assertNotFound();
        DB::table('post_ads')->where('id',$this->listing)->update(['created_by'=>$this->actor->id]);
        $this->postJson($this->base,$this->body(),$this->headers())->assertUnprocessable();
        DB::table('post_ads')->where('id',$this->listing)->update(['created_by'=>$this->owner->id]);
        $this->review($this->actor,$this->other); // Same reviewer/listing blocks even if legacy recipient was incorrect.
        $this->postJson($this->base,$this->body(),$this->headers())->assertStatus(409);
        $this->assertSame(1,DB::table('reviews')->count());
    }
    public function test_no_invented_target_account_status_rule_or_longtext_business_cap_for_public_listing(): void
    {
        $this->owner->forceFill(['isActive'=>0,'isVerified'=>0])->save();
        $text=str_repeat('a',11000);
        $this->postJson($this->base,$this->body(['review'=>$text]),$this->headers())->assertCreated()->assertJsonPath('data.review',$text);
    }
    public function test_duplicate_post_does_not_overwrite_rating_and_another_listing_is_independent(): void
    {
        $this->postJson($this->base,$this->body(),$this->headers())->assertCreated();
        $this->postJson($this->base,$this->body(['rating'=>1,'review'=>'Different']),$this->headers())->assertStatus(409);
        $this->assertSame(5,DB::table('reviews')->first()->rating);
        $data=(array)DB::table('post_ads')->where('id',$this->listing)->first();unset($data['id']);
        $otherListing=DB::table('post_ads')->insertGetId(array_replace($data,['ad_type'=>'Send','title'=>'Another parcel']));
        $this->postJson($this->base,$this->body(['post_ads_id'=>$otherListing]),$this->headers())->assertCreated();
        $this->assertSame(2,DB::table('reviews')->count());
    }
    public function test_submission_failure_is_sanitized_and_rolls_back(): void
    {
        DB::connection()->beforeExecuting(function ($sql) {if(str_starts_with(strtolower($sql),'insert into `reviews`'))throw new \RuntimeException('private-synthetic-failure');});
        $this->postJson($this->base,$this->body(),$this->headers())->assertStatus(500)
            ->assertExactJson(['success'=>false,'message'=>'Server error']);
        $this->assertSame(0,DB::table('reviews')->count());Mail::assertNothingSent();
    }
    public function test_mutation_rate_limit(): void
    {
        for($i=0;$i<10;$i++)$this->postJson($this->base,[],$this->headers())->assertUnprocessable();
        $this->postJson($this->base,[],$this->headers())->assertStatus(429)->assertHeader('Retry-After');
    }
    public function test_optional_details_endpoint_is_not_added(): void
    {
        $id=$this->review($this->owner,$this->actor);
        $this->getJson($this->base.'/'.$id,$this->headers())->assertNotFound();
    }

    public static function nonpublicTargets(): array
    {
        return [['pending'],['expired_status'],['past_departure'],['null_departure'],['inactive_category'],
            ['missing_category'],['missing_owner'],['missing_origin_country'],['missing_destination_country'],
            ['missing_origin_city'],['missing_destination_city']];
    }

    #[DataProvider('nonpublicTargets')]
    public function test_nonpublic_new_review_is_identical_to_missing_and_never_writes_or_discloses_metadata(string $condition): void
    {
        $changes=['title'=>'Secret unpublished parcel','description'=>'Confidential description',
            'from_area'=>'Private origin address','to_area'=>'Private destination address'];
        $this->owner->forceFill(['name'=>'Secret Owner'])->save();
        $changes += match ($condition) {
            'pending'=>['isActive'=>0], 'expired_status'=>['isActive'=>2],
            'past_departure'=>['departure_on'=>'2026-11-13'], 'null_departure'=>['departure_on'=>null],
            'missing_category'=>['category_id'=>999999], 'missing_owner'=>['created_by'=>999999],
            'missing_origin_country'=>['from_country_id'=>999999], 'missing_destination_country'=>['to_country_id'=>999999],
            'missing_origin_city'=>['from_city_id'=>999999], 'missing_destination_city'=>['to_city_id'=>999999],
            default=>[],
        };
        DB::table('post_ads')->where('id',$this->listing)->update($changes);
        if ($condition==='inactive_category') DB::table('categories')->where('id',$this->category)->update(['isActive'=>0]);
        $before=[];foreach (['post_ads','reviews','post_ad_rooms','post_ad_room_messages','users'] as $table) {
            $before[$table]=DB::table($table)->get()->toJson();
        }
        $missing=$this->postJson($this->base,$this->body(['post_ads_id'=>999999]),$this->headers())->assertNotFound()
            ->assertExactJson(['success'=>false,'message'=>'Resource not found']);
        $denied=$this->postJson($this->base,$this->body(),$this->headers())->assertNotFound()
            ->assertExactJson(['success'=>false,'message'=>'Resource not found']);
        $this->assertSame($missing->getContent(),$denied->getContent());
        foreach (['Secret unpublished parcel','Secret Owner','Confidential description','Private origin address',
            'Private destination address',$this->owner->phone,$this->owner->email] as $private) {
            $this->assertStringNotContainsString($private,$denied->getContent());
        }
        $this->getJson('/api/v1/listings/'.$this->listing)->assertNotFound();
        foreach ($before as $table=>$snapshot) $this->assertSame($snapshot,DB::table($table)->get()->toJson());
        $this->assertSame(0,DB::table('reviews')->count());
        Mail::assertNothingSent();Notification::assertNothingSent();Queue::assertNothingPushed();
    }

    public function test_nonpublic_visibility_precedes_duplicate_and_self_review_without_existing_row_changes(): void
    {
        $this->review($this->actor,$this->owner);
        DB::table('post_ads')->where('id',$this->listing)->update(['isActive'=>0]);
        $before=DB::table('reviews')->get()->toJson();
        $missing=$this->postJson($this->base,$this->body(['post_ads_id'=>999999]),$this->headers())->assertNotFound();
        $duplicate=$this->postJson($this->base,$this->body(),$this->headers())->assertNotFound();
        DB::table('post_ads')->where('id',$this->listing)->update(['created_by'=>$this->actor->id]);
        $self=$this->postJson($this->base,$this->body(),$this->headers())->assertNotFound();
        $this->assertSame($missing->getContent(),$duplicate->getContent());
        $this->assertSame($missing->getContent(),$self->getContent());
        $this->assertSame($before,DB::table('reviews')->get()->toJson());
    }

    public static function historyStates(): array {return [[0],[2],['past'],['inactive_category']];}

    #[DataProvider('historyStates')]
    public function test_historical_received_and_given_reviews_survive_later_nonpublic_state_and_remain_scoped(int|string $state): void
    {
        $given=$this->review($this->actor,$this->owner);
        $received=$this->review($this->owner,$this->actor);
        if (is_int($state)) DB::table('post_ads')->where('id',$this->listing)->update(['isActive'=>$state]);
        if ($state==='past') DB::table('post_ads')->where('id',$this->listing)->update(['departure_on'=>'2000-01-01']);
        if ($state==='inactive_category') DB::table('categories')->where('id',$this->category)->update(['isActive'=>0]);
        $before=DB::table('reviews')->get()->toJson();
        $this->getJson($this->base.'/given?per_page=1',$this->headers())->assertOk()->assertJsonCount(1,'data.items')
            ->assertJsonPath('data.items.0.id',$given)->assertJsonPath('data.items.0.listing.title','Parcel capacity')
            ->assertJsonPath('data.pagination.total',1);
        $this->getJson($this->base.'/received?per_page=1',$this->headers())->assertOk()->assertJsonCount(1,'data.items')
            ->assertJsonPath('data.items.0.id',$received)->assertJsonPath('data.pagination.total',1);
        $this->postJson($this->base,$this->body(),$this->headers())->assertNotFound();
        $otherToken=$this->other->createToken('mobile',['mobile:account'])->plainTextToken;
        foreach (['given','received'] as $action) $this->getJson($this->base.'/'.$action,$this->headers($otherToken))
            ->assertOk()->assertJsonPath('data.items',[]);
        $this->assertSame($before,DB::table('reviews')->get()->toJson());
    }

    public function test_public_departure_today_and_inactive_geography_follow_exact_existing_public_scope(): void
    {
        DB::table('post_ads')->where('id',$this->listing)->update(['departure_on'=>'2026-11-14']);
        DB::table('countries')->where('id',$this->country)->update(['isActive'=>0]);
        DB::table('cities')->where('id',$this->city)->update(['isActive'=>0]);
        $this->getJson('/api/v1/listings/'.$this->listing)->assertOk();
        $this->postJson($this->base,$this->body(['rating'=>0]),$this->headers())->assertCreated()
            ->assertJsonPath('data.rating',0)->assertJsonPath('data.reviewed_customer.id',$this->owner->id);
    }

    public function test_invalid_review_validation_does_not_disclose_target_existence(): void
    {
        DB::table('post_ads')->where('id',$this->listing)->update(['isActive'=>0]);
        $missing=$this->postJson($this->base,$this->body(['post_ads_id'=>999999,'rating'=>6]),$this->headers())->assertUnprocessable();
        $private=$this->postJson($this->base,$this->body(['rating'=>6]),$this->headers())->assertUnprocessable();
        $this->assertSame($missing->getContent(),$private->getContent());
        $this->assertSame(0,DB::table('reviews')->count());
    }
}
