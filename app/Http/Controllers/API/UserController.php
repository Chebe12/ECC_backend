<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Services\OtpService;
use App\Helpers\ResponseData;
use Illuminate\Support\Facades\Storage;


class UserController extends Controller
{


    public function me()
    {
        return ResponseData::success(auth()->user(), 'User details retrieved successfully.', 200);
    }

    public function show($id)
    {
        $user = User::findOrFail($id);
        return ResponseData::success($user, 'User details retrieved successfully.', 200);
    }


    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

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

        // Handle profile picture upload
        if ($request->hasFile('profile_picture')) {
            $profilePath = $request->file('profile_picture')->store('profile_pictures', 'public');
            $validated['profile_picture'] = $profilePath;
        }

        // Handle cover image upload
        if ($request->hasFile('cover_image')) {
            $coverPath = $request->file('cover_image')->store('cover_images', 'public');
            $validated['cover_image'] = $coverPath;
        }

        $user->update($validated);

        return ResponseData::success($user, 'User updated successfully.', 200);
    }


    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password'      => 'required|string',
            'new_password'          => 'required|string|min:6|confirmed',
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

    public function delete($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return ResponseData::success([], 'User deleted successfully.', 200);
    }

    public function getAdmins()
    {
        $admins = User::where('role', 'admin')->where('is_deleted', false)->get();
        return ResponseData::success($admins, 'Admin users retrieved successfully');
    }

    public function getCustomers()
    {
        $customers = User::where('role', 'customer')->where('is_deleted', false)->get();
        return ResponseData::success($customers, 'Customer users retrieved successfully');
    }

    public function getUsers()
    {
        $users = User::where('role', 'user')->where('is_deleted', false)->get();
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
}
