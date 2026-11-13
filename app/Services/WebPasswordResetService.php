<?php

namespace App\Services;

use App\Mail\UserForgotPasswrod;
use App\Models\User;
use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class WebPasswordResetService
{
    private function repository(): DatabaseTokenRepository
    {
        return new DatabaseTokenRepository(
            DB::connection(), app('hash'), config('auth.passwords.users.table'),
            config('app.key'), (int) config('auth.passwords.users.expire', 60),
            (int) config('auth.passwords.users.throttle', 60),
        );
    }

    public function identity(User $user, string $role): ScopedResetIdentity
    {
        $this->checkScope($role);

        // Fixed length fits the existing email PK even for 255-character account emails.
        // The default broker's plain-email records cannot authorize these scoped flows.
        return new ScopedResetIdentity('fl-reset-v1:'.$role.':'.hash('sha256', $user->id."\0".$user->email));
    }

    private function checkScope(string $role): void
    {
        if (! in_array($role, ['user', 'admin'], true)) {
            throw new \InvalidArgumentException('Unsupported recovery scope.');
        }
    }

    private function eligible(User $user, string $role): bool
    {
        $this->checkScope($role);
        // Mixed/missing roles fail closed. Never infer an admin account from ID 1.
        return $user->roles()->where('guard_name', 'web')->pluck('name')->all() === [$role];
    }

    public function requestLink(string $email, string $role, ?\Closure $deliver = null): void
    {
        $this->checkScope($role);
        $delivery = DB::transaction(function () use ($email, $role) {
            $user = User::where('email', $email)->lockForUpdate()->first();
            if (! $user || ! $this->eligible($user, $role)) {
                return null;
            }
            $repository = $this->repository();
            $identity = $this->identity($user, $role);
            if ($repository->recentlyCreatedToken($identity)) {
                return null;
            }
            $token = $repository->create($identity);
            $base = rtrim((string) config('app.url'), '/');
            $parts = parse_url($base);
            if (! filter_var($base, FILTER_VALIDATE_URL) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)
                || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
                throw new \LogicException('A valid public application URL is required for recovery mail.');
            }
            $path = $role === 'admin' ? '/admin/reset-password/' : '/reset-password/';

            return ['email' => $user->email, 'data' => [
                'user_name' => $user->name, 'reset_url' => $base.$path.$user->id.'-'.$token,
            ]];
        });

        if ($delivery !== null) {
            try {
                if ($deliver !== null) {
                    $deliver($delivery['email'], $delivery['data']);
                } else {
                    Mail::to($delivery['email'])->send(new UserForgotPasswrod($delivery['data']));
                }
            } catch (\Throwable) {
                // Do not log the rendered email, recipient, exception or reset URL/token.
                Log::warning('Password reset mail delivery failed.');
            }
        }
    }

    private function authorized(User $user, string $role, #[\SensitiveParameter] string $token, bool $lock = false): bool
    {
        if (! $this->eligible($user, $role) || ! preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            return false;
        }
        $identity = $this->identity($user, $role);
        $query = DB::table(config('auth.passwords.users.table'))
            ->where('email', $identity->getEmailForPasswordReset());
        $record = ($lock ? $query->lockForUpdate() : $query)->first();
        if ($record === null || $record->created_at === null) {
            return false;
        }

        // Validate the current locked record, not an older repeatable-read snapshot.
        return \Illuminate\Support\Carbon::parse($record->created_at)
            ->addMinutes((int) config('auth.passwords.users.expire', 60))->isFuture()
            && Hash::check($token, $record->token);
    }

    public function valid(string $id, string $role, #[\SensitiveParameter] string $token): bool
    {
        if (! ctype_digit($id) || strlen($id) > 19 || ! preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            return false;
        }
        $user = User::find($id);

        return $user !== null && $this->authorized($user, $role, $token);
    }

    public function reset(string $id, string $role, #[\SensitiveParameter] string $token, #[\SensitiveParameter] string $password): bool
    {
        if (! ctype_digit($id) || strlen($id) > 19 || ! preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            return false;
        }

        // Issuance and consumption serialize on the same account row. A second concurrent
        // submission sees no token after the first transaction commits. No session/role changes.
        return DB::transaction(function () use ($id, $role, $token, $password) {
            $user = User::whereKey($id)->lockForUpdate()->first();
            if (! $user || ! $this->authorized($user, $role, $token, true)) {
                return false;
            }
            $user->password = Hash::make($password);
            $user->save();
            $this->repository()->delete($this->identity($user, $role));

            return true;
        });
    }
}
