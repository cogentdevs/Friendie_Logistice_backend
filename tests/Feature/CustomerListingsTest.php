<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Api\CustomerListingImageService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerListingsTest extends TestCase
{
    use DatabaseTransactions;

    private User $customer;
    private User $other;
    private string $token;
    private int $category;
    private int $country;
    private int $otherCountry;
    private int $city;
    private int $otherCity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-13 12:00:00','UTC'));
        config(['api.public_base_url'=>'https://media.example.test']);
        Storage::fake('public');
        Role::findOrCreate('user','web');Role::findOrCreate('admin','web');
        $this->customer=User::factory()->create(['isActive'=>1,'isVerified'=>1,'phone'=>'+10000000001','email'=>'owner-private@example.test']);
        $this->customer->assignRole('user');
        $this->other=User::factory()->create(['isActive'=>1,'isVerified'=>1,'phone'=>'+10000000002','email'=>'other-private@example.test']);
        $this->other->assignRole('user');
        $this->token=$this->customer->createToken('mobile',['mobile:account'])->plainTextToken;
        $this->category=DB::table('categories')->insertGetId(['name'=>'Parcels','isActive'=>1]);
        $this->country=DB::table('countries')->insertGetId(['name'=>'Alpha','short_name'=>'AA','currency_name'=>'USD','isActive'=>1]);
        $this->otherCountry=DB::table('countries')->insertGetId(['name'=>'Beta','short_name'=>'BB','currency_name'=>'EUR','isActive'=>1]);
        $this->city=DB::table('cities')->insertGetId(['name'=>'Alpha City','country_id'=>$this->country,'isActive'=>1]);
        $this->otherCity=DB::table('cities')->insertGetId(['name'=>'Beta City','country_id'=>$this->otherCountry,'isActive'=>1]);
    }

    private function headers(?string $token=null):array
    {
        app('auth')->forgetGuards();
        return ['Authorization'=>'Bearer '.($token??$this->token)];
    }

    private function fields(array $changes=[]):array
    {
        return array_replace(['ad_type'=>'Carring','title'=>'Parcel capacity','description'=>'<p>Room for a parcel.</p>',
            'category_id'=>$this->category,'from_country_id'=>$this->country,'from_city_id'=>$this->city,'from_area'=>'Area 12-B',
            'to_country_id'=>$this->otherCountry,'to_city_id'=>$this->otherCity,'to_area'=>'District 7',
            'weight'=>5,'weight_price'=>20.5,'currency'=>'USD','departure_on'=>'2026-11-20','reached_on'=>'2026-11-21'], $changes);
    }

    private function listing(int $status=0,?User $owner=null,array $changes=[]):int
    {
        return DB::table('post_ads')->insertGetId(array_replace($this->fields(),['created_by'=>($owner??$this->customer)->id,
            'isActive'=>$status,'isFeatured'=>0,'show_contact_info'=>0,'image'=>'listing.png',
            'created_at'=>'2026-11-01 10:00:00','updated_at'=>'2026-11-01 10:00:00'],$changes));
    }

    public static function endpoints():array
    {
        return [['GET',''],['POST',''],['GET','/1'],['PUT','/1'],['DELETE','/1'],['POST','/1/image'],['DELETE','/1/image']];
    }

    #[DataProvider('endpoints')]
    public function test_all_routes_require_valid_eligible_customer_authentication(string $method,string $suffix):void
    {
        $url='/api/v1/customer/listings'.$suffix;
        $this->json($method,$url)->assertUnauthorized();
        $this->json($method,$url,[],$this->headers('invalid'))->assertUnauthorized();
        $revoked=$this->customer->createToken('revoked',['mobile:account']);$revoked->accessToken->delete();
        $this->json($method,$url,[],$this->headers($revoked->plainTextToken))->assertUnauthorized();
        foreach([['isActive'=>0],['isActive'=>1,'isVerified'=>0]]as$state){
            $this->customer->forceFill($state)->save();$this->json($method,$url,[],$this->headers())->assertForbidden();
        }
        $this->customer->forceFill(['isActive'=>1,'isVerified'=>1])->save();
        foreach([['admin'],['user','admin'],[]]as$roles){
            $this->customer->syncRoles($roles);$this->json($method,$url,[],$this->headers())->assertForbidden();
        }
        Mail::assertNothingSent();
    }

    public static function types():array {return [['Carring'],['Send']];}

    #[DataProvider('types')]
    public function test_create_both_types_assigns_owner_pending_status_and_is_not_public(string $type):void
    {
        $data=$this->fields(['ad_type'=>$type,'currency'=>' usd ']);
        $response=$this->postJson('/api/v1/customer/listings',$data,$this->headers())->assertCreated()
            ->assertJsonPath('data.status',0)->assertJsonPath('data.is_featured',false)->assertJsonPath('data.currency','USD')
            ->assertJsonPath('data.from_area','Area 12-B')->assertJsonPath('data.ad_type',$type)
            ->assertDontSee($this->customer->phone)->assertDontSee($this->customer->email)->assertDontSee($this->other->email)
            ->assertJsonMissingPath('data.created_by');
        $id=$response->json('data.id');$row=DB::table('post_ads')->find($id);
        $this->assertSame($this->customer->id,(int)$row->created_by);$this->assertSame(0,(int)$row->isActive);
        $this->assertSame(0,(int)$row->isFeatured);$this->assertSame(0,(int)$row->show_contact_info);
        $this->assertSame('listing.png',$row->image);
        $this->getJson('/api/v1/listings/'.$id)->assertNotFound();
        $this->getJson('/api/v1/listings')->assertOk()->assertJsonPath('data.items',[]);
        $this->getJson('/api/v1/home')->assertOk()->assertJsonPath('data.featured_listings',[])->assertJsonPath('data.latest_listings',[]);
        $this->getJson('/api/v1/customer/listings/'.$id,$this->headers())->assertOk()->assertJsonPath('data.id',$id);
        Mail::assertNothingSent();
    }

    public function test_optional_fields_and_historical_dates_follow_verified_form_without_new_type_requirements():void
    {
        $data=$this->fields(['departure_on'=>'2000-01-01','reached_on'=>'2000-01-01']);
        foreach(['weight','weight_price','description','from_area','to_area']as$key)unset($data[$key]);
        $this->postJson('/api/v1/customer/listings',$data,$this->headers())->assertCreated()
            ->assertJsonPath('data.weight',null)->assertJsonPath('data.weight_price',null)->assertJsonPath('data.status',0);
    }

    public static function requiredFields():array
    {
        return array_map(fn($key)=>[$key],['ad_type','title','category_id','from_country_id','from_city_id',
            'to_country_id','to_city_id','currency','departure_on','reached_on']);
    }

    #[DataProvider('requiredFields')]
    public function test_missing_required_fields_are_rejected(string $field):void
    {
        $data=$this->fields();unset($data[$field]);
        $this->postJson('/api/v1/customer/listings',$data,$this->headers())->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame(0,DB::table('post_ads')->count());
    }

    public static function invalidFields():array
    {
        return [[['ad_type'=>'Carrying']],[['category_id'=>99999999]],[['from_country_id'=>99999999]],[['to_city_id'=>99999999]],
            [['from_country_id'=>['bad']]],[['from_city_id'=>['bad']]],[['currency'=>'INVALID']],[['currency'=>str_repeat('A',12)]],
            [['weight'=>-1]],[['weight'=>1.5]],[['weight'=>2147483648]],[['weight_price'=>-1]],[['weight_price'=>'1e309']],
            [['departure_on'=>'tomorrow']],[['departure_on'=>'2026-02-30']],[['reached_on'=>'2026-11-19']],
            [['title'=>str_repeat('a',256)]],[['description'=>str_repeat('a',10001)]],[['from_area'=>str_repeat('a',256)]],
            [['user_id'=>1]],[['owner_id'=>1]],[['created_by'=>1]],[['isActive'=>1]],[['status'=>1]],
            [['created_at'=>'2026-01-01']],[['isVerified'=>1]],[['isFeatured'=>1]],
            [['approved_at'=>'2026-01-01']],[['image'=>'../other.jpg']],[['cur_image'=>'other.jpg']],[['show_contact_info'=>1]]];
    }

    #[DataProvider('invalidFields')]
    public function test_validation_rejects_invalid_values_and_privileged_injection(array $changes):void
    {
        $this->postJson('/api/v1/customer/listings',$this->fields($changes),$this->headers())->assertUnprocessable()
            ->assertJsonPath('success',false)->assertJsonPath('message','Validation failed');
        $this->assertSame(0,DB::table('post_ads')->count());
    }

    public function test_inactive_references_and_city_country_mismatches_are_rejected():void
    {
        $this->postJson('/api/v1/customer/listings',$this->fields(['from_city_id'=>$this->otherCity]),$this->headers())
            ->assertUnprocessable()->assertJsonValidationErrors('from_city_id');
        DB::table('categories')->where('id',$this->category)->update(['isActive'=>0]);
        $this->postJson('/api/v1/customer/listings',$this->fields(),$this->headers())->assertUnprocessable()->assertJsonValidationErrors('category_id');
        DB::table('categories')->where('id',$this->category)->update(['isActive'=>1]);
        DB::table('countries')->where('id',$this->country)->update(['isActive'=>0]);
        $this->postJson('/api/v1/customer/listings',$this->fields(),$this->headers())->assertUnprocessable()->assertJsonValidationErrors('from_country_id');
        DB::table('countries')->where('id',$this->country)->update(['isActive'=>1]);
        DB::table('cities')->where('id',$this->city)->update(['isActive'=>0]);
        $this->postJson('/api/v1/customer/listings',$this->fields(),$this->headers())->assertUnprocessable()->assertJsonValidationErrors('from_city_id');
    }

    public function test_owner_gets_all_statuses_and_private_editable_fields_with_select_only_queries():void
    {
        $ids=[];foreach([0,1,2]as$status)$ids[]=$this->listing($status,null,['departure_on'=>'2000-01-01']);
        $this->listing(0,$this->other);
        $before=DB::table('post_ads')->get()->toJson();$tokens=DB::table('personal_access_tokens')->get()->toJson();$composed=0;
        app('events')->listen('composing: *',function()use(&$composed){$composed++;});
        DB::enableQueryLog();DB::flushQueryLog();
        $this->getJson('/api/v1/customer/listings',$this->headers())->assertOk()->assertJsonPath('data.pagination.total',3)
            ->assertJsonPath('data.pagination.per_page',20)->assertDontSee($this->other->email)->assertDontSee($this->other->phone);
        foreach($ids as$id)$this->getJson('/api/v1/customer/listings/'.$id,$this->headers())->assertOk()
            ->assertJsonPath('data.from_area','Area 12-B')->assertJsonMissingPath('data.owner')->assertJsonMissingPath('data.image');
        foreach(DB::getQueryLog()as$query)$this->assertMatchesRegularExpression('/^select\b/i',$query['query']);
        $this->assertSame($before,DB::table('post_ads')->get()->toJson());$this->assertSame($tokens,DB::table('personal_access_tokens')->get()->toJson());
        $this->assertSame(0,$composed);
    }

    public function test_non_owned_and_missing_listings_return_404_for_every_resource_action():void
    {
        $id=$this->listing(0,$this->other);$before=(array)DB::table('post_ads')->find($id);
        foreach([$id,999999999]as$target){
            foreach([['GET',''],['PUT',''],['DELETE',''],['POST','/image'],['DELETE','/image']]as[$method,$suffix]){
                $this->json($method,'/api/v1/customer/listings/'.$target.$suffix,[],$this->headers())->assertNotFound();
            }
        }
        $this->assertSame($before,(array)DB::table('post_ads')->find($id));
    }

    public function test_owner_search_and_filters_remain_scoped_with_stable_pagination():void
    {
        $match=$this->listing(0,null,['title'=>'Pack%_! case','ad_type'=>'Send']);
        $this->listing(1,null,['title'=>'Pack%_! case','ad_type'=>'Send']);
        $this->listing(0,$this->other,['title'=>'Pack%_! case','ad_type'=>'Send']);
        $this->getJson('/api/v1/customer/listings?search='.rawurlencode('%_!').'&status=0&ad_type=Send',$this->headers())
            ->assertOk()->assertJsonPath('data.pagination.total',1)->assertJsonPath('data.items.0.id',$match);
        $this->getJson('/api/v1/customer/listings?search='.rawurlencode("%' OR 1=1 --"),$this->headers())->assertOk()->assertJsonPath('data.items',[]);
        $first=$this->getJson('/api/v1/customer/listings?per_page=1&sort=oldest',$this->headers())->assertOk()->json('data.items.0.id');
        $second=$this->getJson('/api/v1/customer/listings?per_page=1&page=2&sort=oldest',$this->headers())->assertOk()->json('data.items.0.id');
        $this->assertNotSame($first,$second);$this->assertSame($match,$first);
        foreach(['status=3','status[]=0','ad_type=Carrying','per_page=51','page=0','sort=title','all=1','owner_id=1']as$query){
            $this->getJson('/api/v1/customer/listings?'.$query,$this->headers())->assertUnprocessable();
        }
        $this->getJson('/api/v1/customer/listings/'.$match.'?owner_id=1',$this->headers())->assertUnprocessable();
    }

    public static function statuses():array {return [[0],[1],[2]];}

    #[DataProvider('statuses')]
    public function test_partial_updates_preserve_status_owner_featured_and_unrelated_data(int $status):void
    {
        $id=$this->listing($status,null,['isFeatured'=>1]);$before=(array)DB::table('post_ads')->find($id);
        $this->putJson('/api/v1/customer/listings/'.$id,['title'=>'Updated title'],$this->headers())->assertOk()
            ->assertJsonPath('data.title','Updated title')->assertJsonPath('data.status',$status)->assertJsonPath('data.is_featured',true);
        $after=(array)DB::table('post_ads')->find($id);
        foreach($before as$key=>$value)if(!in_array($key,['title','updated_at'],true))$this->assertSame($value,$after[$key],$key);
        $this->putJson('/api/v1/customer/listings/'.$id,['isActive'=>1],$this->headers())->assertUnprocessable();
        $this->putJson('/api/v1/customer/listings/'.$id,['created_by'=>$this->other->id],$this->headers())->assertUnprocessable();
        $this->putJson('/api/v1/customer/listings/'.$id,['isFeatured'=>0],$this->headers())->assertUnprocessable();
    }

    public function test_partial_updates_validate_cross_field_dependencies_and_never_republish_expired_records():void
    {
        $id=$this->listing(2,null,['departure_on'=>'2000-01-01','reached_on'=>'2000-01-02']);$before=(array)DB::table('post_ads')->find($id);
        $this->putJson('/api/v1/customer/listings/'.$id,['departure_on'=>'2026-11-20'],$this->headers())->assertUnprocessable();
        $this->assertSame($before,(array)DB::table('post_ads')->find($id));
        $this->putJson('/api/v1/customer/listings/'.$id,['from_country_id'=>$this->otherCountry],$this->headers())->assertUnprocessable();
        $this->putJson('/api/v1/customer/listings/'.$id,['departure_on'=>'2026-11-20','reached_on'=>'2026-11-21',
            'from_country_id'=>$this->otherCountry,'from_city_id'=>$this->otherCity],$this->headers())->assertOk()->assertJsonPath('data.status',2);
        $this->getJson('/api/v1/listings/'.$id)->assertNotFound();
        $this->putJson('/api/v1/customer/listings/'.$id,[],$this->headers())->assertUnprocessable();
        DB::table('post_ads')->where('id',$id)->update(['isActive'=>3]);
        $this->putJson('/api/v1/customer/listings/'.$id,['title'=>'Changed'],$this->headers())->assertStatus(409);
    }

    public function test_database_failure_rolls_back_create_and_update_without_mail_or_media_effects():void
    {
        $failInsert=true;$failUpdate=false;
        DB::connection()->beforeExecuting(function($query)use(&$failInsert,&$failUpdate){
            $query=strtolower($query);
            if(($failInsert&&str_starts_with($query,'insert into `post_ads`'))||($failUpdate&&str_starts_with($query,'update `post_ads`'))){
                $failInsert=false;$failUpdate=false;throw new \RuntimeException('synthetic listing database failure');
            }
        });
        $this->postJson('/api/v1/customer/listings',$this->fields(),$this->headers())->assertStatus(500)->assertDontSee('synthetic');
        $this->assertSame(0,DB::table('post_ads')->count());
        $id=$this->listing();$before=(array)DB::table('post_ads')->find($id);$failUpdate=true;
        $this->putJson('/api/v1/customer/listings/'.$id,['title'=>'Changed'],$this->headers())->assertStatus(500);
        $this->assertSame($before,(array)DB::table('post_ads')->find($id));Mail::assertNothingSent();
    }

    public function test_destructive_deletion_is_explicitly_blocked_and_preserves_related_records_and_media():void
    {
        $id=$this->listing();$room=DB::table('post_ad_rooms')->insertGetId(['post_ads_id'=>$id,'from_user_id'=>$this->other->id,'to_user_id'=>$this->customer->id]);
        DB::table('post_ad_room_messages')->insert(['post_ad_room_id'=>$room,'from_user_id'=>$this->other->id,'to_user_id'=>$this->customer->id,
            'message'=>'Private chat','message_on'=>'2026-11-13']);
        DB::table('reviews')->insert(['post_ads_id'=>$id,'from_user_id'=>$this->other->id,'to_user_id'=>$this->customer->id,'rating'=>4,'date_time'=>'2026-11-13']);
        file_put_contents(public_path('frontend/img/listing/listing.png'),'shared-default-canary');
        $snapshot=[];foreach(['post_ads','post_ad_rooms','post_ad_room_messages','reviews','users']as$table)$snapshot[$table]=DB::table($table)->get()->toJson();
        $this->deleteJson('/api/v1/customer/listings/'.$id,[],$this->headers())->assertStatus(409)->assertJsonPath('success',false);
        foreach($snapshot as$table=>$value)$this->assertSame($value,DB::table($table)->get()->toJson());
        $this->assertSame('shared-default-canary',file_get_contents(public_path('frontend/img/listing/listing.png')));
        $unreferenced=$this->listing();
        $this->deleteJson('/api/v1/customer/listings/'.$unreferenced,[],$this->headers())->assertStatus(409);
        $this->assertNotNull(DB::table('post_ads')->find($unreferenced));
    }

    public static function imageTypes():array {return [['jpg'],['png'],['webp']];}

    #[DataProvider('imageTypes')]
    public function test_images_are_reencoded_in_existing_directory_and_replacement_removal_preserve_files(string $extension):void
    {
        $id=$this->listing(1);$other=$this->listing(0,$this->other);$root=public_path('frontend/img/listing');
        file_put_contents($root.'/listing.png','shared-default-canary');
        $image=UploadedFile::fake()->image('photo.'.$extension,800,600);
        file_put_contents($image->getPathname(),'<?php LISTING_EXECUTABLE_CANARY ?>',FILE_APPEND);
        $response=$this->post('/api/v1/customer/listings/'.$id.'/image',['image'=>$image],$this->headers()+['Host'=>'attacker.invalid'])->assertOk();
        $name=DB::table('post_ads')->where('id',$id)->value('image');
        $this->assertMatchesRegularExpression('/\Aapi_listing_'.$id.'_[a-f0-9]{32}\.jpg\z/',$name);
        $this->assertSame('https://media.example.test/frontend/img/listing/'.$name,$response->json('data.image_url'));
        $size=getimagesize($root.'/'.$name);$this->assertSame('image/jpeg',$size['mime']);
        $this->assertLessThanOrEqual(420,$size[0]);$this->assertLessThanOrEqual(340,$size[1]);
        $this->assertStringNotContainsString('LISTING_EXECUTABLE_CANARY',file_get_contents($root.'/'.$name));
        $this->post('/api/v1/customer/listings/'.$id.'/image',['image'=>UploadedFile::fake()->image('replacement.png')],$this->headers())->assertOk();
        $replacement=DB::table('post_ads')->where('id',$id)->value('image');$this->assertNotSame($name,$replacement);$this->assertFileExists($root.'/'.$name);
        $this->deleteJson('/api/v1/customer/listings/'.$id.'/image',[],$this->headers())->assertOk();
        $this->assertFileExists($root.'/'.$replacement);$this->assertSame('listing.png',DB::table('post_ads')->where('id',$id)->value('image'));
        $this->assertSame('listing.png',DB::table('post_ads')->where('id',$other)->value('image'));
        $this->assertSame(1,(int)DB::table('post_ads')->where('id',$id)->value('isActive'));
        $this->assertSame('shared-default-canary',file_get_contents($root.'/listing.png'));
        DB::table('post_ads')->where('id',$id)->update(['image'=>'missing.jpg']);
        $this->deleteJson('/api/v1/customer/listings/'.$id.'/image',[],$this->headers())->assertOk();
        $this->assertStringNotContainsString(str_replace('\\','/',base_path('public')),$root);
    }

    public function test_invalid_images_and_storage_database_failures_leave_original_reference_intact():void
    {
        $id=$this->listing();$url='/api/v1/customer/listings/'.$id.'/image';
        foreach([UploadedFile::fake()->createWithContent('fake.jpg','<?php invalid ?>'),UploadedFile::fake()->image('fake.php'),
            UploadedFile::fake()->image('large.jpg')->size(2049),UploadedFile::fake()->image('wide.png',2049,1)]as$file){
            $this->post($url,['image'=>$file],$this->headers())->assertUnprocessable()->assertJsonValidationErrors('image');
        }
        $this->mock(CustomerListingImageService::class,function($mock){$mock->shouldReceive('store')->once()->andThrow(new \RuntimeException('synthetic storage failure'));});
        $this->post($url,['image'=>UploadedFile::fake()->image('photo.png')],$this->headers())->assertStatus(500);
        $this->assertSame('listing.png',DB::table('post_ads')->where('id',$id)->value('image'));
        $this->app->forgetInstance(CustomerListingImageService::class);
        $fail=true;DB::connection()->beforeExecuting(function($query)use(&$fail){
            if($fail&&str_starts_with(strtolower($query),'update `post_ads`')){$fail=false;throw new \RuntimeException('synthetic image database failure');}
        });
        $this->post($url,['image'=>UploadedFile::fake()->image('photo.png')],$this->headers())->assertStatus(500)->assertDontSee('synthetic');
        $this->assertSame('listing.png',DB::table('post_ads')->where('id',$id)->value('image'));
        $this->assertSame([],glob(public_path('frontend/img/listing/api_listing_'.$id.'_*')));
    }

    public function test_mutation_limits_and_no_blade_or_mail_side_effects():void
    {
        $composed=0;app('events')->listen('composing: *',function()use(&$composed){$composed++;});
        for($i=0;$i<10;$i++)$this->postJson('/api/v1/customer/listings',[],$this->headers())->assertUnprocessable();
        $this->postJson('/api/v1/customer/listings',$this->fields(),$this->headers())->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame(0,$composed);Mail::assertNothingSent();
    }

    public function test_description_policy_preserves_approved_formatting_and_rejects_active_content(): void
    {
        $safe='<p>Unicode ???? &amp; text<br /><strong>B</strong><b>B</b><em>I</em><i>I</i><u>U</u><s>S</s></p><h2>H</h2><h3>H</h3><blockquote>Quote</blockquote><ul><li>A</li></ul><ol><li>B</li></ol><a href="https://example.test/path">HTTPS</a><a href="http://example.test">HTTP</a>';
        $html=\App\Support\ListingDescription::html($safe);
        foreach(['p','br','strong','b','em','i','u','s','h2','h3','blockquote','ul','ol','li','a'] as $tag)$this->assertStringContainsString('<'.$tag,$html);
        $this->assertStringContainsString('????',$html);
        $this->assertStringContainsString('href="https://example.test/path"',$html);
        $this->assertStringContainsString('href="http://example.test"',$html);
        $this->assertSame($html,\App\Support\ListingDescription::html($html));
        $this->assertNull(\App\Support\ListingDescription::html(null));
        $this->assertSame('',\App\Support\ListingDescription::html(''));
        foreach(['javascript:alert(1)','data:text/html,x','vbscript:x','file:///x','mailto:x@example.test','tel:123','/relative','//example.test/x','#anchor'] as $url){
            $result=\App\Support\ListingDescription::html('<a href="'.$url.'">label</a>');
            $this->assertStringNotContainsString('href=',$result);
        }
        foreach(['<script>alert(1)</script>','<p onclick="alert(1)" style="background:url(javascript:x)" class="x" id="x">text</p>',
            '<svg onload="alert(1)"><circle /></svg><math><mi>x</mi></math>','<iframe srcdoc="x"></iframe><object>x</object><embed src="x"><img src="x" onerror="x">',
            '<form><input><button>x</button></form><table><tr><td>x</td></tr></table>',
            '<a href="jav&#x61;script:alert(1)" onmouseover="x">x</a>','<p><b>broken</p></b>'] as $payload){
            $clean=\App\Support\ListingDescription::html($payload);
            $this->assertDoesNotMatchRegularExpression('/<(script|svg|math|iframe|object|embed|img|form|input|button|table)\b|\s(on\w+|style|class|id)=|javascript:/i',$clean);
            $this->assertSame($clean,\App\Support\ListingDescription::html($clean));
        }
        foreach(['&lt;script&gt;alert(1)&lt;/script&gt;','&amp;lt;script&amp;gt;x&amp;lt;/script&amp;gt;'] as $encoded){
            $clean=\App\Support\ListingDescription::html($encoded);
            $this->assertStringNotContainsString('<script',$clean);
            $this->assertSame($clean,\App\Support\ListingDescription::html($clean));
        }
    }

    public function test_api_description_sanitization_preserves_partial_null_and_empty_contracts(): void
    {
        $result=$this->postJson('/api/v1/customer/listings',$this->fields(['description'=>'<p onclick="x">Safe<script>x</script></p>']),$this->headers())->assertCreated();
        $id=$result->json('data.id');
        $this->assertSame('<p>Safe</p>',DB::table('post_ads')->where('id',$id)->value('description'));
        $result->assertJsonPath('data.description','<p>Safe</p>')->assertJsonPath('data.status',0);
        $this->putJson('/api/v1/customer/listings/'.$id,['title'=>'changed'],$this->headers())->assertOk()->assertJsonPath('data.description','<p>Safe</p>');
        $this->putJson('/api/v1/customer/listings/'.$id,['description'=>'<h2>Good</h2><iframe>x</iframe>'],$this->headers())->assertOk()->assertJsonPath('data.description','<h2>Good</h2>x');
        foreach([null,''] as $value)$this->putJson('/api/v1/customer/listings/'.$id,['description'=>$value],$this->headers())->assertOk()->assertJsonPath('data.description',null);
    }

    public function test_customer_create_customer_edit_and_admin_edit_keep_existing_redirects_and_fields(): void
    {
        $this->actingAs($this->customer,'web');
        $controller=new \App\Http\Controllers\PostAdsController();
        $fields=$this->fields(['description'=>'<p>Safe<script>x</script></p>'])+['show_contact_info'=>0];
        $request=\Illuminate\Http\Request::create('/user/add-listing','POST',$fields);
        $response=$controller->addPostAds($request);
        $this->assertStringEndsWith('/user/my-listing',$response->getTargetUrl());
        $row=DB::table('post_ads')->where('created_by',$this->customer->id)->orderByDesc('id')->first();
        $this->assertSame('<p>Safe</p>',$row->description);$this->assertSame(0,(int)$row->isActive);$this->assertSame('listing.png',$row->image);
        $this->other->syncRoles(['admin']);
        foreach([$this->customer,$this->other] as $actor){
            $this->actingAs($actor,'web');
            $input=$fields+['cur_image'=>'listing.png','isActive'=>1];$input['description']='<h3>Edited</h3><img src="x">';
            $reply=$controller->editPostAds(\Illuminate\Http\Request::create('/user/edit-listing/'.$row->id,'POST',$input),$row->id);
            $this->assertStringEndsWith((int)$actor->id === 1 ? '/admin/view-listing' : '/user/my-listing', $reply->getTargetUrl());
            $updated=DB::table('post_ads')->where('id',$row->id)->first();
            $this->assertSame('<h3>Edited</h3>',$updated->description);$this->assertSame(1,(int)$updated->isActive);
            $this->assertSame($row->image,$updated->image);$this->assertSame($row->created_by,$updated->created_by);
        }
    }

    public function test_actual_description_sink_expressions_are_safe_and_preview_limits_preserved(): void
    {
        $listing=(object)['description'=>'<p>Historical<script>alert(1)</script><img src=x onerror=x></p>&lt;script&gt;text&lt;/script&gt;'];
        foreach(['resources/views/frontend/listing-details.blade.php','resources/views/frontend/user/my-listing.blade.php','resources/views/admin/listing/view_listing.blade.php'] as $path){
            $source=file_get_contents(base_path($path));
            preg_match('/^.*ListingDescription::.*$/m',$source,$match);$this->assertNotEmpty($match);
            $this->assertStringNotContainsString('html_entity_decode',$match[0]);
            $rendered=\Illuminate\Support\Facades\Blade::render($match[0],['listing'=>$listing,'data'=>$listing]);
            $this->assertStringNotContainsString('<script',$rendered);$this->assertStringNotContainsString('<img',$rendered);
            $this->assertStringContainsString('Historical',$rendered);
        }
        foreach([100,150] as $width){
            $text=\App\Support\ListingDescription::preview('<p>'.str_repeat('x',200).'</p>', $width);
            $this->assertSame($width,mb_strwidth($text));$this->assertStringEndsWith('...',$text);
        }
        $this->assertSame('one two three',\App\Support\ListingDescription::preview('<p>one</p><p>two<br>three</p>',150));
    }
}
