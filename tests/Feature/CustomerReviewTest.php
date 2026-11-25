<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
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
    private int $deal;
    private int $senderListing;
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
        $this->senderListing=DB::table('post_ads')->insertGetId(['ad_type'=>'Send','title'=>'Sender parcel','created_by'=>$this->actor->id,
            'weight'=>5,'isActive'=>1,'departure_on'=>'2030-01-01','category_id'=>$this->category,
            'from_country_id'=>$this->country,'to_country_id'=>$this->country,'from_city_id'=>$this->city,'to_city_id'=>$this->city]);
        $this->deal=$this->completedDeal();
    }
    private function completedDeal(): int
    {
        $snapshot=[];
        foreach (['sender'=>$this->senderListing,'carrier'=>$this->listing] as $side=>$id) {
            $listing=DB::table('post_ads')->find($id);
            $snapshot[$side]=['id'=>$id,'created_by'=>$listing->created_by,'ad_type'=>$listing->ad_type,'title'=>$listing->title,
                'display_name'=>$side==='sender'?'Actor':'Owner'];
        }
        return DB::table('listing_deals')->insertGetId(['sender_listing_id'=>$this->senderListing,'carrier_listing_id'=>$this->listing,
            'sender_user_id'=>$this->actor->id,'carrier_user_id'=>$this->owner->id,'initiated_by'=>$this->actor->id,
            'requested_kg'=>5,'agreed_kg'=>5,'status'=>'completed','listing_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR),
            'confirmed_at'=>now()->subHours(2),'picked_up_at'=>now()->subHour(),'completed_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
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
        return array_replace(['deal_id'=>$this->deal,'rating'=>5,'review'=>'Helpful communication.'],$extra);
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
        $this->getJson($this->base.'/given',$this->headers())->assertJsonCount(2,'data.items')
            ->assertJsonPath('data.items.0.review',null)->assertJsonPath('data.items.0.created_at',null)->assertJsonPath('data.items.0.rating',0);
        $this->getJson($this->base.'/given',$this->headers())->assertJsonPath('data.items.1.listing.title',null);
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
    public function test_form_rating_values_store_exactly_for_completed_deal_without_chat_requirements(int $rating): void
    {
        // The approved completed-deal rule replaces public-visibility-only submission.
        $before=DB::table('post_ads')->get()->toJson();
        $deals=DB::table('listing_deals')->get()->toJson();
        $notices=DB::table('deal_notifications')->get()->toJson();
        $response=$this->postJson($this->base,$this->body(['rating'=>$rating]),$this->headers())->assertCreated()
            ->assertJsonPath('data.reviewer.id',$this->actor->id)->assertJsonPath('data.reviewed_customer.id',$this->owner->id)
            ->assertJsonPath('data.rating',$rating)->assertJsonPath('data.date_time','2026-11-14');
        $row=DB::table('reviews')->where('id',$response->json('data.id'))->first();
        $this->assertEqualsCanonicalizing(['id','deal_id','listing','reviewer','reviewed_customer','rating','review','date_time','created_at','updated_at'],
            array_keys($response->json('data')));
        $this->assertSame($rating,$row->rating);$this->assertSame($this->actor->id,$row->from_user_id);
        $this->assertSame($this->owner->id,$row->to_user_id);$this->assertSame(0,DB::table('post_ad_rooms')->count());
        $this->assertSame(0,DB::table('post_ad_room_messages')->count());$this->assertSame($before,DB::table('post_ads')->get()->toJson());
        $this->assertSame($deals,DB::table('listing_deals')->get()->toJson());
        $this->assertSame($notices,DB::table('deal_notifications')->get()->toJson());
        $this->assertSame((float)$rating,(float)DB::table('reviews')->where('to_user_id',$this->owner->id)->avg('rating'));
        Mail::assertNothingSent();Notification::assertNothingSent();Queue::assertNothingPushed();
    }
    public static function invalid(): array
    {
        return [[['rating'=>-1]],[['rating'=>6]],[['rating'=>2.5]],[['rating'=>[]]],[['rating'=>null]],
            [['review'=>'']],[['review'=>'  ']],[['review'=>[]]],[['review'=>null]],[['review'=>"bad\0value"]],
            [['deal_id'=>0]],[['deal_id'=>'1 OR 1=1']],[['post_ads_id'=>1]],[['from_user_id'=>1]],[['to_user_id'=>1]],
            [['reviewer_id'=>1]],[['customer_id'=>1]],[['user_id'=>1]],[['isApproved'=>1]],[['attachment'=>'x']]];
    }
    #[DataProvider('invalid')]
    public function test_invalid_rating_text_and_impersonation_cannot_write(array $change): void
    {
        $this->postJson($this->base,$this->body($change),$this->headers())->assertUnprocessable();
        $this->assertSame(0,DB::table('reviews')->count());
    }
    public static function required(): array {return [['rating'],['review'],['deal_id']];}
    #[DataProvider('required')]
    public function test_required_fields(string $field): void
    {
        $data=$this->body();unset($data[$field]);$this->postJson($this->base,$data,$this->headers())->assertUnprocessable();
    }
    public function test_missing_deal_self_review_and_duplicate_are_rejected_and_listing_owner_is_not_trusted(): void
    {
        $this->postJson($this->base,$this->body(['deal_id'=>999999]),$this->headers())->assertNotFound();
        DB::table('post_ads')->where('id',$this->listing)->update(['created_by'=>999999]);
        DB::table('listing_deals')->where('id',$this->deal)->update(['carrier_user_id'=>$this->actor->id]);
        $this->postJson($this->base,$this->body(),$this->headers())->assertStatus(409);
        DB::table('listing_deals')->where('id',$this->deal)->update(['carrier_user_id'=>$this->owner->id]);
        $this->postJson($this->base,$this->body(),$this->headers())->assertCreated()->assertJsonPath('data.reviewed_customer.id',$this->owner->id);
        $this->postJson($this->base,$this->body(),$this->headers())->assertStatus(409);
        $this->assertSame(1,DB::table('reviews')->count());
    }
    public function test_no_invented_target_account_status_rule_or_longtext_business_cap_for_public_listing(): void
    {
        $this->owner->forceFill(['isActive'=>0,'isVerified'=>0])->save();
        $text=str_repeat('a',11000);
        $this->postJson($this->base,$this->body(['review'=>$text]),$this->headers())->assertCreated()->assertJsonPath('data.review',$text);
    }
    public function test_duplicate_post_does_not_overwrite_rating_and_another_completed_deal_is_independent(): void
    {
        $this->postJson($this->base,$this->body(),$this->headers())->assertCreated();
        $this->postJson($this->base,$this->body(['rating'=>1,'review'=>'Different']),$this->headers())->assertStatus(409);
        $this->assertSame(5,DB::table('reviews')->first()->rating);
        $otherDeal=$this->completedDeal();
        $this->postJson($this->base,$this->body(['deal_id'=>$otherDeal]),$this->headers())->assertCreated();
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
    public function test_noncompleted_nonpublic_target_is_identical_to_missing_and_never_writes_or_discloses_metadata(string $condition): void
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
        DB::table('listing_deals')->where('id',$this->deal)->update(['status'=>'pending','completed_at'=>null]);
        $before=[];foreach (['post_ads','reviews','post_ad_rooms','post_ad_room_messages','users'] as $table) {
            $before[$table]=DB::table($table)->get()->toJson();
        }
        $missing=$this->postJson($this->base,$this->body(['deal_id'=>999999]),$this->headers())->assertNotFound()
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

    public function test_completed_deal_authorization_precedes_duplicate_and_self_checks_without_existing_row_changes(): void
    {
        $this->review($this->actor,$this->owner);
        DB::table('post_ads')->where('id',$this->listing)->update(['isActive'=>0]);
        DB::table('listing_deals')->where('id',$this->deal)->update(['status'=>'pending']);
        $before=DB::table('reviews')->get()->toJson();
        $missing=$this->postJson($this->base,$this->body(['deal_id'=>999999]),$this->headers())->assertNotFound();
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
        $this->postJson($this->base,$this->body(),$this->headers())->assertCreated();
        $otherToken=$this->other->createToken('mobile',['mobile:account'])->plainTextToken;
        foreach (['given','received'] as $action) $this->getJson($this->base.'/'.$action,$this->headers($otherToken))
            ->assertOk()->assertJsonPath('data.items',[]);
        $this->assertSame($before,DB::table('reviews')->whereNull('deal_id')->get()->toJson());
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
        $missing=$this->postJson($this->base,$this->body(['deal_id'=>999999,'rating'=>6]),$this->headers())->assertUnprocessable();
        $private=$this->postJson($this->base,$this->body(['rating'=>6]),$this->headers())->assertUnprocessable();
        $this->assertSame($missing->getContent(),$private->getContent());
        $this->assertSame(0,DB::table('reviews')->count());
    }

    public function test_both_participants_review_the_counterpart_listing_and_deleted_expired_history_uses_snapshots(): void
    {
        DB::table('post_ads')->whereIn('id',[$this->listing,$this->senderListing])->update(['isActive'=>2,'departure_on'=>'2000-01-01']);
        $this->postJson($this->base,$this->body(['rating'=>0]),$this->headers())->assertCreated()
            ->assertJsonPath('data.listing.id',$this->listing)->assertJsonPath('data.deal_id',$this->deal);
        DB::table('post_ads')->whereIn('id',[$this->listing,$this->senderListing])->delete();
        $ownerToken=$this->owner->createToken('mobile',['mobile:account'])->plainTextToken;
        $this->postJson($this->base,$this->body(),$this->headers($ownerToken))->assertCreated()
            ->assertJsonPath('data.reviewer.id',$this->owner->id)->assertJsonPath('data.reviewed_customer.id',$this->actor->id)
            ->assertJsonPath('data.listing.id',$this->senderListing)->assertJsonPath('data.listing.title','Sender parcel');
        $this->getJson($this->base.'/received',$this->headers())->assertOk()->assertJsonPath('data.items.0.listing.title','Sender parcel');
        $this->getJson($this->base.'/given',$this->headers())->assertOk()->assertJsonPath('data.items.0.listing.title','Parcel capacity');
        $this->assertSame(2,DB::table('reviews')->where('deal_id',$this->deal)->count());
        $this->getJson('/api/v1/listings/'.$this->listing.'/reviews')->assertNotFound();
    }

    public static function incompleteDeals(): array {return [['pending'],['accepted'],['confirmed'],['rejected'],['cancelled'],['expired']];}

    #[DataProvider('incompleteDeals')]
    public function test_only_completed_deal_participants_may_submit_and_missing_is_indistinguishable(string $status): void
    {
        DB::table('listing_deals')->where('id',$this->deal)->update(['status'=>$status]);
        $missing=$this->postJson($this->base,$this->body(['deal_id'=>999999]),$this->headers())->assertNotFound();
        $denied=$this->postJson($this->base,$this->body(),$this->headers())->assertNotFound();
        $this->assertSame($missing->getContent(),$denied->getContent());
        DB::table('listing_deals')->where('id',$this->deal)->update(['status'=>'completed']);
        $otherToken=$this->other->createToken('mobile',['mobile:account'])->plainTextToken;
        $this->postJson($this->base,$this->body(),$this->headers($otherToken))->assertNotFound()->assertExactJson(['success'=>false,'message'=>'Resource not found']);
        $this->assertSame(0,DB::table('reviews')->count());
    }

    public function test_public_listing_reviews_exclude_legacy_noncompleted_mismatched_and_other_listing_ratings(): void
    {
        $one=$this->postJson($this->base,$this->body(['rating'=>0]),$this->headers())->assertCreated()->json('data.id');
        $second=$this->completedDeal();
        $two=$this->postJson($this->base,$this->body(['deal_id'=>$second,'rating'=>5]),$this->headers())->assertCreated()->json('data.id');
        $this->review($this->other,$this->owner,['rating'=>100]); // Legacy ratings are preserved, never public aggregates.
        $pending=$this->completedDeal(); DB::table('listing_deals')->where('id',$pending)->update(['status'=>'pending']);
        $this->review($this->actor,$this->owner,['rating'=>5,'deal_id'=>$pending]);
        $bad=$this->completedDeal();
        $this->review($this->other,$this->owner,['rating'=>5,'deal_id'=>$bad]);
        $this->review($this->owner,$this->actor,['rating'=>5,'deal_id'=>$this->deal,'post_ads_id'=>$this->senderListing]);
        $before=DB::table('reviews')->get()->toJson();
        DB::connection()->enableQueryLog(); DB::connection()->flushQueryLog();
        $body=$this->getJson('/api/v1/listings/'.$this->listing.'/reviews?per_page=1')->assertOk()
            ->assertJsonPath('data.review_count',2)->assertJsonPath('data.average_rating',2.5)
            ->assertJsonPath('data.pagination.total',2)->assertJsonPath('data.items.0.id',$two)
            ->assertJsonPath('data.items.0.reviewer.display_name','Actor')->getContent();
        foreach (DB::connection()->getQueryLog() as $q) $this->assertMatchesRegularExpression('/^select\\b/i',$q['query']);
        $this->getJson('/api/v1/listings/'.$this->listing.'/reviews?per_page=1&page=2')->assertOk()->assertJsonPath('data.items.0.id',$one);
        foreach (['deal_id','reviewed_customer','post_ads_id','phone','email','latitude','longitude','address','listing_snapshot','private@example.test'] as $key) $this->assertStringNotContainsString($key,$body);
        $this->getJson('/api/v1/listings/'.$this->senderListing.'/reviews')->assertOk()->assertJsonPath('data.review_count',1)->assertJsonPath('data.average_rating',5);
        $this->assertSame($before,DB::table('reviews')->get()->toJson());
    }

    public function test_public_empty_results_contact_text_redaction_missing_visibility_and_pagination_validation(): void
    {
        $url='/api/v1/listings/'.$this->listing.'/reviews';
        $this->getJson($url)->assertOk()->assertJsonPath('data.review_count',0)->assertJsonPath('data.average_rating',null)->assertJsonPath('data.items',[]);
        $this->postJson($this->base,$this->body(['review'=>'Call secret@example.test']),$this->headers())->assertCreated();
        $this->getJson($url)->assertOk()->assertJsonPath('data.items.0.review',null);
        foreach (['per_page=51','page=0','sort=x'] as $query) $this->getJson($url.'?'.$query)->assertUnprocessable();
        DB::table('post_ads')->where('id',$this->listing)->update(['isActive'=>0]);
        $missing=$this->getJson('/api/v1/listings/999999/reviews')->assertNotFound();
        $this->assertSame($missing->getContent(),$this->getJson($url)->assertNotFound()->getContent());
    }

    public function test_verified_reviews_retain_snapshot_names_after_account_deletion_and_do_not_follow_new_listing_owners(): void
    {
        $this->postJson($this->base,$this->body(),$this->headers())->assertCreated();
        DB::table('users')->where('id',$this->actor->id)->delete();
        $this->getJson('/api/v1/listings/'.$this->listing.'/reviews')->assertOk()->assertJsonPath('data.items.0.reviewer.display_name','Actor');
        DB::table('post_ads')->where('id',$this->listing)->update(['created_by'=>$this->other->id]);
        $this->getJson('/api/v1/listings/'.$this->listing.'/reviews')->assertOk()->assertJsonPath('data.review_count',0);
        $ownerToken=$this->owner->createToken('mobile',['mobile:account'])->plainTextToken;
        $this->getJson($this->base.'/received',$this->headers($ownerToken))->assertOk()->assertJsonPath('data.items.0.listing.title','Parcel capacity');
    }

    public function test_api_and_website_share_duplicate_enforcement_and_form_redirect_without_ui_changes(): void
    {
        $this->actingAs($this->actor,'web');
        $form=['post_ads_id'=>$this->listing,'from_user_id'=>$this->actor->id,'to_user_id'=>$this->owner->id,'rating'=>4,'review'=>'Useful trip.'];
        $response=(new \App\Http\Controllers\UserController)->postRateReview(\Illuminate\Http\Request::create('/user/post-review','POST',$form));
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class,$response);
        $this->assertSame('Your Review Post Successfullyl.',session('flash_message_success'));
        $this->assertSame($this->deal,DB::table('reviews')->first()->deal_id);
        $this->postJson($this->base,$this->body(),$this->headers())->assertStatus(409);
        $this->assertSame(1,DB::table('reviews')->count());
        Mail::assertNothingSent(); Notification::assertNothingSent();
    }

    public function test_website_spoofed_identities_noncompleted_and_ambiguous_selection_cannot_write(): void
    {
        $this->actingAs($this->actor,'web');
        $form=['post_ads_id'=>$this->listing,'from_user_id'=>$this->actor->id,'to_user_id'=>$this->owner->id,'rating'=>5,'review'=>'Helpful.'];
        foreach ([['from_user_id'=>$this->other->id],['to_user_id'=>$this->other->id],['post_ads_id'=>$this->senderListing]] as $change) {
            try { (new \App\Http\Controllers\UserController)->postRateReview(\Illuminate\Http\Request::create('/user/post-review','POST',array_replace($form,$change))); $this->fail('Invalid website ownership accepted.'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertContains($e->getStatusCode(),[403,404]); }
        }
        DB::table('listing_deals')->where('id',$this->deal)->update(['status'=>'confirmed']);
        try { (new \App\Http\Controllers\UserController)->postRateReview(\Illuminate\Http\Request::create('/user/post-review','POST',$form)); $this->fail('Uncompleted website review.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(404,$e->getStatusCode()); }
        DB::table('listing_deals')->where('id',$this->deal)->update(['status'=>'completed']); $this->completedDeal();
        try { (new \App\Http\Controllers\UserController)->postRateReview(\Illuminate\Http\Request::create('/user/post-review','POST',$form)); $this->fail('Ambiguous website deal guessed.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(409,$e->getStatusCode()); }
        $this->assertSame(0,DB::table('reviews')->count());
        (new \App\Http\Controllers\UserController)->postRateReview(\Illuminate\Http\Request::create('/user/post-review','POST',$form+['deal_id'=>$this->deal]));
        $this->assertSame(1,DB::table('reviews')->count());
    }

    public function test_review_unique_index_and_locked_deal_serialize_competing_submissions(): void
    {
        $config=DB::connection()->getConfig();
        $observer=new \PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'],
            $config['username'],$config['password'],[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);
        $checked=false;
        DB::listen(function ($query) use ($observer,&$checked) {
            if (!str_contains($query->sql,'`listing_deals`') || !str_contains(strtolower($query->sql),'for update')) return;
            $observer->beginTransaction();
            try { $observer->query('SELECT id FROM listing_deals WHERE id='.$this->deal.' FOR UPDATE NOWAIT'); $this->fail('Competing review bypassed deal lock.'); }
            catch (\PDOException $e) { $this->assertSame(1205,(int)$e->errorInfo[1]); $checked=true; }
            finally { $observer->rollBack(); }
        });
        $this->postJson($this->base,$this->body(),$this->headers())->assertCreated(); $this->assertTrue($checked);
        $row=(array)DB::table('reviews')->first(); unset($row['id']); DB::beginTransaction();
        try { DB::table('reviews')->insert($row); $this->fail('Duplicate deal review inserted.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertSame('23000',(string)$e->errorInfo[0]); }
        finally { DB::rollBack(); }
        $this->review($this->actor,$this->owner); $this->review($this->actor,$this->owner);
        $this->assertSame(2,DB::table('reviews')->whereNull('deal_id')->count());
    }

    public function test_public_review_queries_are_bounded_as_page_size_grows(): void
    {
        for ($i=0;$i<3;$i++) {
            $deal=$i===0?$this->deal:$this->completedDeal();
            $this->postJson($this->base,$this->body(['deal_id'=>$deal]),$this->headers())->assertCreated();
        }
        $counts=[];
        foreach ([1,3] as $size) {
            DB::connection()->enableQueryLog(); DB::connection()->flushQueryLog();
            $this->getJson('/api/v1/listings/'.$this->listing.'/reviews?per_page='.$size)->assertOk()->assertJsonCount($size,'data.items');
            $counts[]=count(DB::connection()->getQueryLog());
        }
        $this->assertSame($counts[0],$counts[1],'Public reviews must not query users/deals for every result.');
    }

    public function test_missing_review_migration_blocks_new_writes_and_public_results_but_legacy_history_remains(): void
    {
        $legacy=$this->review($this->actor,$this->owner);
        $schema=Schema::getFacadeRoot();
        Schema::shouldReceive('hasColumn')->andReturnUsing(fn($table,$column)=>$table==='reviews'&&$column==='deal_id'?false:$schema->hasColumn($table,$column));
        $this->postJson($this->base,$this->body(),$this->headers())->assertStatus(503);
        $this->getJson('/api/v1/listings/'.$this->listing.'/reviews')->assertStatus(503);
        $this->getJson($this->base.'/given',$this->headers())->assertOk()->assertJsonPath('data.items.0.id',$legacy)->assertJsonPath('data.items.0.deal_id',null);
        $this->assertSame(1,DB::table('reviews')->count());
    }

    public function test_website_registration_status_and_rating_checks_cannot_bypass_review_policy(): void
    {
        $this->actingAs($this->actor,'web');
        $form=['post_ads_id'=>$this->listing,'rating'=>5,'review'=>'Helpful.'];
        foreach (['isActive','isVerified'] as $field) {
            $this->actor->update([$field=>0]);
            try { (new \App\Http\Controllers\UserController)->postRateReview(\Illuminate\Http\Request::create('/user/post-review','POST',$form)); $this->fail('Ineligible website customer accepted.'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
            $this->actor->update([$field=>1]);
        }
        try { (new \App\Http\Controllers\UserController)->postRateReview(\Illuminate\Http\Request::create('/user/post-review','POST',array_replace($form,['rating'=>6]))); $this->fail('Website rating bypassed validation.'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('rating',$e->errors()); }
        $this->assertSame(0,DB::table('reviews')->count());
    }
}
