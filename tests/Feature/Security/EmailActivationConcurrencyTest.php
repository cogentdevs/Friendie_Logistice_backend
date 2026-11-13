<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\EmailActivationService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailActivationConcurrencyTest extends TestCase
{
    public function test_two_concurrent_posts_transition_verification_only_once(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertMatchesRegularExpression('/^friendie_logistics_testing_[0-9_]+$/', DB::connection()->getDatabaseName());
        $this->assertSame(0, DB::table('users')->count());
        config(['app.url' => 'https://activation.example.test']);
        $createdRole = ! Role::where('name', 'user')->where('guard_name', 'web')->exists();
        $role = Role::findOrCreate('user', 'web');
        $user = null;
        $workers = [];
        $gate = str_replace('\\', '/', sys_get_temp_dir()).'/friendie-activation-race-'.bin2hex(random_bytes(10));
        try {
            $user = User::factory()->create(['email' => 'activation-race@example.test', 'isActive' => 1,
                'isVerified' => 0, 'email_verified_at' => null]);
            $user->assignRole($role);
            $before = $user->fresh()->getAttributes();
            $url = app(EmailActivationService::class)->link($user);
            for ($i = 0; $i < 2; $i++) {
                $process = proc_open([PHP_BINARY, base_path('tests/Support/EmailActivationWorker.php')],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $this->assertIsResource($process);
                stream_set_timeout($pipes[1], 20);
                fwrite($pipes[0], json_encode(['id' => $user->id, 'url' => $url,
                    'slug' => hash('sha256', 'activation-race@example.test'), 'gate' => $gate]));
                fclose($pipes[0]);
                $workers[] = [$process, $pipes];
            }
            foreach ($workers as [, $pipes]) {
                $this->assertSame("READY\n", fgets($pipes[1]));
            }
            file_put_contents($gate, 'synthetic-test-barrier');
            $results = [];
            foreach ($workers as [$process, $pipes]) {
                $results[] = trim(stream_get_contents($pipes[1]));
                fclose($pipes[1]);
                stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process));
            }
            $workers = [];
            sort($results);
            $this->assertSame(['already_verified', 'verified'], $results);
            $after = $user->fresh()->getAttributes();
            $this->assertSame(1, $after['isVerified']);
            $this->assertNotNull($after['email_verified_at']);
            foreach ($before as $field => $value) {
                if (! in_array($field, ['isVerified', 'email_verified_at'], true)) {
                    $this->assertSame($value, $after[$field]);
                }
            }
        } finally {
            foreach ($workers as [$process]) {
                if (is_resource($process)) { proc_terminate($process); proc_close($process); }
            }
            if (is_file($gate)) { unlink($gate); }
            if ($user !== null) {
                DB::table('model_has_roles')->where('model_id', $user->id)->where('model_type', User::class)->delete();
                DB::table('users')->where('id', $user->id)->delete();
            }
            if ($createdRole) { $role->delete(); }
        }
    }
}
