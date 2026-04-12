<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PortalAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!session('portal_auth')) {
            return redirect('/portal/login');
        }
        return $next($request);
    }
}
