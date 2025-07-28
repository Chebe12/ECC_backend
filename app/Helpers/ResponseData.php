<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Illuminate\Http\Response;


class ResponseData
{
    public static function success($data = [], $message = 'Success', $code = 200)
    {
        return response()->json([
            'message' => $message,
            'status' => 'success',
            'data' => $data,
        ], $code);
    }

    public static function error($errors = [], $message = 'Error', $code = 422)
    {
        return response()->json([
            'message' => $message,
            'status' => 'error',
            'errors' => $errors,
        ], $code);
    }

    public static function withToken($token, $data = [], $message = 'Success', $code = 200)
    {
        return response()->json([
            'message' => $message,
            'status' => 'success',
            'data' => $data,
            'token' => $token,
        ], $code);
    }


    public static function withUserAuth($user, $message = 'Success', $code = 200): JsonResponse
    {
        return self::authResponse($user, 'api', $message, $code);
    }

    /**
     * Admin Authentication Response
     * 
     * Generates JWT token for administrators and sets it in a separate HTTP-only cookie
     * 
     * @param mixed $admin    Authenticated admin object
     * @param string $message Success message
     * @param int $code       HTTP status code (default: 200)
     * @return JsonResponse
     */
    public static function withAdminAuth($admin, $message = 'Success', $code = 200): JsonResponse
    {
        return self::authResponse($admin, 'admin', $message, $code);
    }

    /**
     * Base Authentication Response Handler
     * 
     * @param mixed $user     Authenticated user/admin object
     * @param string $guard   Authentication guard (api/admins)
     * @param string $message Success message
     * @param int $code       HTTP status code
     * @return JsonResponse
     */
    protected static function authResponse($user, $guard, $message, $code): JsonResponse
    {
        // Generate guard-specific JWT token
        $token = auth($guard)->login($user);

        // Determine cookie name and TTL based on guard
        $cookieName = 'jwt_token';
        $ttlConfig = 'jwt.ttl';

        return response()->json([
            'message' => $message,
            'status' => 'success',
            'data' => $user->only(['id', 'name', 'email']),
        ], $code)->withCookie(self::createCookie($cookieName, $token, config($ttlConfig)));
    }

    /**
     * Create Secure Authentication Cookie
     * 
     * @param string $name     Cookie name
     * @param string $token    JWT token value
     * @param int $minutes     Token lifetime in minutes
     * @return Cookie
     */
    public static function createCookie($name, $token, $minutes): Cookie
    {
        return cookie(
            $name,
            $token,
            $minutes * 60,   // Convert minutes to seconds
            null,            // Path (entire domain)
            null,            // Domain
            config('app.env') === 'production', // Secure flag
            true,            // HttpOnly
            false,           // Raw
            'Lax'            // SameSite policy
        );
    }

    /**
     * Clear Authentication Cookie
     * 
     * @param string $guard Authentication guard (api/admins)
     * @return JsonResponse
     */
    // public static function clearAuthCookie($guard = 'api'): JsonResponse
    // {
    //     $cookieName = $guard === 'admin' ? 'jwt_token' : 'jwt_token';

    //     return response()->json([
    //         'message' => 'Logged out successfully',
    //         'status' => 'success',
    //         'data' => [],
    //     ])->withoutCookie($cookieName);
    // }

    public static function clearAuthCookie($guard = 'api'): JsonResponse
    {
        try {
            // Invalidate the token to prevent further use
            auth($guard)->logout();
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'message' => 'Failed to logout. Please try again.',
                'status' => 'error'
            ], 500);
        }

        $cookieName = 'jwt_token';

        return response()->json([
            'message' => 'Logged out successfully',
            'status' => 'success',
            'data' => [],
        ])->withoutCookie($cookieName);
    }
}
