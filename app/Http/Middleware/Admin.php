<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Services\Admin\AdminAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Admin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check() || !Role::where('user_id', Auth::id())->where('role', 1)->exists()) {
            abort(404);
        }

        $access = app(AdminAccessService::class);
        $required = $access->requiredPermission($request->route());
        if ($required === 'admin.panel' && $access->canAccessPanel(Auth::user())) {
            return $next($request);
        }
        if ($required === 'admin.super_admin' && $access->isSuperAdmin(Auth::user())) {
            return $next($request);
        }
        if (!$required || !$access->can(Auth::user(), $required)) {
            abort(403, 'You are not authorized to perform this action.');
        }

        return $next($request);
    }
}
