<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\AuthController;
use App\Mail\MobileAccountMail;
use App\Models\User;
use App\Services\EmailActivationService;
use App\Services\WebPasswordResetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobileAuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://auth.example.test']);
        Role::findOrCreate('user', 'web');
        Role::findOrCreate('admin', 'web');
        // Only the test challenge verifier is replaced. Production still calls the CAPTCHA provider.
        Validator::extend('captcha', fn ($attribute, $value) => $value === 'synthetic-valid-challenge');
    }

    private function customer(array $changes = [], array $roles = ['user']): User
    {
        $user = User::factory()->create(array_replace([
            'email' => 'customer@example.test', 'password' => Hash::make('Password123!'),
            'isActive' => 1, 'isVerified' => 1,
        ], $changes));
        $user->syncRoles($roles);

        return $user;
    }

    private function registration(array $changes = []): array
    {
        $country = DB::table('countries')->insertGetId(['name' => 'Synthetic', 'short_name' => 'SY', 'isActive' => 1]);

        return array_replace(['name' => 'New Customer', 'email' => 'new@example.test', 'phone' => '+10000000001',
            'country_id' => $country, 'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'captcha_token' => 'synthetic-valid-challenge'], $changes);
    }

    private function bearer(string $token): array
    {
        // Simulate a fresh HTTP request; Laravel's test process otherwise retains resolved guards.
        app('auth')->forgetGuards();

        return ['Authorization' => 'Bearer '.$token];
    }

    private function proof(User $user, string $role = 'user'): string
    {
        $proof = null;
        app(WebPasswordResetService::class)->requestLink($user->email, $role,
            function ($email, $data) use (&$proof) { $proof = substr($data['reset_url'], -64); });
        $this->assertIsString($proof);

        return $proof;
    }

    public function test_registration_assigns_only_customer_role_and_requires_verification_without_tokens_or_session(): void
    {
        $data = $this->registration();
        $first = $this->postJson('/api/v1/auth/register', $data)->assertCreated()
            ->assertExactJson(['success' => true, 'message' => AuthController::REGISTERED, 'data' => []])
            ->assertHeaderMissing('Set-Cookie');
        $this->assertInstanceOf(\stdClass::class, json_decode($first->getContent())->data);
        $user = User::where('email', $data['email'])->sole();
        $this->assertSame(1, (int) $user->isActive);
        $this->assertSame(0, (int) $user->isVerified);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check($data['password'], $user->password));
        $this->assertSame(['user'], $user->getRoleNames()->all());
        $this->assertSame(0, $user->tokens()->count());
        Mail::assertSent(MobileAccountMail::class, fn ($mail) => $mail->activation && $mail->hasTo($data['email']));
        $this->postJson('/api/v1/auth/register', $data)->assertCreated()->assertExactJson($first->json());
        $this->assertSame(1, User::where('email', $data['email'])->count());
        Mail::assertSentCount(1);
        $this->assertGuest('web');
        $this->postJson('/api/v1/auth/login', ['email' => $data['email'], 'password' => $data['password']])->assertUnauthorized();
    }

    public static function badRegistrations(): array
    {
        return [[['name' => null]], [['email' => 'invalid']], [['phone' => null]], [['country_id' => 99999999]],
            [['password' => 'short', 'password_confirmation' => 'short']], [['password_confirmation' => 'different']],
            [['password' => str_repeat('a', 73), 'password_confirmation' => str_repeat('a', 73)]],
            [['captcha_token' => null]], [['captcha_token' => 'invalid']], [['role' => 'admin']],
            [['roles' => ['admin']]], [['isActive' => 1]], [['isVerified' => 1]], [['email_verified_at' => '2026-01-01']]];
    }

    #[DataProvider('badRegistrations')]
    public function test_registration_validation_and_injection_rejection(array $changes): void
    {
        $this->postJson('/api/v1/auth/register', $this->registration($changes))->assertUnprocessable()->assertJsonPath('success', false);
        $this->assertSame(0, User::count());
        Mail::assertNothingSent();
    }

    public function test_registration_rate_limit_and_mail_failure_leave_an_unverified_recoverable_account(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('synthetic transport failure'));
        $data = $this->registration();
        $this->postJson('/api/v1/auth/register', $data)->assertCreated();
        $this->assertSame(0, (int) User::where('email', $data['email'])->sole()->isVerified);
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/auth/register', $data)->assertCreated();
        }
        $this->postJson('/api/v1/auth/register', $data)->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_login_token_is_hashed_scoped_expiring_and_profile_is_allowlisted(): void
    {
        $user = $this->customer(['phone' => 'private-phone-canary', 'remember_token' => 'private-token-canary']);
        $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password123!'])
            ->assertOk()->assertJsonPath('data.token_type', 'Bearer')->assertHeaderMissing('Set-Cookie');
        $token = $response->json('data.token');
        $row = $user->tokens()->sole();
        $this->assertSame(hash('sha256', explode('|', $token, 2)[1]), $row->token);
        $this->assertSame(['mobile:account'], $row->abilities);
        $this->assertTrue($row->expires_at->isFuture());
        $this->assertGuest('web');
        $this->getJson('/api/v1/auth/me', $this->bearer($token))->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Account retrieved successfully.', 'data' => [
                'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            ]])->assertDontSee('private-phone-canary')->assertDontSee('private-token-canary')->assertDontSee('password');
        $this->assertSame(['id', 'name', 'email'], array_keys($response->json('data.user')));
    }

    public static function ineligibleLogins(): array
    {
        return [[[], ['user'], 'wrong'], [['isVerified' => 0], ['user'], 'Password123!'],
            [['isActive' => 0], ['user'], 'Password123!'], [[], ['admin'], 'Password123!'],
            [[], ['user', 'admin'], 'Password123!'], [[], [], 'Password123!']];
    }

    #[DataProvider('ineligibleLogins')]
    public function test_ineligible_credentials_receive_the_same_neutral_error(array $state, array $roles, string $password): void
    {
        $user = $this->customer($state, $roles);
        $expected = ['success' => false, 'message' => 'Invalid credentials.'];
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => $password])->assertUnauthorized()->assertExactJson($expected);
        $this->postJson('/api/v1/auth/login', ['email' => 'unknown@example.test', 'password' => $password])->assertUnauthorized()->assertExactJson($expected);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_login_rate_limiting_and_validation(): void
    {
        $this->postJson('/api/v1/auth/login', [])->assertUnprocessable();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'unknown@example.test', 'password' => 'wrong'])->assertUnauthorized();
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'unknown@example.test', 'password' => 'wrong'])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_logout_revokes_only_current_device_and_preserves_web_session_and_other_token(): void
    {
        $user = $this->customer();
        $current = $user->createToken('first', ['mobile:account']);
        $other = $user->createToken('other', ['mobile:account']);
        $sessionKey = app('auth')->guard('web')->getName();
        $this->withSession([$sessionKey => $user->id]);
        $this->postJson('/api/v1/auth/logout', [], $this->bearer($current->plainTextToken))->assertOk();
        $this->assertNull($current->accessToken->fresh());
        $this->assertNotNull($other->accessToken->fresh());
        // The web session guard is not logged out by the API.
        $this->assertSame($user->id, session()->get($sessionKey));
        $this->getJson('/api/v1/auth/me', $this->bearer($current->plainTextToken))->assertUnauthorized();
        $this->getJson('/api/v1/auth/me', $this->bearer($other->plainTextToken))->assertOk();
    }

    public function test_missing_invalid_expired_tokens_and_web_session_are_rejected(): void
    {
        $user = $this->customer();
        $this->actingAs($user, 'web');
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
        $this->getJson('/api/v1/auth/me', $this->bearer('invalid'))->assertUnauthorized();
        $expired = $user->createToken('expired', ['mobile:account'], now()->subSecond());
        $this->getJson('/api/v1/auth/me', $this->bearer($expired->plainTextToken))->assertUnauthorized();
        $scoped = $user->createToken('other-purpose', ['other']);
        $this->getJson('/api/v1/auth/me', $this->bearer($scoped->plainTextToken))->assertForbidden();
    }

    public static function revokedEligibility(): array
    {
        return [[['isActive' => 0], ['user']], [['isVerified' => 0], ['user']], [[], ['admin']], [[], ['user', 'admin']]];
    }

    #[DataProvider('revokedEligibility')]
    public function test_current_status_and_roles_are_enforced_for_existing_tokens(array $state, array $roles): void
    {
        $user = $this->customer();
        $token = $user->createToken('old', ['mobile:account'])->plainTextToken;
        $user->forceFill($state)->save();
        $user->syncRoles($roles);
        $this->getJson('/api/v1/auth/me', $this->bearer($token))->assertForbidden();
        $this->postJson('/api/v1/auth/logout', [], $this->bearer($token))->assertForbidden();
    }

    public function test_recovery_is_neutral_cooldown_applies_and_browser_link_proof_resets_once(): void
    {
        $user = $this->customer();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk()->assertJsonPath('message', AuthController::RECOVERY);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.test'])->assertOk()->assertJsonPath('message', AuthController::RECOVERY);
        Mail::assertSentCount(1);
        $mail = Mail::sent(MobileAccountMail::class)->sole();
        $this->assertFalse($mail->activation);
        $this->assertStringStartsWith('https://auth.example.test/reset-password/'.$user->id.'-', $mail->data['reset_url']);
        $token = substr($mail->data['reset_url'], -64);
        $this->assertTrue(app(WebPasswordResetService::class)->valid((string) $user->id, 'user', $token));
        $request = ['user_id' => $user->id, 'token' => $token, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'];
        $old = $user->getAttributes();
        $this->postJson('/api/v1/auth/reset-password', $request)->assertOk()->assertJsonMissingPath('data.token');
        $this->assertTrue(Hash::check($request['password'], $user->fresh()->password));
        foreach (['isActive', 'isVerified', 'remember_token', 'email'] as $field) {
            $this->assertSame($old[$field], $user->fresh()->getAttributes()[$field]);
        }
        $this->postJson('/api/v1/auth/reset-password', $request)->assertUnprocessable();
        $this->assertSame(0, $user->tokens()->count());
        $this->assertGuest('web');
    }

    public function test_reset_rejects_wrong_account_role_expiry_and_invalid_proof_without_changing_hash(): void
    {
        $user = $this->customer();
        $admin = $this->customer(['email' => 'admin@example.test'], ['admin']);
        $adminToken = $this->proof($admin, 'admin');
        $token = $this->proof($user);
        $base = ['user_id' => $user->id, 'token' => $token, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'];
        $this->postJson('/api/v1/auth/reset-password', array_replace($base, ['token' => str_repeat('0', 64)]))->assertUnprocessable();
        $this->postJson('/api/v1/auth/reset-password', array_replace($base, ['user_id' => $admin->id, 'token' => $adminToken]))->assertUnprocessable();
        $this->postJson('/api/v1/auth/reset-password', array_replace($base, ['token' => $adminToken]))->assertUnprocessable();
        $this->postJson('/api/v1/auth/reset-password', array_replace($base, ['password_confirmation' => 'wrong']))->assertUnprocessable();
        $this->travel(61)->minutes();
        $this->postJson('/api/v1/auth/reset-password', $base)->assertUnprocessable();
        $this->assertTrue(Hash::check('Password123!', $user->fresh()->password));
        $this->assertTrue(Hash::check('Password123!', $admin->fresh()->password));
    }

    public function test_resend_reuses_secure_browser_link_and_remains_neutral_for_other_accounts(): void
    {
        $user = $this->customer(['isVerified' => 0]);
        $admin = $this->customer(['email' => 'admin@example.test', 'isVerified' => 0], ['admin']);
        $disabled = $this->customer(['email' => 'disabled@example.test', 'isActive' => 0, 'isVerified' => 0]);
        foreach ([$user->email, $user->email, $admin->email, $disabled->email, 'unknown@example.test'] as $email) {
            $this->postJson('/api/v1/auth/resend-verification', ['email' => $email])->assertOk()->assertJsonPath('message', AuthController::RECOVERY);
        }
        Mail::assertSentCount(1);
        $mail = Mail::sent(MobileAccountMail::class)->sole();
        $url = $mail->data['activation_url'];
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame(86400, (int) $query['expires'] - (int) $query['issued']);
        $this->assertSame('pending', app(EmailActivationService::class)->inspect(\Illuminate\Http\Request::create($url), (string) $user->id, hash('sha256', $user->email)));
        $this->travel(86400)->seconds();
        $this->assertSame('invalid', app(EmailActivationService::class)->inspect(\Illuminate\Http\Request::create($url), (string) $user->id, hash('sha256', $user->email)));
        $this->postJson('/api/v1/auth/resend-verification', ['email' => $user->email])->assertOk();
        Mail::assertSentCount(2);
        $this->assertSame(0, (int) $disabled->fresh()->isActive);
    }

    public function test_resend_and_recovery_limits_are_shared_with_web_flow_and_mail_failure_is_neutral(): void
    {
        $user = $this->customer(['isVerified' => 0]);
        Mail::shouldReceive('to')->twice()->andThrow(new \RuntimeException('synthetic failure'));
        $this->postJson('/api/v1/auth/resend-verification', ['email' => $user->email])->assertOk();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        foreach (['resend-verification', 'forgot-password'] as $endpoint) {
            for ($i = 0; $i < 4; $i++) {
                $this->postJson('/api/v1/auth/'.$endpoint, ['email' => $user->email])->assertOk();
            }
            $this->postJson('/api/v1/auth/'.$endpoint, ['email' => $user->email])->assertStatus(429)->assertHeader('Retry-After');
        }
        $this->assertTrue(RateLimiter::tooManyAttempts('activation-resend-account:'.hash('sha256', $user->email), 5));
        $this->assertTrue(RateLimiter::tooManyAttempts('web-reset-request-account:'.hash('sha256', $user->email), 5));
    }

    public function test_api_mail_rendering_never_invokes_the_production_wildcard_composer(): void
    {
        $user = $this->customer(['isVerified' => 0, 'name' => '<script>canary</script>']);
        $category = DB::table('categories')->insertGetId(['name' => 'Synthetic', 'isActive' => 1]);
        $country = DB::table('countries')->insertGetId(['name' => 'Synthetic', 'short_name' => 'SY', 'isActive' => 1]);
        $city = DB::table('cities')->insertGetId(['name' => 'Synthetic', 'country_id' => $country, 'isActive' => 1]);
        $id = DB::table('post_ads')->insertGetId(['category_id' => $category, 'ad_type' => 'Carring', 'title' => 'Expired',
            'description' => 'Synthetic', 'weight' => 1, 'weight_price' => 1, 'currency' => 'USD',
            'from_country_id' => $country, 'to_country_id' => $country, 'from_city_id' => $city, 'to_city_id' => $city,
            'departure_on' => '2000-01-01', 'reached_on' => '2000-01-02', 'isActive' => 1, 'isFeatured' => 0, 'created_by' => $user->id]);
        $before = (array) DB::table('post_ads')->find($id);
        $composed = 0;
        app('events')->listen('composing: *', function () use (&$composed) { $composed++; });
        $this->postJson('/api/v1/auth/resend-verification', ['email' => $user->email])->assertOk();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        foreach (Mail::sent(MobileAccountMail::class) as $mail) {
            $html = $mail->render();
            $this->assertStringContainsString('https://auth.example.test/', $html);
            $this->assertStringNotContainsString('<script>canary</script>', $html);
        }
        $this->assertSame(0, $composed);
        $this->assertSame($before, (array) DB::table('post_ads')->find($id));
    }
}
