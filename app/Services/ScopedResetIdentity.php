<?php

namespace App\Services;

use Illuminate\Contracts\Auth\CanResetPassword;

/** Token-repository identity, not a second account model or an email recipient. */
final class ScopedResetIdentity implements CanResetPassword
{
    public function __construct(private readonly string $key) {}

    public function getEmailForPasswordReset(): string
    {
        return $this->key;
    }

    public function sendPasswordResetNotification($token): void
    {
        throw new \LogicException('Recovery mail must be sent to the verified account email.');
    }
}
