<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use App\Helpers\ResponseData;

class Authenticate
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // ✅ Check if token is actually present
            if (!$request->bearerToken()) {
                return ResponseData::error([], 'Please log in to continue.', 401);
            }

            // ✅ Try authenticating the user with the token
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return ResponseData::error([], 'Authentication failed. User not found.', 401);
            }

            // Optionally attach user to request
            $request->merge(['auth_user' => $user]);
        } catch (TokenExpiredException $e) {
            return ResponseData::error([], 'Your session has expired. Please log in again.', 401);
        } catch (TokenInvalidException $e) {
            return ResponseData::error([], 'The provided token is invalid.', 401);
        } catch (TokenBlacklistedException $e) {
            return ResponseData::error([], 'This token has been blacklisted. Please log in again.', 401);
        } catch (JWTException $e) {
            return ResponseData::error([], 'An error occurred while validating your session. Please log in.', 401);
        }

        return $next($request);
    }
}
