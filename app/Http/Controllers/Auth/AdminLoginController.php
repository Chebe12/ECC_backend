<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Services\OtpService;
use App\Helpers\ResponseData;
use Illuminate\Support\Facades\Auth;


class AdminLoginController extends Controller
{
    public function login(Request $request, OtpService $otpService)
    {
        // Get credentials
        $credentials = $request->only('email', 'password');

        // Specify the guard to use for this authentication attempt (admin guard)
        auth()->shouldUse('admin');

        try {
            // Try to authenticate the admin using the 'admin' guard
            if (!$token = JWTAuth::attempt($credentials)) {
                return ResponseData::error([], 'Invalid credentials', 401);
            }

            // Retrieve the authenticated admin using the specified guard
            $admin = auth()->user();

            // No email verification for admins, proceed directly to token generation
            $token = JWTAuth::fromUser($admin);
            $cookie = cookie('token', $token, 120); // Token valid for 60 minutes

            // Return the token and admin details in the response
            return ResponseData::withToken($token, $admin, 'Logged in successfully')->withCookie($cookie);
        } catch (JWTException $e) {
            return ResponseData::error([], 'Could not create token.', 500);
        }
    }


    public function logout(Request $request)
    {
        // Specify which guard to use for logout (admin guard in this case)
        auth()->shouldUse('admin');

        // Invalidate the JWT token for admin
        JWTAuth::logout();

        // Clear the cookie by setting it to null and making it expired
        $cookie = cookie('token', null, -1);

        return ResponseData::success([], 'Logged out successfully')->withCookie($cookie);
    }
    public function refresh(Request $request)
    {
        // Specify which guard to use for token refresh (admin guard in this case)
        auth()->shouldUse('admin');

        try {
            // Refresh the JWT token
            $token = JWTAuth::refresh();
            $cookie = cookie('token', $token, 60); // Token valid for 60 minutes

            return ResponseData::withToken($token, null, 'Token refreshed successfully')->withCookie($cookie);
        } catch (JWTException $e) {
            return ResponseData::error([], 'Could not refresh token.', 500);
        }
    }
}
