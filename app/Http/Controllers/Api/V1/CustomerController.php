<?php

namespace App\Http\Controllers\Api\V1;

use App\Mail\MobileAccountMail;
use App\Models\User;
use App\Services\Api\CustomerAvatarService;
use App\Services\CustomerEmailChangeService;
use App\Services\EmailActivationService;
use App\Services\WebPasswordResetService;
use App\Support\Api\PublicData;
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
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CustomerController
{
    private function input(Request $request, array $rules): array
    {
        $unknown = array_diff(array_keys($request->all()), array_keys($rules));
        if ($unknown !== []) {
            throw ValidationException::withMessages(array_fill_keys($unknown, ['This field is not allowed.']));
        }

        return $request->validate($rules);
    }

    private function limit(Request $request, string $operation, int $max, int $seconds): void
    {
        foreach (['account:'.$request->user()->id, 'ip:'.hash('sha256', (string) $request->ip())] as $scope) {
            $key = 'customer-'.$operation.':'.$scope;
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw new HttpException(429, '', null, ['Retry-After' => (string) max(1, RateLimiter::availableIn($key))]);
            }
            RateLimiter::hit($key, $seconds);
        }
    }

    private function lockedCustomer(Request $request): User
    {
        $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
        abort_unless((int) $user->isActive === 1 && (int) $user->isVerified === 1
            && app(EmailActivationService::class)->customer($user), 403);
        // Serialize with concurrent password/email updates; reject a token already revoked by one.
        abort_unless($user->tokens()->whereKey($request->user()->currentAccessToken()->getKey())->exists(), 401);

        return $user;
    }

    private function profileData(User $user): array
    {
        $country = $user->country_id === null ? null : DB::table('countries')
            ->select('id', 'name', 'short_name')->where('id', $user->country_id)->first();

        return [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone,
            'country_id' => $user->country_id === null ? null : (int) $user->country_id,
            'country' => $country ? ['id' => (int) $country->id, 'name' => $country->name, 'short_name' => $country->short_name] : null,
            'image_url' => PublicData::image($user->image, 'frontend/img/profiles'),
            'is_verified' => (int) $user->isVerified === 1,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
        ];
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->input($request, []);
        $id = $request->user()->id;
        $counts = DB::table('post_ads')->where('created_by', $id)->selectRaw('COUNT(*) AS total,
            COALESCE(SUM(isActive = 1), 0) AS published, COALESCE(SUM(isActive = 0), 0) AS pending,
            COALESCE(SUM(isActive = 2), 0) AS expired')->first();
        $unread = DB::table('post_ad_room_messages as message')
            ->join('post_ad_rooms as room', 'room.id', '=', 'message.post_ad_room_id')
            ->join('post_ads as listing', 'listing.id', '=', 'room.post_ads_id')
            ->where('message.to_user_id', $id)->whereNull('message.read_by')
            ->where(function ($query) use ($id) {
                $query->where(function ($q) use ($id) {
                    $q->where('room.from_user_id', $id)->whereColumn('message.from_user_id', 'room.to_user_id')
                        ->whereColumn('message.to_user_id', 'room.from_user_id');
                })->orWhere(function ($q) use ($id) {
                    $q->where('room.to_user_id', $id)->whereColumn('message.from_user_id', 'room.from_user_id')
                        ->whereColumn('message.to_user_id', 'room.to_user_id');
                });
            })->count();

        return ApiResponse::success('Dashboard retrieved successfully.', [
            'listings' => array_map('intval', (array) $counts),
            'unread_messages' => $unread,
            'reviews_received' => DB::table('reviews')->where('to_user_id', $id)->count(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function profile(Request $request): JsonResponse
    {
        $this->input($request, []);

        return ApiResponse::success('Profile retrieved successfully.', $this->profileData($request->user()))
            ->header('Cache-Control', 'no-store, private');
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $this->limit($request, 'profile', 10, 900);
        $data = $this->input($request, [
            'name' => 'sometimes|required|string|max:255', 'email' => 'sometimes|required|string|email|max:255',
            'phone' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users', 'phone')->ignore($request->user()->id)],
            'country_id' => ['sometimes', 'required', 'integer', 'min:1', Rule::exists('countries', 'id')->where('isActive', 1)],
        ]);
        if ($data === []) {
            throw ValidationException::withMessages(['profile' => 'Provide at least one supported profile field.']);
        }
        try {
            [$user, $changed] = DB::transaction(function () use ($request, $data) {
                $user = $this->lockedCustomer($request);
                $attributes = $data + ['email' => $user->email];
                $changed = app(CustomerEmailChangeService::class)->update($user->id, $attributes,
                    static function (string $email, array $data): void {
                        Mail::to($email)->send(new MobileAccountMail($data, true));
                    });
                if ($changed) {
                    // Revoke every personal access token for this account, regardless of device name/ability.
                    // This includes all mobile devices; unrelated website sessions are untouched.
                    $user->tokens()->delete();
                }

                return [$user->fresh(), $changed];
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }
            throw ValidationException::withMessages(['profile' => 'These profile details are unavailable.']);
        }

        return ApiResponse::success($changed ? 'Profile updated. Verify your new email and sign in again.' : 'Profile updated successfully.', [
            'profile' => $this->profileData($user), 'verification_required' => $changed, 'reauthentication_required' => $changed,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function uploadAvatar(Request $request, CustomerAvatarService $avatars): JsonResponse
    {
        $this->limit($request, 'avatar', 10, 3600);
        $data = $this->input($request, ['avatar' => ['required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp',
            'extensions:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=1,min_height=1,max_width=2048,max_height=2048']]);
        $name = null;
        try {
            $user = DB::transaction(function () use ($request, $data, $avatars, &$name) {
                $user = $this->lockedCustomer($request);
                $name = $avatars->store($user->id, $data['avatar']);
                DB::table('users')->where('id', $user->id)->update(['image' => $name]);

                return $user->fresh();
            });
        } catch (\Throwable $exception) {
            if ($name !== null) {
                $avatars->discardNew($name, $request->user()->id);
            }
            throw $exception;
        }

        return ApiResponse::success('Avatar updated successfully.', ['image_url' => PublicData::image($user->image, 'frontend/img/profiles')]);
    }

    public function removeAvatar(Request $request): JsonResponse
    {
        $this->input($request, []);
        $this->limit($request, 'avatar', 10, 3600);
        $user = DB::transaction(function () use ($request) {
            $user = $this->lockedCustomer($request);
            DB::table('users')->where('id', $user->id)->update(['image' => 'avatar.jpg']);

            return $user->fresh();
        });

        // Reference removal only. Never delete legacy, shared/default or another account's files.
        return ApiResponse::success('Avatar removed successfully.', ['image_url' => PublicData::image($user->image, 'frontend/img/profiles')]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $this->limit($request, 'password', 5, 900);
        $data = $this->input($request, [
            'current_password' => 'required|string|max:255',
            'password' => ['required', 'string', 'confirmed', Password::defaults(), 'different:current_password',
                function ($attribute, $value, $fail) {
                    if (is_string($value) && strlen($value) > 72) {
                        $fail('The password must not exceed 72 bytes.');
                    }
                }],
            'password_confirmation' => 'required|string',
        ]);
        DB::transaction(function () use ($request, $data) {
            $user = $this->lockedCustomer($request);
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
            }
            DB::table('users')->where('id', $user->id)->update(['password' => Hash::make($data['password'])]);
            DB::table(config('auth.passwords.users.table'))->where('email',
                app(WebPasswordResetService::class)->identity($user, 'user')->getEmailForPasswordReset())->delete();
            $user->tokens()->delete();
        });

        return ApiResponse::success('Password changed successfully. Sign in again.', ['reauthentication_required' => true]);
    }
}
