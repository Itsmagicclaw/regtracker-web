<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PanelAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!session('panel_auth')) {
            return redirect('/panel/login');
        }
        return $next($request);
    }
}
