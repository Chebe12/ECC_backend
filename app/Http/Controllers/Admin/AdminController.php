<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Otp;
use App\Models\Role;
use App\Helpers\ResponseData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    public function me()
    {
        $user = Auth::guard('admin')->user();

        if (!$user) {
            return ResponseData::error([], 'Admin not authenticated', 401);
        }

        return ResponseData::success($user, 'Admin details retrieved successfully.', 200);
    }
    public function show($id)
    {
        $admin = Admin::findOrFail($id);
        if (!$admin) {
            return ResponseData::error([], 'Admin not found', 404);
        }
        if ($admin->is_deleted) {
            return ResponseData::error([], 'Admin has been deleted', 410);
        }
        if ($admin->is_banned) {
            return ResponseData::error([], 'Admin is banned', 403);
        }
        if (!$admin->is_active) {
            return ResponseData::error([], 'Admin is inactive', 403);
        }
        if ($admin->is_suspended) {
            return ResponseData::error([], 'Admin is suspended', 403);
        }
        return ResponseData::success($admin, 'Admin details retrieved successfully.', 200);
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

        return ResponseData::success($user, 'User updated successfully.', 200);
    }



    public function getAdmins()
    {
        $admins = Admin::where('is_deleted', false)->get();
        return ResponseData::success($admins, 'Admin users retrieved successfully');
    }

    public function getCustomers()
    {
        $customers = Customer::where('is_deleted', false)->get();
        return ResponseData::success($customers, 'Customer users retrieved successfully');
    }

    public function getUsers()
    {
        $users = User::where('is_deleted', false)->get();
        return ResponseData::success($users, 'General users retrieved successfully');
    }

    public function banUser($id)
    {
        $user = User::findOrFail($id);
        $user->is_banned = true;
        $user->is_active = false;
        $user->save();

        return ResponseData::success($user, 'User has been banned.');
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        $user->is_deleted = true;
        $user->is_active = false;
        $user->save();

        return ResponseData::success(null, 'User has been soft deleted.');
    }

    public function suspendUser($id)
    {
        $user = User::findOrFail($id);
        $user->is_active = false;
        $user->save();

        return ResponseData::success($user, 'User has been suspended.');
    }

    public function reactivateUser($id)
    {
        $user = User::findOrFail($id);
        $user->is_active = true;
        $user->is_banned = false;
        $user->is_deleted = false;
        $user->save();

        return ResponseData::success($user, 'User has been reactivated.');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return ResponseData::error([], 'Admin not authenticated', 401);
        }

        if (!Hash::check($request->current_password, $admin->password)) {
            return ResponseData::error([], 'Current password is incorrect', 400);
        }

        if (Hash::check($request->new_password, $admin->password)) {
            return ResponseData::error([], 'New password must be different from the current password', 400);
        }

        $admin->password = Hash::make($request->new_password);
        $admin->save();

        return ResponseData::success([], 'Password changed successfully', 200);
    }
}
