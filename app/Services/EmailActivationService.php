<?php

namespace App\Services;

use App\Mail\UserEmailVerification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

final class EmailActivationService
{
    public const PURPOSE = 'customer-email-activation-v1';
    public const LIFETIME = 86400;

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function customer(User $user): bool
    {
        return $user->roles()->where('guard_name', 'web')->pluck('name')->all() === ['user'];
    }

    private function base(): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $parts = parse_url($base);
        if (! filter_var($base, FILTER_VALIDATE_URL) || ! in_array($parts['scheme'] ?? '', ['https', 'http'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (! app()->environment(['local', 'testing']) && ($parts['scheme'] ?? '') !== 'https')) {
            throw new \LogicException('A trusted public HTTPS application origin is required.');
        }

        return $base;
    }

    public function link(User $user): string
    {
        $issued = now()->getTimestamp();
        // Clone the framework generator: never change global web URL/session behavior.
        $generator = clone app('url');
        $generator->forceRootUrl($this->base());
        $generator->forceScheme(parse_url($this->base(), PHP_URL_SCHEME));

        return $generator->temporarySignedRoute('activation.confirm',
            \Illuminate\Support\Carbon::createFromTimestampUTC($issued + self::LIFETIME), [
            'user_id' => $user->id,
            'slug' => hash('sha256', self::normalizeEmail($user->email)),
            'issued' => $issued, 'purpose' => self::PURPOSE,
        ]);
    }

    public function send(int $id, ?\Closure $deliver = null): void
    {
        $user = User::find($id);
        if (! $user || ! $this->customer($user) || (int) $user->isActive !== 1
            || ! in_array($user->isVerified, [0, '0'], true)) {
            return;
        }
        try {
            $data = ['user_name' => $user->name, 'activation_url' => $this->link($user)];
            if ($deliver !== null) {
                $deliver($user->email, $data);
            } else {
                Mail::to($user->email)->send(new UserEmailVerification($data));
            }
        } catch (\Throwable) {
            // Rendering/transport exceptions may contain the signed URL. Never log their contents.
            Log::warning('Activation email delivery failed.');
        }
    }

    private function proof(Request $request, string $id, string $slug): bool
    {
        $query = $request->query();
        if (! ctype_digit($id) || strlen($id) > 19 || ! preg_match('/\A[a-f0-9]{64}\z/', $slug)
            || array_diff(array_keys($query), ['issued', 'expires', 'purpose', 'signature']) !== []) {
            return false;
        }
        foreach (['issued', 'expires'] as $key) {
            if (! isset($query[$key]) || ! is_scalar($query[$key])
                || ! preg_match('/\A[1-9][0-9]{0,11}\z/', (string) $query[$key])) {
                return false;
            }
        }
        if (($query['purpose'] ?? null) !== self::PURPOSE || ! is_string($query['signature'] ?? null)
            || ! preg_match('/\A[a-f0-9]{64}\z/', $query['signature'])) {
            return false;
        }
        $now = now()->getTimestamp();
        $issued = (int) $query['issued'];
        $expires = (int) $query['expires'];
        $base = parse_url($this->base());
        $origin = $base['scheme'].'://'.$base['host'].(isset($base['port']) ? ':'.$base['port'] : '');

        return $issued <= $now && $expires - $issued === self::LIFETIME && $now < $expires
            && $request->getSchemeAndHttpHost() === $origin && URL::hasValidSignature($request);
    }

    private function state(Request $request, string $id, string $slug, bool $write): string
    {
        if (! $this->proof($request, $id, $slug)) {
            return 'invalid';
        }
        $query = User::whereKey($id);
        $user = ($write ? $query->lockForUpdate() : $query)->first();
        // Inactive/unverified legacy accounts and administrative holds fail closed.
        if (! $user || ! $this->customer($user) || (int) $user->isActive !== 1
            || ! hash_equals(hash('sha256', self::normalizeEmail($user->email)), $slug)
            || ! in_array($user->isVerified, [0, 1, '0', '1'], true)
            || ! $this->proof($request, $id, $slug)) {
            return 'invalid';
        }
        if ((int) $user->isVerified === 1) {
            return 'already_verified';
        }
        if ($write) {
            // Only verification fields change. In particular, never write isActive.
            DB::table('users')->where('id', $user->id)->update(['isVerified' => 1, 'email_verified_at' => now()]);
        }

        return $write ? 'verified' : 'pending';
    }

    public function inspect(Request $request, string $id, string $slug): string
    {
        return $this->state($request, $id, $slug, false);
    }

    public function verify(Request $request, string $id, string $slug): string
    {
        return DB::transaction(fn () => $this->state($request, $id, $slug, true));
    }
}
