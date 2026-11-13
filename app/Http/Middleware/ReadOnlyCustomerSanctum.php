<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Guard;

/** Use the installed Sanctum guard without its optional last_used_at write on customer GETs. */
final class ReadOnlyCustomerSanctum implements AuthenticatesRequests
{
    public function handle(Request $request, Closure $next)
    {
        $guard = new Guard(Auth::getFacadeRoot(), config('sanctum.expiration'),
            config('auth.guards.sanctum.provider'), trackLastUsedAt: false);
        $user = $guard($request);
        abort_unless($user, 401);
        // auth:sanctum still runs next, using the user validated by the exact framework guard.
        // No global Sanctum configuration, model or web guard is changed.
        Auth::guard('sanctum')->setUser($user);

        return $next($request);
    }
}
