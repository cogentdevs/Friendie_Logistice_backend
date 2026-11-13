<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Services\EmailActivationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

final class EmailActivationController
{
    public const NEUTRAL = 'If an eligible unverified account matches that email, an activation link will be sent. Please check your inbox and spam folder.';
    private int $retry = 900;

    public function __construct(private readonly EmailActivationService $activation) {}

    private function response(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    private function limit(string $key, int $max, int $seconds): bool
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            $this->retry = max(1, RateLimiter::availableIn($key));
            return false;
        }
        RateLimiter::hit($key, $seconds);

        return true;
    }

    private function throttled(): Response
    {
        return $this->response(response('Too many attempts. Please try again later.', 429)->header('Retry-After', (string) $this->retry));
    }

    public function confirm(Request $request, string $id, #[\SensitiveParameter] string $slug): Response
    {
        if (! $this->limit('activation-confirm:'.hash('sha256', (string) $request->ip()), 30, 900)) {
            return $this->throttled();
        }
        $state = $request->isMethod('post') ? $this->activation->verify($request, $id, $slug)
            : $this->activation->inspect($request, $id, $slug);
        if ($state === 'invalid') {
            return $this->response(redirect('/resend-activation')->with('flash_message_error',
                'This activation link is invalid, expired, or unavailable for this account. Request a new link or contact support.'));
        }
        if ($state === 'verified' || $state === 'already_verified') {
            return $this->response(redirect('/login')->with('flash_message_success', 'Email verification is complete. You can sign in if your account is enabled.'));
        }

        return $this->response(response()->view('frontend.activate-account', ['activation_url' => $request->fullUrl()]));
    }

    public function resend(Request $request): Response
    {
        if (! $request->isMethod('post')) {
            return $this->response(response()->view('frontend.resend-activation'));
        }
        $validator = Validator::make($request->only('email'), ['email' => 'required|string|email|max:255']);
        if ($validator->fails()) {
            return $this->response(redirect('/resend-activation')->with('flash_message_error', 'Please enter a valid email address.'));
        }
        $email = EmailActivationService::normalizeEmail($request->string('email')->toString());
        $key = hash('sha256', $email);
        if (! $this->limit('activation-resend-ip:'.hash('sha256', (string) $request->ip()), 10, 900)
            || ! $this->limit('activation-resend-account:'.$key, 5, 3600)) {
            return $this->throttled();
        }
        if ($this->limit('activation-resend-cooldown:'.$key, 1, 60)) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $this->activation->send($user->id);
            }
        }

        return $this->response(redirect('/resend-activation')->with('flash_message_success', self::NEUTRAL));
    }
}
