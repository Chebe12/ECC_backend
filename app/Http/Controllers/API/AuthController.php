<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Otp;
use Illuminate\Support\Facades\Cookie;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;


class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ])->validate();

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => bcrypt($validated['password']),
        ]);

        $this->sendOtp($user, 'email');

        return response()->json(['message' => 'Registered successfully. Verify your email.']);
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $cookie = cookie('token', $token, 60); // 60 minutes
        return response()->json(['message' => 'Logged in'])->withCookie($cookie);
    }

    public function sendOtp(User $user, $type = 'email')
    {
        $code = rand(100000, 999999);
        Otp::create([
            'user_id'    => $user->id,
            'code'       => $code,
            'type'       => $type,
            'expires_at' => now()->addMinutes(10),
        ]);

        if ($type === 'email') {
            // send email with $code
        } else {
            // send SMS with $code
        }
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code'  => 'required|digits:6',
            'type'  => 'required|in:email,phone',
        ]);

        $user = User::where('email', $request->email)->first();
        $otp = Otp::where('user_id', $user->id)
            ->where('type', $request->type)
            ->where('code', $request->code)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) return response()->json(['error' => 'Invalid or expired OTP'], 422);

        $otp->is_used = true;
        $otp->save();

        if ($request->type === 'email') {
            $user->email_verified = true;
            $user->email_verified_at = now();
        } else {
            $user->phone_verified = true;
            $user->phone_verified_at = now();
        }

        $user->save();

        return response()->json(['message' => ucfirst($request->type) . ' verified successfully']);
    }

    public function logout()
    {
        $cookie = cookie()->forget('token');
        return response()->json(['message' => 'Logged out'])->withCookie($cookie);
    }

    public function me()
    {
        return response()->json(auth()->user());
    }

    public function logout(Request $request)
    {
        try {
            auth()->logout(); // Invalidate the token
            $cookie = Cookie::forget('token');
            return response()->json(['message' => 'Logged out successfully'])->withCookie($cookie);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to logout'], 500);
        }
    }

    public function refreshToken(Request $request)
    {
        try {
            $newToken = JWTAuth::parseToken()->refresh();
            $cookie = cookie('token', $newToken, 60); // 60 minutes
            return response()->json(['message' => 'Token refreshed'])->withCookie($cookie);
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token expired, login again'], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to refresh token'], 500);
        }
    }
}
