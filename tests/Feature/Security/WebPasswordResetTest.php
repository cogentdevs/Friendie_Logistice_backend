<?php

namespace Tests\Feature\Security;

use App\Http\Controllers\Auth\WebPasswordResetController;
use App\Mail\UserEmailVerification;
use App\Mail\UserForgotPasswrod;
use App\Models\User;
use App\Services\WebPasswordResetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebPasswordResetTest extends TestCase
{
    use DatabaseTransactions;

    private User $customer;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // Test public directory deliberately has no production build artifacts.
        Role::findOrCreate('user', 'web');
        Role::findOrCreate('admin', 'web');
        $this->customer = User::factory()->create(['email' => 'customer@example.test', 'isActive' => 1, 'isVerified' => 1]);
        $this->customer->assignRole('user');
        $this->admin = User::factory()->create(['email' => 'admin@example.test', 'isActive' => 1, 'isVerified' => 1]);
        $this->admin->assignRole('admin');
        $this->admin->givePermissionTo(Permission::findOrCreate('synthetic-recovery-permission', 'web'));

        // Test-only replacement: legacy '*' composer mutates listings even during mail rendering.
        // Do not silently alter its production business behavior as part of this remediation.
        app('events')->forget('composing: *');
        view()->share(['front_footer_count' => 0, 'front_footer' => null, 'meta_tags' => collect(),
            'home_meta_tags' => null, 'about_meta_tags' => null, 'contact_meta_tags' => null]);
    }

    public static function roles(): array
    {
        return [['user'], ['admin']];
    }

    private function account(string $role): User
    {
        return $role === 'admin' ? $this->admin : $this->customer;
    }

    private function requestPath(string $role): string
    {
        return $role === 'admin' ? '/admin/forgot-password' : '/forgot-password';
    }

    private function issue(string $role): array
    {
        $user = $this->account($role);
        $this->post($this->requestPath($role), ['email' => $user->email])->assertRedirect($this->requestPath($role))
            ->assertSessionHas('flash_message_success', WebPasswordResetController::NEUTRAL);
        $mail = Mail::sent(UserForgotPasswrod::class)->last();
        $this->assertNotNull($mail);
        $url = $mail->data['reset_url'];
        $path = parse_url($url, PHP_URL_PATH);
        $token = substr($path, strrpos($path, '-') + 1);

        return [$path, $token, $mail];
    }

    private function passwordData(string $password = 'NewSecurePassword42!'): array
    {
        return ['newPassword' => $password, 'confirmPassword' => $password];
    }

    #[DataProvider('roles')]
    public function test_valid_token_resets_only_its_account_and_preserves_roles_status_and_session(string $role): void
    {
        [$path, $token] = $this->issue($role);
        $user = $this->account($role);
        $before = $user->fresh()->getAttributes();
        $roles = $user->getRoleNames()->all();
        $permissions = $user->getAllPermissions()->pluck('name')->all();
        $other = $this->account($role === 'admin' ? 'user' : 'admin')->fresh()->getAttributes();
        $this->actingAs($user, 'web')->withSession(['unrelated-session-value' => 'preserve']);
        $this->post($path, $this->passwordData())->assertRedirect($role === 'admin' ? '/admin' : '/login')
            ->assertSessionHas('unrelated-session-value', 'preserve');
        $this->assertTrue(Hash::check('NewSecurePassword42!', $user->fresh()->password));
        $this->assertFalse(app(WebPasswordResetService::class)->valid((string) $user->id, $role, $token));
        foreach ($before as $field => $value) {
            if (! in_array($field, ['password', 'updated_at'], true)) {
                $this->assertSame($value, $user->fresh()->getAttributes()[$field], $field);
            }
        }
        $this->assertSame($roles, $user->fresh()->getRoleNames()->all());
        $this->assertSame($permissions, $user->fresh()->getAllPermissions()->pluck('name')->all());
        $this->assertSame($other, $this->account($role === 'admin' ? 'user' : 'admin')->fresh()->getAttributes());
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
        $this->assertAuthenticatedAs($user, 'web');
    }

    #[DataProvider('roles')]
    public function test_tokens_are_hashed_scoped_and_mail_contains_the_authoritative_role_link(string $role): void
    {
        [$path, $token, $mail] = $this->issue($role);
        $user = $this->account($role);
        $record = DB::table('password_reset_tokens')->sole();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $this->assertNotSame($token, $record->token);
        $this->assertTrue(Hash::check($token, $record->token));
        $this->assertSame(app(WebPasswordResetService::class)->identity($user, $role)->getEmailForPasswordReset(), $record->email);
        $this->assertStringStartsWith('fl-reset-v1:'.$role.':', $record->email);
        $this->assertStringStartsWith($role === 'admin' ? '/admin/reset-password/' : '/reset-password/', $path);
        $this->assertStringContainsString(e($mail->data['reset_url']), $mail->render());
        $this->assertStringNotContainsString($user->password, $mail->render());
        $this->get($path)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('action="'.url($path).'"', false);
    }

    #[DataProvider('roles')]
    public function test_invalid_expired_replayed_cross_account_and_legacy_tokens_cannot_change_passwords(string $role): void
    {
        [$path, $token] = $this->issue($role);
        $user = $this->account($role);
        $hash = $user->fresh()->password;
        $prefix = $role === 'admin' ? '/admin/reset-password/' : '/reset-password/';
        $other = User::factory()->create();
        $other->assignRole($role);
        foreach ([$prefix.$user->id.'-'.str_repeat('f', 64), $prefix.$user->id.'-legacy-slug',
            $prefix.$other->id.'-'.$token, $prefix.$user->id.'-missing'] as $invalid) {
            $this->post($invalid, $this->passwordData())->assertRedirect($this->requestPath($role))
                ->assertSessionHas('flash_message_error', WebPasswordResetController::INVALID);
        }
        $this->assertSame($hash, $user->fresh()->password);
        $this->get($prefix.$user->id.'-')->assertNotFound();
        DB::table('password_reset_tokens')->update(['created_at' => now()->subMinutes(61)]);
        $this->post($path, $this->passwordData())->assertRedirect($this->requestPath($role));
        $this->assertSame($hash, $user->fresh()->password);
        $this->travel(16)->minutes();
        DB::table('password_reset_tokens')->update(['created_at' => now()]);
        $this->post($path, $this->passwordData())->assertRedirect($role === 'admin' ? '/admin' : '/login');
        $changed = $user->fresh()->password;
        $this->post($path, $this->passwordData('DifferentPassword42!'))->assertRedirect($this->requestPath($role));
        $this->assertSame($changed, $user->fresh()->password);
    }

    #[DataProvider('roles')]
    public function test_password_policy_confirmation_and_missing_password_preserve_hash_and_do_not_flash_credentials(string $role): void
    {
        [$path] = $this->issue($role);
        $hash = $this->account($role)->fresh()->password;
        foreach ([[], $this->passwordData('short'), ['newPassword' => 'ValidPassword42!', 'confirmPassword' => 'mismatch'],
            $this->passwordData(str_repeat('x', 73)), $this->passwordData(str_repeat('é', 40))] as $input) {
            $this->post($path, $input)->assertSessionHasErrors()->assertSessionMissing('_old_input.newPassword')
                ->assertSessionMissing('_old_input.confirmPassword');
            $this->assertSame($hash, $this->account($role)->fresh()->password);
        }
    }

    #[DataProvider('roles')]
    public function test_unknown_and_wrong_role_requests_have_the_same_neutral_response(string $role): void
    {
        foreach (['unknown@example.test', $this->account($role === 'admin' ? 'user' : 'admin')->email,
            $this->account($role)->email] as $email) {
            $this->post($this->requestPath($role), ['email' => $email])->assertRedirect($this->requestPath($role))
                ->assertSessionHas('flash_message_success', WebPasswordResetController::NEUTRAL);
        }
        Mail::assertSentCount(1);
    }

    #[DataProvider('roles')]
    public function test_request_cooldown_and_account_rate_limits_apply_even_to_unknown_accounts(string $role): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post($this->requestPath($role), ['email' => $this->account($role)->email])->assertRedirect();
        }
        Mail::assertSentCount(1);
        $this->post($this->requestPath($role), ['email' => $this->account($role)->email])->assertStatus(429)->assertHeader('Retry-After');
    }

    #[DataProvider('roles')]
    public function test_submission_rate_limits_reject_further_attempts_without_changing_hash(string $role): void
    {
        $user = $this->account($role);
        $hash = $user->password;
        $path = ($role === 'admin' ? '/admin' : '').'/reset-password/'.$user->id.'-'.str_repeat('0', 64);
        for ($i = 0; $i < 5; $i++) {
            $this->post($path, $this->passwordData())->assertRedirect();
        }
        $this->post($path, $this->passwordData())->assertStatus(429);
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_customer_admin_and_changed_role_tokens_are_strictly_isolated(): void
    {
        [$customerPath, $customerToken] = $this->issue('user');
        [$adminPath, $adminToken] = $this->issue('admin');
        $this->post('/admin/reset-password/'.$this->admin->id.'-'.$customerToken, $this->passwordData())->assertRedirect('/admin/forgot-password');
        $this->post('/reset-password/'.$this->customer->id.'-'.$adminToken, $this->passwordData())->assertRedirect('/forgot-password');
        $this->post('/admin'. $customerPath, $this->passwordData())->assertRedirect('/admin/forgot-password');
        $this->post(str_replace('/admin', '', $adminPath), $this->passwordData())->assertRedirect('/forgot-password');
        $this->customer->syncRoles('admin');
        $this->post('/admin/reset-password/'.$this->customer->id.'-'.$customerToken, $this->passwordData())->assertRedirect('/admin/forgot-password');
        $this->assertTrue(Hash::check('password', $this->customer->fresh()->password));
        $this->assertTrue(Hash::check('password', $this->admin->fresh()->password));
    }

    public function test_reissue_email_change_and_null_creation_time_invalidate_authorization(): void
    {
        [$oldPath] = $this->issue('user');
        $this->travel(61)->seconds();
        [$newPath] = $this->issue('user');
        $this->get($oldPath)->assertRedirect('/forgot-password');
        $this->get($newPath)->assertOk();
        DB::table('password_reset_tokens')->update(['created_at' => null]);
        $this->get($newPath)->assertRedirect('/forgot-password');
        DB::table('password_reset_tokens')->update(['created_at' => now()]);
        $this->customer->update(['email' => 'changed@example.test']);
        $this->get($newPath)->assertRedirect('/forgot-password');
    }

    public function test_ip_rate_limit_covers_different_accounts_and_customer_admin_flows(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post($this->requestPath($i % 2 ? 'admin' : 'user'), ['email' => 'unknown'.$i.'@example.test'])->assertRedirect();
        }
        $this->post('/forgot-password', ['email' => 'another@example.test'])->assertStatus(429);
        Mail::assertNothingSent();
    }

    public function test_unknown_email_is_account_rate_limited_and_reset_remains_behind_csrf(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertRedirect('/forgot-password');
        }
        $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertStatus(429);
        Mail::assertNothingSent();
        $this->app->bind(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken {
                protected function runningUnitTests(): bool { return false; }
            };
        });
        $this->post('/forgot-password', ['email' => $this->customer->email])->assertStatus(419);
        $this->assertTrue(Hash::check('password', $this->customer->fresh()->password));
        Mail::assertNothingSent();
    }

    public function test_existing_customer_and_admin_login_and_registration_remain_compatible(): void
    {
        $this->post('/login', ['email' => $this->customer->email, 'password' => 'password', 'from' => 'main', 'remember' => 0])
            ->assertRedirect('user/dashboard');
        $this->assertAuthenticatedAs($this->customer);
        auth()->logout();
        $this->post('/admin', ['email' => $this->admin->email, 'password' => 'password'])->assertRedirect('admin/dashboard');
        $this->assertAuthenticatedAs($this->admin);
        auth()->logout();
        app('validator')->extend('captcha', fn () => true); // No external CAPTCHA network call.
        $this->from('/sign-up')->post('/sign-up', ['name' => 'Synthetic New Customer', 'email' => 'registered@example.test',
            'phone' => 'synthetic-registration-phone', 'password' => 'RegistrationPassword42!', 'country_id' => 1,
            'g-recaptcha-response' => 'synthetic-captcha'])->assertRedirect('/sign-up');
        $registered = User::where('email', 'registered@example.test')->sole();
        $this->assertSame(1, $registered->isActive);
        $this->assertSame(0, $registered->isVerified);
        $this->assertTrue($registered->hasRole('user'));
        $this->assertTrue(Hash::check('RegistrationPassword42!', $registered->password));
        Mail::assertSent(UserEmailVerification::class);
    }

    #[DataProvider('roles')]
    public function test_new_password_authenticates_through_the_existing_login_and_old_password_no_longer_does(string $role): void
    {
        [$path] = $this->issue($role);
        $this->post($path, $this->passwordData())->assertRedirect($role === 'admin' ? '/admin' : '/login');
        $this->assertGuest();
        $login = $role === 'admin' ? '/admin' : '/login';
        $credentials = ['email' => $this->account($role)->email, 'password' => 'password', 'from' => 'main', 'remember' => 0];
        $this->post($login, $credentials)->assertRedirect($login)->assertSessionHas('flash_message_error');
        $this->assertGuest();
        $credentials['password'] = 'NewSecurePassword42!';
        $this->post($login, $credentials)->assertRedirect($role === 'admin' ? 'admin/dashboard' : 'user/dashboard');
        $this->assertAuthenticatedAs($this->account($role));
    }

    public function test_disabled_account_stays_disabled_and_mail_failure_is_neutral(): void
    {
        $this->customer->update(['isActive' => 0]);
        [$path] = $this->issue('user');
        $this->post($path, $this->passwordData())->assertRedirect('/login');
        $this->assertSame(0, $this->customer->fresh()->isActive);
        Mail::shouldReceive('to')->once()->andThrow(new \Symfony\Component\Mailer\Exception\TransportException('synthetic-failure'));
        $this->post('/admin/forgot-password', ['email' => $this->admin->email])->assertRedirect('/admin/forgot-password')
            ->assertSessionHas('flash_message_success', WebPasswordResetController::NEUTRAL);
    }

    public function test_auth_requests_and_mail_rendering_do_not_change_test_listings_with_test_only_composer_guard(): void
    {
        DB::table('post_ads')->insert(['title' => 'Synthetic expired parcel', 'ad_type' => 'Send',
            'departure_on' => '2020-01-01', 'isActive' => 1, 'show_contact_info' => 0, 'created_by' => $this->customer->id]);
        $before = DB::table('post_ads')->get()->toJson();
        [$path, , $mail] = $this->issue('user');
        $mail->render();
        $this->get($path)->assertOk();
        $this->post($path, $this->passwordData())->assertRedirect('/login');
        $this->assertSame($before, DB::table('post_ads')->get()->toJson());
    }
}
