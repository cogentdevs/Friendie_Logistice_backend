<?php

namespace Tests\Feature\Security;

use App\Http\Controllers\Auth\EmailActivationController;
use App\Mail\UserEmailVerification;
use App\Models\User;
use App\Services\CustomerEmailChangeService;
use App\Services\EmailActivationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailActivationTest extends TestCase
{
    use DatabaseTransactions;
    private User $customer;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-12 12:00:00', 'UTC'));
        config(['app.url' => 'https://activation.example.test']);
        Role::findOrCreate('user', 'web');
        Role::findOrCreate('admin', 'web');
        $this->customer = User::factory()->create(['email' => 'Pending@Example.test', 'isActive' => 1,
            'isVerified' => 0, 'email_verified_at' => null]);
        $this->customer->assignRole('user');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        // Same explicit test-only composer isolation as the approved reset tests.
        app('events')->forget('composing: *');
        view()->share(['front_footer_count' => 0, 'front_footer' => null, 'meta_tags' => collect(),
            'home_meta_tags' => null, 'about_meta_tags' => null, 'contact_meta_tags' => null]);
    }

    private function link(?User $user = null): string
    {
        return app(EmailActivationService::class)->link($user ?? $this->customer);
    }

    private function tamper(string $url, array $changes): string
    {
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        foreach ($changes as $key => $value) {
            if ($value === null) {
                unset($query[$key]);
            } else {
                $query[$key] = $value;
            }
        }

        return strtok($url, '?').'?'.http_build_query($query);
    }

    public function test_get_is_read_only_and_post_is_atomic_idempotent_and_only_updates_verification(): void
    {
        $url = $this->link();
        $before = $this->customer->fresh()->getAttributes();
        $this->get($url)->assertOk()->assertSee('action="'.e($url).'"', false)
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertDontSee('verified=true');
        $this->assertSame($before, $this->customer->fresh()->getAttributes());
        $this->post($url)->assertRedirect('/login');
        $after = $this->customer->fresh()->getAttributes();
        $this->assertSame(1, $after['isVerified']);
        $this->assertNotNull($after['email_verified_at']);
        foreach ($before as $field => $value) {
            if (! in_array($field, ['isVerified', 'email_verified_at'], true)) {
                $this->assertSame($value, $after[$field], $field);
            }
        }
        $this->travel(1)->hour();
        $this->post($url)->assertRedirect('/login');
        $this->assertSame($after, $this->customer->fresh()->getAttributes());
        $this->assertGuest();
    }

    public static function invalidProofs(): array
    {
        return [
            [['signature' => null]], [['signature' => str_repeat('0', 64)]], [['expires' => null]],
            [['expires' => 'tomorrow']], [['expires' => ['bad']]], [['issued' => null]],
            [['purpose' => 'password-reset']], [['purpose' => ['bad']]], [['extra' => 'unsigned']],
        ];
    }

    #[DataProvider('invalidProofs')]
    public function test_invalid_or_missing_signed_parameters_never_authorize(array $changes): void
    {
        $before = $this->customer->fresh()->getAttributes();
        $url = $this->tamper($this->link(), $changes);
        $this->get($url)->assertRedirect('/resend-activation');
        $this->post($url)->assertRedirect('/resend-activation');
        $this->assertSame($before, $this->customer->fresh()->getAttributes());
    }

    public function test_wrong_account_email_host_and_legacy_links_are_rejected(): void
    {
        $url = $this->link();
        $other = User::factory()->create(['isVerified' => 0]);
        $other->assignRole('user');
        foreach ([str_replace('/'.$this->customer->id.'-', '/'.$other->id.'-', $url),
            str_replace(hash('sha256', 'pending@example.test'), str_repeat('0', 64), $url),
            str_replace('activation.example.test', 'attacker.example.test', $url),
            '/activate-your-account/'.$this->customer->id.'-legacy-random-slug'] as $bad) {
            $this->post($bad)->assertRedirect('/resend-activation');
        }
        $this->assertSame(0, $this->customer->fresh()->isVerified);
        $this->assertSame(0, $other->fresh()->isVerified);
    }

    public function test_valid_signature_cannot_override_purpose_ttl_future_issue_or_missing_expiry_policy(): void
    {
        $generator = clone app('url');
        $generator->forceRootUrl('https://activation.example.test');
        $generator->forceScheme('https');
        $parameters = ['user_id' => $this->customer->id,
            'slug' => hash('sha256', 'pending@example.test'), 'purpose' => EmailActivationService::PURPOSE,
            'issued' => now()->getTimestamp()];
        foreach ([
            $generator->signedRoute('activation.confirm', $parameters),
            $generator->temporarySignedRoute('activation.confirm', now()->addHours(25), $parameters),
            $generator->temporarySignedRoute('activation.confirm', now()->addDays(2), array_replace($parameters, ['issued' => now()->addDay()->getTimestamp()])),
            $generator->temporarySignedRoute('activation.confirm', now()->addDay(), array_replace($parameters, ['purpose' => 'other-purpose'])),
        ] as $url) {
            $this->post($url)->assertRedirect('/resend-activation');
        }
        $this->assertSame(0, $this->customer->fresh()->isVerified);
    }

    public static function boundaries(): array
    {
        return [[86399, true], [86400, false], [86401, false]];
    }

    #[DataProvider('boundaries')]
    public function test_exact_24_hour_boundary_is_enforced_on_post(int $seconds, bool $allowed): void
    {
        $url = $this->link();
        $this->get($url)->assertOk();
        $this->travel($seconds)->seconds();
        $this->post($url)->assertRedirect($allowed ? '/login' : '/resend-activation');
        $this->assertSame($allowed ? 1 : 0, $this->customer->fresh()->isVerified);
    }

    public function test_admin_disabled_ambiguous_and_already_verified_accounts_are_safe(): void
    {
        $url = $this->link();
        $this->customer->update(['isActive' => 0]);
        $before = $this->customer->fresh()->getAttributes();
        $this->post($url)->assertRedirect('/resend-activation');
        $this->assertSame($before, $this->customer->fresh()->getAttributes());
        $this->post($this->link($this->admin))->assertRedirect('/resend-activation');
        $this->customer->update(['isActive' => 1, 'isVerified' => 1]);
        $before = $this->customer->fresh()->getAttributes();
        $this->post($url)->assertRedirect('/login');
        $this->assertSame($before, $this->customer->fresh()->getAttributes());
        $this->assertNull($this->customer->fresh()->email_verified_at);
    }

    public function test_resend_is_neutral_rate_limited_and_does_not_revoke_older_links(): void
    {
        $url = $this->link();
        foreach ([$this->customer->email, 'unknown@example.test', $this->admin->email] as $email) {
            $this->post('/resend-activation', ['email' => $email])->assertRedirect('/resend-activation')
                ->assertSessionHas('flash_message_success', EmailActivationController::NEUTRAL);
        }
        Mail::assertSentCount(1);
        $this->travel(61)->seconds();
        $this->post('/resend-activation', ['email' => $this->customer->email])->assertRedirect();
        Mail::assertSentCount(2);
        $newUrl = Mail::sent(UserEmailVerification::class)->last()->data['activation_url'];
        $this->get($url)->assertOk();
        $this->get($newUrl)->assertOk();
        for ($i = 0; $i < 3; $i++) {
            $this->post('/resend-activation', ['email' => $this->customer->email])->assertRedirect();
        }
        $this->post('/resend-activation', ['email' => $this->customer->email])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_expired_link_can_be_replaced_and_disabled_accounts_receive_no_email(): void
    {
        $old = $this->link();
        $this->travel(86400)->seconds();
        $this->get($old)->assertRedirect('/resend-activation');
        $this->post('/resend-activation', ['email' => $this->customer->email])->assertRedirect();
        $fresh = Mail::sent(UserEmailVerification::class)->sole()->data['activation_url'];
        $this->get($fresh)->assertOk();
        $this->customer->update(['isActive' => 0]);
        $this->travel(61)->seconds();
        $this->post('/resend-activation', ['email' => $this->customer->email])->assertRedirect();
        Mail::assertSentCount(1);
    }

    public function test_mail_failure_is_neutral_and_canonical_origin_ignores_incoming_host(): void
    {
        request()->headers->set('host', 'attacker.example.test');
        $url = $this->link();
        $this->assertStringStartsWith('https://activation.example.test/', $url);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('synthetic-mail-render-failure'));
        $this->post('/resend-activation', ['email' => $this->customer->email])->assertRedirect('/resend-activation')
            ->assertSessionHas('flash_message_success', EmailActivationController::NEUTRAL);
    }

    public function test_customer_and_admin_email_edits_require_reverification_without_changing_status(): void
    {
        $old = $this->link();
        $password = $this->customer->password;
        $remember = $this->customer->remember_token;
        $this->customer->update(['isVerified' => 1, 'email_verified_at' => now()]);
        $this->actingAs($this->customer)->post('/user/profile', ['name' => $this->customer->name,
            'email' => 'replacement@example.test', 'country_id' => 1, 'phone' => 'synthetic-phone', 'cur_image' => 'avatar.jpg'])
            ->assertRedirect('/login');
        $this->assertGuest();
        $this->assertSame(1, $this->customer->fresh()->isActive);
        $this->assertSame(0, $this->customer->fresh()->isVerified);
        $this->assertNull($this->customer->fresh()->email_verified_at);
        $this->assertSame($password, $this->customer->fresh()->password);
        $this->assertSame($remember, $this->customer->fresh()->remember_token);
        $this->assertTrue($this->customer->fresh()->hasRole('user'));
        $this->get($old)->assertRedirect('/resend-activation');
        $this->actingAs($this->admin)->post('/admin/edit-user/'.$this->customer->id, ['name' => $this->customer->name,
            'email' => 'admin-changed@example.test', 'country_id' => 1, 'phone' => 'synthetic-phone',
            'cur_image' => 'avatar.jpg', 'isActive' => 0])->assertRedirect('/admin/view-users');
        $this->assertAuthenticatedAs($this->admin);
        $this->assertSame(0, $this->customer->fresh()->isActive);
        $this->assertSame(0, $this->customer->fresh()->isVerified);
        $this->post('/admin/edit-user/'.$this->customer->id, ['name' => $this->customer->name,
            'email' => 'admin-changed@example.test', 'country_id' => 1, 'phone' => 'synthetic-phone',
            'cur_image' => 'avatar.jpg', 'isActive' => 1])->assertRedirect('/admin/view-users');
        $this->assertSame(1, $this->customer->fresh()->isActive);
    }

    public function test_non_customer_email_changes_are_blocked_without_changing_admin_access_or_roles(): void
    {
        $before = $this->admin->fresh()->getAttributes();
        $this->actingAs($this->admin)->post('/admin/edit-user/'.$this->admin->id, ['name' => $this->admin->name,
            'email' => 'other-admin@example.test', 'country_id' => 1, 'phone' => 'admin-synthetic-phone',
            'cur_image' => 'avatar.jpg', 'isActive' => 1,
            'image' => \Illuminate\Http\UploadedFile::fake()->create('unreadable.jpg', 1, 'image/jpeg')])->assertSessionHasErrors('email');
        $this->assertSame($before, $this->admin->fresh()->getAttributes());
        $this->assertTrue($this->admin->fresh()->hasRole('admin'));
    }

    public function test_login_requires_both_flags_and_activation_post_is_csrf_protected(): void
    {
        $credentials = ['email' => $this->customer->email, 'password' => 'password', 'from' => 'main', 'remember' => 0];
        $this->post('/login', $credentials)->assertRedirect('/login')->assertSessionMissing(auth()->guard('web')->getName());
        $this->post($this->link())->assertRedirect('/login');
        $this->post('/login', $credentials)->assertRedirect('user/dashboard');
        $this->customer->update(['isActive' => 0]);
        auth()->logout();
        $this->post('/login', $credentials)->assertRedirect('/login');
        $this->customer->refresh()->update(['isActive' => 1, 'isVerified' => 0]);
        $this->app->bind(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken {
                protected function runningUnitTests(): bool { return false; }
            };
        });
        $this->post($this->link())->assertStatus(419);
        $this->assertSame(0, $this->customer->fresh()->isVerified);
        $this->withSession(['_token' => 'synthetic-csrf-token'])->post($this->link(), ['_token' => 'synthetic-csrf-token'])->assertRedirect('/login');
        $this->assertSame(1, $this->customer->fresh()->isVerified);
    }

    public function test_resend_ip_limit_cooldown_and_get_page(): void
    {
        $this->get('/resend-activation')->assertOk()->assertSee('Request an activation link');
        $this->post('/resend-activation', ['email' => $this->customer->email])->assertRedirect();
        $this->post('/resend-activation', ['email' => $this->customer->email])->assertRedirect();
        Mail::assertSentCount(1);
        for ($i = 0; $i < 8; $i++) {
            $this->post('/resend-activation', ['email' => 'unknown'.$i.'@example.test'])->assertRedirect();
        }
        $this->post('/resend-activation', ['email' => 'other@example.test'])->assertStatus(429);
    }

    public function test_admin_created_customer_defaults_and_case_only_email_changes_remain_compatible(): void
    {
        $this->actingAs($this->admin)->post('/admin/add-user', ['name' => 'Synthetic Admin Created Customer',
            'email' => 'admin-created@example.test', 'phone' => 'synthetic-admin-created-phone',
            'country_id' => 1, 'password' => 'AdminCreatedPassword42!'])->assertRedirect('/admin/view-users');
        $created = User::where('email', 'admin-created@example.test')->sole();
        $this->assertSame(1, $created->isActive);
        $this->assertSame(1, $created->isVerified);
        $this->assertTrue($created->hasRole('user'));
        $created->forceFill(['email_verified_at' => now()])->save();
        $before = $created->fresh()->getAttributes();
        $changed = app(CustomerEmailChangeService::class)->update($created->id, ['email' => 'ADMIN-CREATED@EXAMPLE.TEST']);
        $this->assertFalse($changed);
        $this->assertSame($before, $created->fresh()->getAttributes());
        Mail::assertNothingSent();
    }

    public function test_request_body_cannot_redirect_verification_to_a_different_account(): void
    {
        $this->post($this->link(), ['user_id' => $this->admin->id, 'isActive' => 1, 'isVerified' => 1])->assertRedirect('/login');
        $this->assertSame(1, $this->customer->fresh()->isVerified);
        $this->assertSame(1, $this->customer->fresh()->isActive);
        $this->assertTrue($this->admin->fresh()->hasRole('admin'));
    }

    public function test_null_or_unknown_verification_states_and_missing_or_mixed_roles_fail_closed(): void
    {
        $url = $this->link();
        foreach ([null, 2, -1] as $state) {
            DB::table('users')->where('id', $this->customer->id)->update(['isVerified' => $state]);
            $this->post($url)->assertRedirect('/resend-activation');
            $this->assertSame($state, $this->customer->fresh()->isVerified);
        }
        DB::table('users')->where('id', $this->customer->id)->update(['isVerified' => 0]);
        $this->customer->syncRoles([]);
        $this->post($url)->assertRedirect('/resend-activation');
        $this->customer->syncRoles(['user', 'admin']);
        $this->post($url)->assertRedirect('/resend-activation');
        $this->assertSame(0, $this->customer->fresh()->isVerified);
    }
}
