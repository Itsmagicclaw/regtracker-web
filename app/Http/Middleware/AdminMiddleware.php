<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $secret = config('regtracker.admin_secret');

        if (!$token || $token !== $secret) {
            return response()->json(['error' => 'Unauthorised'], 401);
        }

        return $next($request);
    }
}
