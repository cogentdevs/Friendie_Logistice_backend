<?php

namespace App\Http\Controllers\Api\V1;

use App\Mail\MobileAccountMail;
use App\Models\User;
use App\Services\EmailActivationService;
use App\Services\WebPasswordResetService;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AuthController
{
    public const REGISTERED = 'If registration is available for these details, a verification email will be sent. Please check your inbox and spam folder.';
    public const RECOVERY = 'If an eligible account matches that email, a link will be sent. Please check your inbox and spam folder.';

    public function __construct(
        private readonly EmailActivationService $activation,
        private readonly WebPasswordResetService $resets,
    ) {}

    private function limit(string $key, int $max, int $seconds): void
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw new HttpException(429, '', null, ['Retry-After' => (string) max(1, RateLimiter::availableIn($key))]);
        }
        RateLimiter::hit($key, $seconds);
    }

    private function input(Request $request, array $rules): array
    {
        $unknown = array_diff(array_keys($request->all()), array_keys($rules));
        if ($unknown !== []) {
            throw ValidationException::withMessages(array_fill_keys($unknown, ['This field is not allowed.']));
        }

        return $request->validate($rules);
    }

    private function email(Request $request): string
    {
        $data = $this->input($request, ['email' => 'required|string|email|max:255']);

        return EmailActivationService::normalizeEmail($data['email']);
    }

    private function passwords(): array
    {
        return ['required', 'string', 'confirmed', Password::defaults(), function ($attribute, $value, $fail) {
            if (is_string($value) && strlen($value) > 72) {
                $fail('The password must not exceed 72 bytes.');
            }
        }];
    }

    private function delivery(bool $activation): \Closure
    {
        return static function (string $email, array $data) use ($activation): void {
            $url = $data[$activation ? 'activation_url' : 'reset_url'];
            if (! app()->environment(['local', 'testing']) && parse_url($url, PHP_URL_SCHEME) !== 'https') {
                throw new \LogicException('Account mail requires HTTPS.');
            }
            Mail::to($email)->send(new MobileAccountMail($data, $activation));
        };
    }

    private function profile(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
    }

    public function register(Request $request): JsonResponse
    {
        $data = $this->input($request, [
            'name' => 'required|string|max:255', 'email' => 'required|string|email|max:255',
            'phone' => 'required|string|max:255',
            'country_id' => ['required', 'integer', 'min:1', Rule::exists('countries', 'id')->where('isActive', 1)],
            'password' => $this->passwords(), 'password_confirmation' => 'required|string',
        ]);
        $data['email'] = EmailActivationService::normalizeEmail($data['email']);
        $id = null;
        try {
            $id = DB::transaction(function () use ($data) {
                if (User::where('email', $data['email'])->orWhere('phone', $data['phone'])->exists()) {
                    return null;
                }
                $role = Role::findByName('user', 'web');
                $user = new User;
                foreach (['name', 'email', 'phone', 'country_id'] as $field) {
                    $user->{$field} = $data[$field];
                }
                $user->password = Hash::make($data['password']);
                $user->image = 'avatar.jpg';
                $user->isActive = 1;
                $user->isVerified = 0;
                $user->email_verified_at = null;
                $user->save();
                $user->assignRole($role);

                return $user->id;
            });
        } catch (QueryException $exception) {
            // A concurrent duplicate registration receives the same neutral response.
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }
        }
        if ($id !== null) {
            $this->activation->send($id, $this->delivery(true));
        }

        return ApiResponse::success(self::REGISTERED, status: 201);
    }

    public function login(Request $request): JsonResponse
    {
        $this->limit('mobile-login-ip:'.hash('sha256', (string) $request->ip()), 20, 900);
        $data = $this->input($request, ['email' => 'required|string|email|max:255', 'password' => 'required|string|max:255']);
        $email = EmailActivationService::normalizeEmail($data['email']);
        $this->limit('mobile-login-account:'.hash('sha256', $email), 5, 900);
        $result = DB::transaction(function () use ($email, $data) {
            $user = User::where('email', $email)->lockForUpdate()->first();
            // A valid dummy hash keeps unknown-account password verification non-trivial.
            $hash = $user?->password ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
            $valid = Hash::check($data['password'], $hash);
            if (! $valid || ! $user || ! $this->activation->customer($user)
                || (int) $user->isActive !== 1 || (int) $user->isVerified !== 1) {
                return null;
            }
            $minutes = (int) config('sanctum.expiration', 43200);
            if ($minutes < 1) {
                throw new \LogicException('Mobile tokens require a positive expiration.');
            }
            $token = $user->createToken('mobile', ['mobile:account'], now()->addMinutes($minutes));

            return ['token' => $token->plainTextToken, 'token_type' => 'Bearer', 'user' => $this->profile($user)];
        });

        return $result === null ? ApiResponse::error('Invalid credentials.', 401)
            : ApiResponse::success('Login successful.', $result)->header('Cache-Control', 'no-store, private');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success('Account retrieved successfully.', $this->profile($request->user()))
            ->header('Cache-Control', 'no-store, private');
    }

    public function logout(Request $request): JsonResponse
    {
        // Middleware guarantees a real personal access token, never a web/transient token.
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success('Logout successful.');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $this->limit('web-reset-request-ip:'.hash('sha256', (string) $request->ip()), 10, 900);
        $email = $this->email($request);
        $key = hash('sha256', $email);
        $this->limit('web-reset-request-account:'.$key, 5, 3600);
        if (! RateLimiter::tooManyAttempts('web-reset-cooldown:'.$key, 1)) {
            RateLimiter::hit('web-reset-cooldown:'.$key, 60);
            $this->resets->requestLink($email, 'user', $this->delivery(false));
        }

        return ApiResponse::success(self::RECOVERY);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $this->limit('web-reset-submit-ip:'.hash('sha256', (string) $request->ip()), 10, 900);
        $data = $this->input($request, [
            'user_id' => ['required', 'regex:/\A[1-9][0-9]{0,18}\z/'],
            'token' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'password' => $this->passwords(), 'password_confirmation' => 'required|string',
        ]);
        $this->limit('web-reset-submit-account:'.hash('sha256', 'user:'.$data['user_id']), 5, 900);
        $reset = DB::transaction(function () use ($data) {
            if (! $this->resets->reset((string) $data['user_id'], 'user', $data['token'], $data['password'])) {
                return false;
            }
            // The shared reset retains its account lock until this outer transaction commits.
            // Classify by the explicit ability issued by mobile login, never by device name or '*'.
            User::findOrFail($data['user_id'])->tokens()->whereJsonContains('abilities', 'mobile:account')->delete();

            return true;
        });
        if (! $reset) {
            return ApiResponse::error('Invalid or expired reset link.', 422);
        }

        return ApiResponse::success('Password reset successfully.');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $this->limit('activation-resend-ip:'.hash('sha256', (string) $request->ip()), 10, 900);
        $email = $this->email($request);
        $key = hash('sha256', $email);
        $this->limit('activation-resend-account:'.$key, 5, 3600);
        if (! RateLimiter::tooManyAttempts('activation-resend-cooldown:'.$key, 1)) {
            RateLimiter::hit('activation-resend-cooldown:'.$key, 60);
            $user = User::where('email', $email)->first();
            if ($user) {
                $this->activation->send($user->id, $this->delivery(true));
            }
        }

        return ApiResponse::success(self::RECOVERY);
    }
}
