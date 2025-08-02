<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Helpers\ResponseData;
use App\Models\User;
use App\Models\Admin;

class UserAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $guard)
    {
        if (!Auth::guard($guard)->check()) {
            return response()->json([
                'message' => 'Unauthorized. Only ' . $guard . 's can access this route.'
            ], 401);
        }

        // Set the active guard for the rest of the request (optional)
        Auth::shouldUse($guard);

        return $next($request);
    }
}
