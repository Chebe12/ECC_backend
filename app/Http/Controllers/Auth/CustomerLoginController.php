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

class CustomerLoginController extends Controller
{
    protected $otpService;
    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    public function registerCustomer(Request $request)
    {
        // Validate customer registration request
        $validated = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:customers',
            'password' => 'required|string|min:6|confirmed',
            'country_code' => 'nullable|string',
            'phone' => 'nullable|string',
        ])->validate();

        // Create the customer
        $customer = Customer::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => bcrypt($validated['password']),
            'country_code' => $validated['country_code'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'role' => 'customer', // Customer role
        ]);

        // Send OTP to email for verification
        $this->otpService->sendEmailOtp($customer);

        // Return success response asking customer to verify their email
        return ResponseData::success($customer, 'Registered successfully. Verify your email.', 201);
    }

    public function login(Request $request, OtpService $otpService)
    {
        // Get credentials
        $credentials = $request->only('email', 'password');

        // Specify the guard to use for this authentication attempt (customer guard)
        auth()->shouldUse('customer');

        try {
            // Try to authenticate the customer using the 'customer' guard
            if (!$token = JWTAuth::attempt($credentials)) {
                return ResponseData::error([], 'Invalid credentials', 401);
            }

            // Retrieve the authenticated customer using the specified guard
            $customer = auth()->user();

            // Check if email is verified for customers
            if (!$customer->email_verified) {
                // Send OTP for email verification
                $otpService->sendEmailOtp($customer);
                return ResponseData::error([], 'Email not verified. OTP sent.', 403);
            }

            // Generate JWT token for the customer
            $token = JWTAuth::fromUser($customer);
            $cookie = cookie('token', $token, 60); // Token valid for 60 minutes

            // Return the token and customer details in the response
            return ResponseData::withToken($token, $customer, 'Logged in successfully')->withCookie($cookie);
        } catch (JWTException $e) {
            return ResponseData::error([], 'Could not create token.', 500);
        }
    }


    public function sendOtp(Request $request)
    {
        // Validate the incoming request
        $request->validate(['email' => 'required|email']);

        // Find user by email (could be customer or user)
        $user = null;

        // Try to find the user as a customer first, then a user
        $user = Customer::where('email', $request->email)->first(); // Look for customer
        if (!$user) {
            $user = User::where('email', $request->email)->first(); // Look for user
        }

        // If no user is found
        if (!$user) {
            return ResponseData::error([], 'User not found', 404);
        }

        // If email is already verified, no need to send OTP
        if ($user->email_verified) {
            return ResponseData::error([], 'Email is already verified', 400);
        }

        // Send OTP using the OtpService
        $this->otpService->sendEmailOtp($user);

        // Return success response
        return ResponseData::success([], 'OTP sent successfully');
    }

    public function verifyOtp(Request $request)
    {
        try {
            // Validate the incoming request
            $request->validate([
                'email' => 'required|email',
                'otp'   => 'required|digits:6',
            ]);

            // Look for the user (either customer or user) by email
            $user = Customer::where('email', $request->email)->first(); // First try to find as customer
            if (!$user) {
                $user = User::where('email', $request->email)->first(); // Look for user if no customer found
            }

            // If user (customer or user) not found
            if (!$user) {
                return ResponseData::error([], 'User not found', 404);
            }

            // If email is already verified, no need for OTP
            if ($user->email_verified) {
                return ResponseData::error([], 'Email is already verified', 200);
            }

            // Verify OTP
            $verified = $this->otpService->verifyOtp($user, $request->otp);

            // If OTP is invalid or expired
            if (!$verified) {
                return ResponseData::error([], 'Invalid or expired OTP', 400);
            }

            // Mark email as verified
            $user->email_verified = true;
            $user->save();

            // Generate JWT token after verification
            $token = JWTAuth::fromUser($user);
            $cookie = cookie('token', $token, 60); // Token valid for 60 minutes

            // Return success response with token and user data
            return ResponseData::withToken($token, $user, 'Email verified and logged in successfully')->withCookie($cookie);
        } catch (\Exception $e) {
            // Handle errors
            return ResponseData::error(['error' => $e->getMessage()], 'An error occurred while verifying OTP', 500);
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
