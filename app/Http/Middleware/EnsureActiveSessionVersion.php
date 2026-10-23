<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureActiveSessionVersion
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $key = 'auth_version_'.$user->id;
            $sessionVersion = $request->session()->get($key);

            if (($user->banned && (int) $user->banned !== 0)
                || ($sessionVersion !== null && (int) $sessionVersion !== (int) $user->auth_version)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Your session is no longer valid. Please sign in again.'], 401);
                }
                return redirect('/login');
            }
            if ($sessionVersion === null) {
                $request->session()->put($key, (int) $user->auth_version);
            }
        }

        return $next($request);
    }
}
