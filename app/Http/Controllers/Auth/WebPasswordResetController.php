<?php

namespace App\Http\Controllers\Auth;

use App\Services\WebPasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

final class WebPasswordResetController
{
    public const NEUTRAL = 'If an eligible account matches that email, a password reset link will be sent. Please check your inbox and spam folder.';
    public const INVALID = 'This password reset link is invalid or expired. Please request a new link.';
    private int $retryAfter = 900;

    public function __construct(private readonly WebPasswordResetService $resets) {}

    private function protect(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    private function limit(string $key, int $maximum, int $seconds): bool
    {
        if (RateLimiter::tooManyAttempts($key, $maximum)) {
            $this->retryAfter = max(1, RateLimiter::availableIn($key));
            return false;
        }
        RateLimiter::hit($key, $seconds);

        return true;
    }

    private function throttled(): Response
    {
        return $this->protect(response('Too many attempts. Please try again later.', 429)->header('Retry-After', (string) $this->retryAfter));
    }

    public function requestLink(Request $request, string $role): Response
    {
        $prefix = $role === 'admin' ? '/admin' : '';
        if (! $request->isMethod('post')) {
            return $this->protect(response()->view($role === 'admin' ? 'admin.forgot-password-link' : 'frontend.forgot-password-link'));
        }
        $validator = Validator::make($request->only('email'), ['email' => 'required|string|email|max:255']);
        if ($validator->fails()) {
            return $this->protect(redirect($prefix.'/forgot-password')->with('flash_message_error', 'Please enter a valid email address.'));
        }
        $email = trim($request->string('email')->toString());
        $accountKey = hash('sha256', mb_strtolower($email));
        $ip = hash('sha256', (string) $request->ip());
        if (! $this->limit('web-reset-request-ip:'.$ip, 10, 900)
            || ! $this->limit('web-reset-request-account:'.$accountKey, 5, 3600)) {
            return $this->throttled();
        }
        // Apply the same resend cooldown/response to known, unknown and wrong-role emails.
        if ($this->limit('web-reset-cooldown:'.$accountKey, 1, 60)) {
            $this->resets->requestLink($email, $role);
        }

        return $this->protect(redirect($prefix.'/forgot-password')->with('flash_message_success', self::NEUTRAL));
    }

    public function reset(Request $request, string $id, #[\SensitiveParameter] string $token, string $role): Response
    {
        $prefix = $role === 'admin' ? '/admin' : '';
        $ip = hash('sha256', (string) $request->ip());
        if (! $this->limit('web-reset-view-ip:'.$ip, 30, 900)
            || ($request->isMethod('post') && (! $this->limit('web-reset-submit-ip:'.$ip, 10, 900)
                || ! $this->limit('web-reset-submit-account:'.hash('sha256', $role.':'.$id), 5, 900)))) {
            return $this->throttled();
        }
        if (! $this->resets->valid($id, $role, $token)) {
            return $this->protect(redirect($prefix.'/forgot-password')->with('flash_message_error', self::INVALID));
        }
        if ($request->isMethod('post')) {
            $validator = Validator::make($request->only('newPassword', 'confirmPassword'), [
                'newPassword' => ['required', 'string', Password::defaults(), function ($attribute, $value, $fail) {
                    if (is_string($value) && strlen($value) > 72) {
                        $fail('The password must not exceed 72 bytes.');
                    }
                }],
                'confirmPassword' => 'required|string|same:newPassword',
            ]);
            if ($validator->fails()) {
                // Never flash newPassword/confirmPassword into the session.
                return $this->protect(redirect($request->url())->withErrors($validator));
            }
            if (! $this->resets->reset($id, $role, $token, $request->string('newPassword')->toString())) {
                return $this->protect(redirect($prefix.'/forgot-password')->with('flash_message_error', self::INVALID));
            }

            return $this->protect(redirect($role === 'admin' ? '/admin' : '/login')->with('flash_message_success', 'Password Reset Successfully.'));
        }

        return $this->protect(response()->view($role === 'admin' ? 'admin.forgot-password' : 'frontend.forgot-password', [
            'user_id' => $id, 'slug' => $token,
        ]));
    }
}
