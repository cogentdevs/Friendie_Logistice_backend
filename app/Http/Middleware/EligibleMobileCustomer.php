<?php

namespace App\Http\Middleware;

use App\Services\EmailActivationService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class EligibleMobileCustomer
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        abort_unless($token instanceof PersonalAccessToken, 401);
        abort_unless((int) $user->isActive === 1 && (int) $user->isVerified === 1
            && app(EmailActivationService::class)->customer($user)
            && $token->can('mobile:account'), 403);

        return $next($request);
    }
}
