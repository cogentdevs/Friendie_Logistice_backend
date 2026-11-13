<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomerEmailChangeService
{
    public function validateEmail(User $user, mixed $email): string
    {
        if (is_string($email)) {
            $email = EmailActivationService::normalizeEmail($email);
        }
        validator(['email' => $email], ['email' => ['required', 'string', 'email', 'max:255',
            \Illuminate\Validation\Rule::unique('users', 'email')->ignore($user->id)]])->validate();
        if (EmailActivationService::normalizeEmail($user->email) !== $email
            && ! app(EmailActivationService::class)->customer($user)) {
            throw ValidationException::withMessages(['email' => 'Non-customer email changes require a separately approved administrative workflow.']);
        }

        return $email;
    }

    public function update(int $id, array $attributes, ?\Closure $deliver = null): bool
    {
        $changed = DB::transaction(function () use ($id, $attributes) {
            $user = User::whereKey($id)->lockForUpdate()->firstOrFail();
            $email = $this->validateEmail($user, $attributes['email'] ?? null);
            $changed = EmailActivationService::normalizeEmail($user->email) !== $email;
            $attributes['email'] = $email;
            if ($changed) {
                $attributes['isVerified'] = 0;
                $attributes['email_verified_at'] = null;
            }
            DB::table('users')->where('id', $id)->update($attributes);

            return $changed;
        });
        if ($changed) {
            // Default web delivery is unchanged; API callers can avoid Blade composers.
            // When called inside an outer account transaction, send only after its commit.
            DB::afterCommit(fn () => app(EmailActivationService::class)->send($id, $deliver));
        }

        return $changed;
    }
}
