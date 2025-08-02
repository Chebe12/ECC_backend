<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Services\OtpService;
use App\Helpers\ResponseData;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Customer;
use App\Models\Admin;
use App\Models\Bid;
use App\Models\Auction;
use App\Models\Otp;
use Illuminate\Support\Facades\Cookie;
use Carbon\Carbon;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;





class UserLoginController extends Controller
{
    protected $otpService;
    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    public function register(Request $request, OtpService $otpService)
    {
        // Validate user registration request
        $validated = Validator::make($request->all(), [
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users',
            'password'      => 'required|string|min:6|confirmed',
            'country_code'  => 'nullable|string',
            'phone'         => 'nullable|string',
        ])->validate();

        // Create the user
        $user = User::create([
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'password'      => bcrypt($validated['password']),
            'country_code'  => $validated['country_code'] ?? null,
            'phone'         => $validated['phone'] ?? null,
            'role'          => 'user', // User role
        ]);

        // Send OTP to email for verification
        // $otpService->sendEmailOtp($user);

        // Generate JWT token for the newly registered user
        $token = JWTAuth::fromUser($user);

        // You can also set the token in a cookie for the client
        $cookie = cookie('token', $token, 60); // Token valid for 60 minutes

        // Return success response, asking user to verify their email
        return ResponseData::withToken($token, $user, 'Registered successfully. Verify your email.')->withCookie($cookie);
    }


    // public function login(Request $request)
    // {
    //     // Validate input credentials
    //     $credentials = $request->only('email', 'password');

    //     try {
    //         // Specify which guard to use (in this case, 'customer')
    //         auth()->shouldUse('user');

    //         // Attempt to authenticate the customer with the credentials
    //         if (!$token = JWTAuth::attempt($credentials)) {
    //             return ResponseData::error([], 'invalid', 400);
    //         }

    //         // Get the authenticated customer user
    //         $customer = auth()->user();

    //         // Optionally check if email is verified
    //         // if (!$customer->email_verified) {
    //         //     return ResponseData(false, 403, "Email not verified.", false, false, false);
    //         // }

    //         // Generate the JWT token for the customer
    //         $token = JWTAuth::fromUser($customer);
    //         $cookie = cookie('token', $token, 60); // 60 minutes

    //         return ResponseData::withToken($token, $customer, 'Logged in successfully')->withCookie($cookie);
    //     } catch (JWTException $e) {
    //         return ResponseData::error([], 'Could not create token', 500);
    //     }
    // }


    public function login(Request $request, OtpService $otpService)
    {
        $credentials = $request->only('email', 'password');

        // Specify the guard to use for this authentication attempt (user guard)
        auth()->shouldUse('user');

        try {
            // Attempt to authenticate the user using the 'user' guard
            if (!$token = JWTAuth::attempt($credentials)) {
                return ResponseData::error([], 'Invalid credentials', 401);
            }

            // Retrieve the authenticated user using the specified guard
            $user = auth()->user();

            // Check if the user's email is verified
            // if (!$user->email_verified) {
            //     // If not verified, send OTP for verification
            //     $otpService->sendEmailOtp($user);
            //     return ResponseData::error([], 'Email not verified. OTP sent.', 403);
            // }

            // Generate JWT token for the user
            $token = JWTAuth::fromUser($user);
            $cookie = cookie('token', $token, 120); // Token valid for 60 minutes

            // Return the token and user details in the response
            return ResponseData::withToken($token, $user, 'Logged in successfully')->withCookie($cookie);
        } catch (JWTException $e) {
            return ResponseData::error([], 'Could not create token.', 500);
        }
    }


    public function sendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->firstOrFail();

        $this->otpService->sendEmailOtp($user);
        return ResponseData::success([], 'OTP sent successfully');
    }

    public function verifyOtp(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'otp'   => 'required|digits:6',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return ResponseData::error([], 'User not found', 404);
            }

            if ($user->email_verified) {
                return ResponseData::error([], 'Email is already verified', 200);
            }

            $verified = $this->otpService->verifyOtp($user, $request->otp);

            if (!$verified) {
                return ResponseData::error([], 'Invalid or expired OTP', 400);
            }

            // Mark email as verified
            $user->email_verified = true;
            $user->save();

            // Generate token after verification
            $token = JWTAuth::fromUser($user);
            $cookie = cookie('token', $token, 60); // token valid for 60 mins

            return ResponseData::withToken($token, $user, 'Email verified and logged in successfully')->withCookie($cookie);
        } catch (\Exception $e) {
            return ResponseData::error(['error' => $e->getMessage()], 'An error occurred while verifying OTP', 500);
        }
    }

    public function logout(Request $request)
    {
        // Specify the guard if needed
        auth()->shouldUse('user');

        try {
            // Invalidate the token
            JWTAuth::invalidate(JWTAuth::getToken());

            // Clear the cookie
            $cookie = cookie('token', null, -1);

            return ResponseData::success([], 'Logged out successfully')->withCookie($cookie);
        } catch (\Exception $e) {
            return ResponseData::error([], 'Logout failed: ' . $e->getMessage(), 500);
        }
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
