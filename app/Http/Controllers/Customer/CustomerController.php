<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Helpers\ResponseData;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;


class CustomerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:customer');
    }

    public function me()
    {
        $customer = Auth::user();
        if (!$customer) {
            return ResponseData::error([], 'Customer not authenticated', 401);
        }
        return ResponseData::success($customer, 'Customer details retrieved successfully.', 200);
    }

    public function show($id)
    {
        $customer = User::findOrFail($id);
        if (!$customer) {
            return ResponseData::error([], 'Customer not found', 404);
        }
        if ($customer->is_deleted) {
            return ResponseData::error([], 'Customer has been deleted', 410);
        }
        if ($customer->is_banned) {
            return ResponseData::error([], 'Customer is banned', 403);
        }
        if (!$customer->is_active) {
            return ResponseData::error([], 'Customer is inactive', 403);
        }
        if ($customer->is_suspended) {
            return ResponseData::error([], 'Customer is suspended', 403);
        }
        // if ($customer->is_verified === false) {
        //     return ResponseData::error([], 'Customer is not verified', 403);
        // }

        return ResponseData::success($customer, 'Customer details retrieved successfully.', 200);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'country_code' => 'nullable|string',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'bio' => 'nullable|string|max:255',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($request->hasFile('profile_picture')) {
            $profilePath = $request->file('profile_picture')->store('profile_pictures', 'public');
            $validated['profile_picture'] = $profilePath;
        }

        if ($request->hasFile('cover_image')) {
            $coverPath = $request->file('cover_image')->store('cover_images', 'public');
            $validated['cover_image'] = $coverPath;
        }

        $user->update($validated);

        return ResponseData::success($user, 'Customer updated successfully.', 200);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return ResponseData::error([], 'Current password is incorrect', 400);
        }

        if (Hash::check($request->new_password, $user->password)) {
            return ResponseData::error([], 'New password must be different from the current password', 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();
        return ResponseData::success([], 'Password changed successfully', 200);
    }
}
