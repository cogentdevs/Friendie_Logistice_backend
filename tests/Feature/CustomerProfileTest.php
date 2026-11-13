<?php

namespace Tests\Feature;

use App\Mail\MobileAccountMail;
use App\Models\User;
use App\Services\Api\CustomerAvatarService;
use App\Services\EmailActivationService;
use App\Services\WebPasswordResetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
{
    use DatabaseTransactions;

    private User $customer;
    private User $other;
    private int $country;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://account.example.test', 'api.public_base_url' => 'https://media.example.test']);
        Storage::fake('public');
        Role::findOrCreate('user', 'web');
        Role::findOrCreate('admin', 'web');
        $this->country = DB::table('countries')->insertGetId(['name' => 'Example Country', 'short_name' => 'EX', 'isActive' => 1]);
        $this->customer = User::factory()->create(['email' => 'owner@example.test', 'phone' => '+10000000001',
            'country_id' => $this->country, 'password' => Hash::make('CurrentPassword123!'), 'isActive' => 1, 'isVerified' => 1,
            'remember_token' => 'private-remember-canary', 'email_verified_at' => null, 'image' => 'avatar.jpg']);
        $this->customer->assignRole('user');
        $this->other = User::factory()->create(['email' => 'other-private@example.test', 'phone' => '+10000000002',
            'isActive' => 1, 'isVerified' => 1]);
        $this->other->assignRole('user');
        $this->token = $this->customer->createToken('mobile', ['mobile:account'])->plainTextToken;
        $this->customer->refresh();
        $this->other->refresh();
    }

    private function headers(?string $token = null): array
    {
        app('auth')->forgetGuards();

        return ['Authorization' => 'Bearer '.($token ?? $this->token)];
    }

    public static function endpoints(): array
    {
        return [['GET', 'dashboard'], ['GET', 'profile'], ['PUT', 'profile'], ['POST', 'profile/avatar'],
            ['DELETE', 'profile/avatar'], ['POST', 'change-password']];
    }

    #[DataProvider('endpoints')]
    public function test_every_endpoint_rejects_guests_invalid_revoked_and_ineligible_accounts(string $method, string $path): void
    {
        $url = '/api/v1/customer/'.$path;
        $this->json($method, $url)->assertUnauthorized()->assertJsonPath('success', false);
        $this->json($method, $url, [], $this->headers('invalid'))->assertUnauthorized();
        $revoked = $this->customer->createToken('revoked', ['mobile:account']);
        $revoked->accessToken->delete();
        $this->json($method, $url, [], $this->headers($revoked->plainTextToken))->assertUnauthorized();
        foreach ([['isActive' => 0], ['isActive' => 1, 'isVerified' => 0]] as $state) {
            $this->customer->forceFill($state)->save();
            $this->json($method, $url, [], $this->headers())->assertForbidden();
        }
        $this->customer->forceFill(['isActive' => 1, 'isVerified' => 1])->save();
        foreach ([['admin'], ['admin', 'user'], []] as $roles) {
            $this->customer->syncRoles($roles);
            $this->json($method, $url, [], $this->headers())->assertForbidden();
        }
        Mail::assertNothingSent();
    }

    private function listing(User $owner, int $status, string $departure = '2000-01-01'): int
    {
        $category = DB::table('categories')->insertGetId(['name' => 'Synthetic', 'isActive' => 1]);

        return DB::table('post_ads')->insertGetId(['category_id' => $category, 'ad_type' => 'Carring', 'title' => 'Synthetic',
            'description' => 'Synthetic', 'weight' => 1, 'weight_price' => 1, 'currency' => 'USD', 'show_contact_info' => 0,
            'departure_on' => $departure, 'reached_on' => $departure, 'isActive' => $status, 'isFeatured' => 0, 'created_by' => $owner->id]);
    }

    public function test_dashboard_scopes_stored_status_counts_and_authorized_unread_messages_without_any_writes(): void
    {
        $mine = $this->listing($this->customer, 1);
        $this->listing($this->customer, 0);
        $this->listing($this->customer, 2);
        $this->listing($this->other, 1);
        $room = DB::table('post_ad_rooms')->insertGetId(['post_ads_id' => $mine, 'from_user_id' => $this->other->id, 'to_user_id' => $this->customer->id]);
        $foreignRoom = DB::table('post_ad_rooms')->insertGetId(['post_ads_id' => $mine, 'from_user_id' => $this->other->id, 'to_user_id' => $this->other->id]);
        $message = ['post_ad_room_id' => $room, 'from_user_id' => $this->other->id,
            'to_user_id' => $this->customer->id, 'message' => 'private-message-canary', 'message_on' => '2026-11-13'];
        DB::table('post_ad_room_messages')->insert($message);
        DB::table('post_ad_room_messages')->insert(array_replace($message, ['read_by' => (string) $this->customer->id]));
        DB::table('post_ad_room_messages')->insert(array_replace($message, ['post_ad_room_id' => $foreignRoom]));
        DB::table('post_ad_room_messages')->insert(array_replace($message, ['from_user_id' => $this->customer->id, 'to_user_id' => $this->other->id]));
        DB::table('reviews')->insert(['post_ads_id' => $mine, 'from_user_id' => $this->other->id, 'to_user_id' => $this->customer->id,
            'rating' => 4, 'date_time' => '2026-11-13']);
        $composed = 0;
        app('events')->listen('composing: *', function () use (&$composed) { $composed++; });
        $tokenBefore = DB::table('personal_access_tokens')->get()->toJson();
        DB::enableQueryLog();DB::flushQueryLog();
        $this->getJson('/api/v1/customer/dashboard', $this->headers())->assertOk()->assertExactJson([
            'success' => true, 'message' => 'Dashboard retrieved successfully.', 'data' => [
                'listings' => ['total' => 3, 'published' => 1, 'pending' => 1, 'expired' => 1],
                'unread_messages' => 1, 'reviews_received' => 1,
            ],
        ])->assertDontSee('private-message-canary')->assertDontSee($this->other->email);
        foreach (DB::getQueryLog() as $query) {
            $this->assertMatchesRegularExpression('/^select\b/i', $query['query']);
        }
        $this->assertLessThanOrEqual(12, count(DB::getQueryLog()));
        $this->assertSame($tokenBefore, DB::table('personal_access_tokens')->get()->toJson());
        $this->assertSame(1, (int) DB::table('post_ads')->where('id', $mine)->value('isActive'));
        $this->assertSame(0, $composed);
    }

    public function test_empty_dashboard_and_own_profile_are_read_only_allowlisted_and_reject_account_selectors(): void
    {
        $this->getJson('/api/v1/customer/dashboard', $this->headers())->assertOk()->assertJsonPath('data.listings.total', 0)
            ->assertJsonPath('data.listings.published', 0)->assertJsonPath('data.unread_messages', 0);
        DB::enableQueryLog();DB::flushQueryLog();
        $profile = $this->getJson('/api/v1/customer/profile', $this->headers())->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('data.id', $this->customer->id)
            ->assertJsonPath('data.phone', $this->customer->phone)->assertJsonPath('data.email', $this->customer->email)
            ->assertJsonPath('data.country.id', $this->country)->assertJsonPath('data.is_verified', true)
            ->assertDontSee('private-remember-canary')->assertDontSee('password')->assertDontSee($this->other->phone)
            ->assertDontSee($this->other->email);
        $this->assertSame(['id','name','email','phone','country_id','country','image_url','is_verified','email_verified_at'], array_keys($profile->json('data')));
        foreach (DB::getQueryLog() as $query) {
            $this->assertMatchesRegularExpression('/^select\b/i', $query['query']);
        }
        $this->getJson('/api/v1/customer/profile?user_id='.$this->other->id, $this->headers())->assertUnprocessable();
        $this->getJson('/api/v1/customer/profile/'.$this->other->id, $this->headers())->assertNotFound();
        $otherToken = $this->other->createToken('mobile', ['mobile:account'])->plainTextToken;
        $this->getJson('/api/v1/customer/profile', $this->headers($otherToken))->assertOk()->assertJsonPath('data.id', $this->other->id)
            ->assertDontSee($this->customer->phone);
    }

    public function test_profile_updates_only_selected_fields_and_preserves_other_accounts_and_tokens(): void
    {
        $before = $this->customer->getAttributes();$otherBefore = $this->other->getAttributes();
        $this->putJson('/api/v1/customer/profile', ['name' => 'Updated Name', 'phone' => '+10000000003', 'country_id' => $this->country], $this->headers())
            ->assertOk()->assertJsonPath('data.profile.name', 'Updated Name')->assertJsonPath('data.verification_required', false);
        $after = $this->customer->fresh()->getAttributes();
        foreach ($before as $key => $value) {
            if (! in_array($key, ['name', 'phone'], true))$this->assertSame($value, $after[$key], $key);
        }
        $this->assertSame($otherBefore, $this->other->fresh()->getAttributes());
        $this->getJson('/api/v1/auth/me', $this->headers())->assertOk();
        Mail::assertNothingSent();
    }

    public static function invalidUpdates(): array
    {
        return [[[]], [['id'=>99]], [['user_id'=>99]], [['role'=>'admin']], [['roles'=>['admin']]],
            [['isActive'=>0]], [['isVerified'=>1]], [['email_verified_at'=>'2026-01-01']], [['password'=>'NewPassword123!']],
            [['image'=>'../other.jpg']], [['name'=>'']], [['phone'=>null]], [['phone'=>'+10000000002']],
            [['country_id'=>99999999]], [['email'=>'not-an-email']], [['email'=>'other-private@example.test']]];
    }

    #[DataProvider('invalidUpdates')]
    public function test_profile_rejects_invalid_privileged_and_cross_account_updates(array $data): void
    {
        $before = $this->customer->getAttributes();
        $this->putJson('/api/v1/customer/profile', $data, $this->headers())->assertUnprocessable()->assertJsonPath('success', false);
        $this->assertSame($before, $this->customer->fresh()->getAttributes());
        Mail::assertNothingSent();
    }

    public function test_email_change_reuses_shared_verification_revokes_all_own_tokens_and_preserves_web_session(): void
    {
        $oldLink = app(EmailActivationService::class)->link($this->customer);
        $oldSlug = hash('sha256', $this->customer->email);
        $this->customer->createToken('other-device', ['mobile:account']);
        $this->customer->createToken('legacy-wide', ['*']);
        $otherToken = $this->other->createToken('mobile', ['mobile:account']);
        $sessionKey = app('auth')->guard('web')->getName();$this->withSession([$sessionKey=>$this->customer->id]);
        $before = $this->customer->getAttributes();
        $this->putJson('/api/v1/customer/profile', ['email'=>'NEW@example.test'], $this->headers())->assertOk()
            ->assertJsonPath('data.verification_required', true)->assertJsonPath('data.reauthentication_required', true)
            ->assertJsonPath('data.profile.is_verified', false)->assertJsonPath('data.profile.email', 'new@example.test');
        $user = $this->customer->fresh();
        $this->assertSame(1,(int)$user->isActive);$this->assertSame(0,(int)$user->isVerified);$this->assertNull($user->email_verified_at);
        $this->assertSame($before['password'],$user->password);$this->assertSame($before['remember_token'],$user->remember_token);
        $this->assertSame(['user'],$user->getRoleNames()->all());
        $this->assertSame(0,$user->tokens()->count());$this->assertNotNull($otherToken->accessToken->fresh());
        $this->assertSame($user->id,session()->get($sessionKey));
        $this->assertSame('invalid',app(EmailActivationService::class)->inspect(Request::create($oldLink),(string)$user->id,$oldSlug));
        Mail::assertSent(MobileAccountMail::class,fn($mail)=>$mail->activation&&$mail->hasTo('new@example.test'));
        $mail=Mail::sent(MobileAccountMail::class)->sole();parse_str(parse_url($mail->data['activation_url'],PHP_URL_QUERY),$query);
        $this->assertSame(86400,(int)$query['expires']-(int)$query['issued']);
        $this->assertSame('pending',app(EmailActivationService::class)->inspect(Request::create($mail->data['activation_url']),(string)$user->id,hash('sha256',$user->email)));
        $this->getJson('/api/v1/customer/profile',$this->headers())->assertUnauthorized();
        // Even after re-verification an old token cannot regain access.
        $user->forceFill(['isVerified'=>1])->save();
        $this->getJson('/api/v1/auth/me',$this->headers())->assertUnauthorized();
    }

    public function test_case_only_email_change_needs_no_verification_and_mail_failure_does_not_restore_tokens(): void
    {
        $this->putJson('/api/v1/customer/profile',['email'=>'OWNER@EXAMPLE.TEST'],$this->headers())->assertOk()
            ->assertJsonPath('data.verification_required',false);
        Mail::assertNothingSent();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('synthetic mail failure'));
        $this->putJson('/api/v1/customer/profile',['email'=>'changed@example.test'],$this->headers())->assertOk()
            ->assertJsonPath('data.verification_required',true);
        $this->assertSame(0,$this->customer->tokens()->count());
        $this->assertSame(0,(int)$this->customer->fresh()->isVerified);
    }

    public function test_email_change_rolls_back_account_and_tokens_without_sending_mail_when_transaction_fails(): void
    {
        $before = $this->customer->fresh()->getAttributes();
        $fail = true;
        DB::connection()->beforeExecuting(function ($query) use (&$fail) {
            if ($fail && str_starts_with(strtolower($query), 'delete from `personal_access_tokens`')) {
                $fail = false;
                throw new \RuntimeException('synthetic token revocation failure');
            }
        });
        $this->putJson('/api/v1/customer/profile', ['email' => 'changed@example.test'], $this->headers())
            ->assertStatus(500)->assertJsonPath('message', 'Server error')->assertDontSee('synthetic');
        $this->assertSame($before, $this->customer->fresh()->getAttributes());
        $this->assertSame(1, $this->customer->tokens()->count());
        Mail::assertNothingSent();
    }

    public function test_avatar_upload_reencodes_in_isolated_public_directory_preserves_previous_and_removes_only_reference(): void
    {
        $root=public_path('frontend/img/profiles');
        file_put_contents($root.'/avatar.jpg','shared-default-canary');
        file_put_contents($root.'/legacy.jpg','legacy-canary');
        $this->customer->forceFill(['image'=>'legacy.jpg'])->save();
        $this->other->forceFill(['image'=>'legacy.jpg'])->save();
        $first=$this->post('/api/v1/customer/profile/avatar',['avatar'=>UploadedFile::fake()->image('photo.png',64,64)],$this->headers())
            ->assertOk()->assertJsonPath('success',true);
        $name=$this->customer->fresh()->image;
        $this->assertMatchesRegularExpression('/\Aapi_user_'.$this->customer->id.'_[a-f0-9]{32}\.jpg\z/',$name);
        $this->assertSame('https://media.example.test/frontend/img/profiles/'.$name,$first->json('data.image_url'));
        $this->assertSame('image/jpeg',mime_content_type($root.'/'.$name));
        $this->assertFileExists($root.'/legacy.jpg');
        $this->post('/api/v1/customer/profile/avatar',['avatar'=>UploadedFile::fake()->image('replacement.jpg',32,32)],$this->headers())->assertOk();
        $second=$this->customer->fresh()->image;$this->assertNotSame($name,$second);
        $this->assertFileExists($root.'/'.$name);
        $this->deleteJson('/api/v1/customer/profile/avatar',[],$this->headers())->assertOk();
        $this->assertSame('avatar.jpg',$this->customer->fresh()->image);
        $this->assertSame('legacy.jpg',$this->other->fresh()->image);
        $this->assertFileExists($root.'/'.$second);$this->assertSame('shared-default-canary',file_get_contents($root.'/avatar.jpg'));
        $this->deleteJson('/api/v1/customer/profile/avatar',[],$this->headers())->assertOk();
        $this->customer->forceFill(['image'=>'missing.jpg'])->save();
        $this->deleteJson('/api/v1/customer/profile/avatar',[],$this->headers())->assertOk();
        $this->assertStringNotContainsString(str_replace('\\','/',base_path('public')),$root);
    }

    public function test_avatar_rejects_fake_content_executable_extensions_size_and_dimensions(): void
    {
        $files=[UploadedFile::fake()->createWithContent('fake.jpg','<?php echo "unsafe";'),
            UploadedFile::fake()->image('executable.php',10,10),UploadedFile::fake()->image('large.png')->size(2049),
            UploadedFile::fake()->image('wide.png',2049,1),UploadedFile::fake()->createWithContent('vector.svg','<svg/>')];
        foreach($files as$file){$this->post('/api/v1/customer/profile/avatar',['avatar'=>$file],$this->headers())
            ->assertUnprocessable()->assertJsonValidationErrors('avatar');}
        $this->assertSame('avatar.jpg',$this->customer->fresh()->image);
        $this->assertSame([],glob(public_path('frontend/img/profiles/api_user_'.$this->customer->id.'_*')));
    }

    public static function validImages(): array
    {
        return [['jpg'], ['png'], ['webp']];
    }

    #[DataProvider('validImages')]
    public function test_approved_image_formats_are_reencoded_scaled_and_have_host_independent_profile_urls(string $extension): void
    {
        $image = UploadedFile::fake()->image('photo.'.$extension, 800, 600);
        file_put_contents($image->getPathname(), '<?php PHASE5_EXECUTABLE_CANARY ?>', FILE_APPEND);
        $this->post('/api/v1/customer/profile/avatar', ['avatar' => $image], $this->headers())->assertOk();
        $name = $this->customer->fresh()->image;
        $path = public_path('frontend/img/profiles/'.$name);
        $size = getimagesize($path);
        $this->assertSame('image/jpeg', $size['mime']);
        $this->assertLessThanOrEqual(512, $size[0]);
        $this->assertLessThanOrEqual(512, $size[1]);
        $this->assertStringNotContainsString('PHASE5_EXECUTABLE_CANARY', file_get_contents($path));
        $this->getJson('/api/v1/customer/profile', $this->headers() + ['Host' => 'attacker.invalid', 'X-Forwarded-Host' => 'attacker.invalid'])
            ->assertOk()->assertJsonPath('data.image_url', 'https://media.example.test/frontend/img/profiles/'.$name)
            ->assertDontSee('attacker.invalid');
    }

    public function test_missing_profile_directory_and_avatar_limit_fail_safely(): void
    {
        $before = $this->customer->fresh()->image;
        $public = public_path();
        $this->app->usePublicPath($public.'/nonexistent-synthetic-root');
        $this->post('/api/v1/customer/profile/avatar', ['avatar' => UploadedFile::fake()->image('photo.png')], $this->headers())
            ->assertStatus(500)->assertJsonPath('message', 'Server error')->assertDontSee('directory');
        $this->assertSame($before, $this->customer->fresh()->image);
        $this->app->usePublicPath($public);
        for ($i = 0; $i < 9; $i++) {
            $this->post('/api/v1/customer/profile/avatar', [], $this->headers())->assertUnprocessable();
        }
        $this->post('/api/v1/customer/profile/avatar', [], $this->headers())->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_avatar_storage_and_database_failures_preserve_old_reference_and_cleanup_only_new_file(): void
    {
        $original=$this->customer->image;
        $this->mock(CustomerAvatarService::class,function($mock){$mock->shouldReceive('store')->once()->andThrow(new \RuntimeException('synthetic storage failure'));});
        $this->post('/api/v1/customer/profile/avatar',['avatar'=>UploadedFile::fake()->image('photo.png')],$this->headers())->assertStatus(500)->assertDontSee('synthetic storage failure');
        $this->assertSame($original,$this->customer->fresh()->image);
        $this->app->forgetInstance(CustomerAvatarService::class);
        // Force a post-write DB rollback inside a savepoint; only the brand-new file may be discarded.
        $this->app->instance(CustomerAvatarService::class,new CustomerAvatarService);
        $fail=true;
        DB::connection()->beforeExecuting(function($query)use(&$fail){
            if($fail&&str_starts_with(strtolower($query),'update `users` set `image`')){
                $fail=false;throw new \RuntimeException('synthetic database failure');
            }
        });
        $this->post('/api/v1/customer/profile/avatar',['avatar'=>UploadedFile::fake()->image('photo.png')],$this->headers())->assertStatus(500)->assertDontSee('synthetic database failure');
        $this->assertSame($original,$this->customer->fresh()->image);
        $this->assertSame([],glob(public_path('frontend/img/profiles/api_user_'.$this->customer->id.'_*')));
    }

    public function test_api_email_and_avatar_operations_do_not_invoke_blade_composers(): void
    {
        $listing=$this->listing($this->customer,0);$before=(array)DB::table('post_ads')->find($listing);$composed=0;
        app('events')->listen('composing: *',function()use(&$composed){$composed++;});
        $this->post('/api/v1/customer/profile/avatar',['avatar'=>UploadedFile::fake()->image('photo.png')],$this->headers())->assertOk();
        $this->putJson('/api/v1/customer/profile',['email'=>'changed@example.test'],$this->headers())->assertOk();
        $mail=Mail::sent(MobileAccountMail::class)->sole();$this->assertStringContainsString('Verify your email',$mail->render());
        $this->assertSame(0,$composed);$this->assertSame($before,(array)DB::table('post_ads')->find($listing));
    }

    public function test_password_change_revokes_all_own_tokens_and_reset_proof_but_preserves_web_session_role_and_status(): void
    {
        $this->customer->createToken('other-device',['mobile:account']);$this->customer->createToken('wide',['*']);
        $otherToken=$this->other->createToken('mobile',['mobile:account']);
        $proof=null;app(WebPasswordResetService::class)->requestLink($this->customer->email,'user',function($email,$data)use(&$proof){$proof=substr($data['reset_url'],-64);});
        $sessionKey=app('auth')->guard('web')->getName();$this->withSession([$sessionKey=>$this->customer->id]);
        $before=$this->customer->getAttributes();
        $this->postJson('/api/v1/customer/change-password',['current_password'=>'CurrentPassword123!',
            'password'=>'NewPassword123!','password_confirmation'=>'NewPassword123!'],$this->headers())->assertOk()->assertJsonPath('data.reauthentication_required',true)
            ->assertDontSee('NewPassword123!')->assertDontSee('password_hash');
        $after=$this->customer->fresh()->getAttributes();$this->assertTrue(Hash::check('NewPassword123!',$after['password']));
        foreach($before as$key=>$value)if($key!=='password')$this->assertSame($value,$after[$key],$key);
        $this->assertSame(['user'],$this->customer->getRoleNames()->all());$this->assertSame(0,$this->customer->tokens()->count());
        $this->assertNotNull($otherToken->accessToken->fresh());$this->assertSame($this->customer->id,session()->get($sessionKey));
        $this->assertFalse(app(WebPasswordResetService::class)->valid((string)$this->customer->id,'user',$proof));
        $this->getJson('/api/v1/auth/me',$this->headers())->assertUnauthorized();
        $this->postJson('/api/v1/auth/login',['email'=>$this->customer->email,'password'=>'NewPassword123!'])->assertOk();
    }

    public static function invalidPasswords():array
    {
        return [[['current_password'=>'wrong']], [['password'=>'short','password_confirmation'=>'short']],
            [['password_confirmation'=>'wrong']], [['password'=>'CurrentPassword123!','password_confirmation'=>'CurrentPassword123!']],
            [['password'=>str_repeat('a',73),'password_confirmation'=>str_repeat('a',73)]], [['role'=>'admin']]];
    }

    #[DataProvider('invalidPasswords')]
    public function test_password_failures_preserve_hash_and_tokens(array $changes):void
    {
        $before=$this->customer->password;
        $data=array_replace(['current_password'=>'CurrentPassword123!','password'=>'NewPassword123!','password_confirmation'=>'NewPassword123!'],$changes);
        $this->postJson('/api/v1/customer/change-password',$data,$this->headers())->assertUnprocessable()->assertJsonPath('success',false);
        $this->assertSame($before,$this->customer->fresh()->password);$this->assertSame(1,$this->customer->tokens()->count());
    }

    public function test_password_and_profile_mutations_are_rate_limited():void
    {
        for($i=0;$i<5;$i++)$this->postJson('/api/v1/customer/change-password',[
            'current_password'=>'wrong','password'=>'NewPassword123!','password_confirmation'=>'NewPassword123!'],$this->headers())->assertUnprocessable();
        $this->postJson('/api/v1/customer/change-password',[],$this->headers())->assertStatus(429)->assertHeader('Retry-After');
        for($i=0;$i<10;$i++)$this->putJson('/api/v1/customer/profile',['name'=>'Updated'],$this->headers())->assertOk();
        $this->putJson('/api/v1/customer/profile',['name'=>'Updated'],$this->headers())->assertStatus(429);
    }
}
