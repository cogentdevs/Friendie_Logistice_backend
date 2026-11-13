<?php

namespace Tests\Feature\Security;

use App\Mail\UserForgotPasswrod;
use App\Models\User;
use App\Services\WebPasswordResetService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PasswordResetConcurrencyTest extends TestCase
{
    public static function roles(): array
    {
        return [['user'], ['admin']];
    }

    #[DataProvider('roles')]
    public function test_two_real_connections_cannot_consume_the_same_reset_token(string $scope): void
    {
        // Committed synthetic fixture is necessary for two processes to see the same rows.
        // The common bootstrap has already proved this user's grants exclude the original DB.
        $this->assertTrue(app()->environment('testing'));
        $this->assertMatchesRegularExpression('/^friendie_logistics_testing_[0-9_]+$/', DB::connection()->getDatabaseName());
        $this->assertSame(0, DB::table('users')->count(), 'Concurrency test requires an empty isolated account table.');
        $createdRole = ! Role::where('name', $scope)->where('guard_name', 'web')->exists();
        $role = Role::findOrCreate($scope, 'web');
        $user = null;
        $key = null;
        $gate = str_replace('\\', '/', sys_get_temp_dir()).'/friendie-reset-race-'.bin2hex(random_bytes(10));
        $workers = [];
        try {
            $user = User::factory()->create(['email' => 'concurrent-reset@example.test']);
            $user->assignRole($role);
            $service = app(WebPasswordResetService::class);
            $key = $service->identity($user, $scope)->getEmailForPasswordReset();
            $service->requestLink($user->email, $scope);
            $url = Mail::sent(UserForgotPasswrod::class)->sole()->data['reset_url'];
            $token = substr($url, strrpos($url, '-') + 1);
            foreach (['ConcurrentPasswordA42!', 'ConcurrentPasswordB42!'] as $password) {
                $process = proc_open([PHP_BINARY, base_path('tests/Support/PasswordResetWorker.php')],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $this->assertIsResource($process);
                stream_set_timeout($pipes[1], 20);
                fwrite($pipes[0], json_encode(['id' => $user->id, 'role' => $scope, 'token' => $token, 'password' => $password, 'gate' => $gate]));
                fclose($pipes[0]);
                $workers[] = [$process, $pipes];
            }
            foreach ($workers as [, $pipes]) {
                $this->assertSame("READY\n", fgets($pipes[1]), 'Restricted worker must reach the barrier.');
            }
            file_put_contents($gate, 'synthetic-test-barrier');
            $outcomes = [];
            foreach ($workers as [$process, $pipes]) {
                $outcomes[] = trim(stream_get_contents($pipes[1]));
                fclose($pipes[1]);
                // Read/discard stderr; do not expose sensitive worker inputs in test diagnostics.
                stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process));
            }
            $workers = [];
            sort($outcomes);
            $this->assertSame(['REJECTED', 'SUCCESS'], $outcomes);
            $this->assertTrue(Hash::check('ConcurrentPasswordA42!', $user->fresh()->password)
                || Hash::check('ConcurrentPasswordB42!', $user->fresh()->password));
            $this->assertFalse($service->valid((string) $user->id, $scope, $token));
            $this->assertSame(0, DB::table('password_reset_tokens')->where('email', $key)->count());
        } finally {
            foreach ($workers as [$process]) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }
            if (is_file($gate)) {
                unlink($gate);
            }
            // Only this test's exact synthetic IDs/key, always on the restricted test connection.
            if ($key !== null) {
                DB::table('password_reset_tokens')->where('email', $key)->delete();
            }
            if ($user !== null) {
                DB::table('model_has_roles')->where('model_id', $user->id)->where('model_type', User::class)->delete();
                DB::table('users')->where('id', $user->id)->delete();
            }
            if ($createdRole) {
                $role->delete();
            }
        }
    }
}
