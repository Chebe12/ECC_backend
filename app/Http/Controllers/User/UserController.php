<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\ResponseData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{

    public function me()
    {
        $user = Auth::guard('user')->user();

        if (!$user) {
            return ResponseData::error([], 'User not authenticated', 401);
        }

        return ResponseData::success($user, 'User details retrieved successfully.', 200);
    }


    public function show($id)
    {
        $user = User::findOrFail($id);
        if (!$user) {
            return ResponseData::error([], 'User not found', 404);
        }
        if ($user->is_deleted) {
            return ResponseData::error([], 'User has been deleted', 410);
        }
        if ($user->is_banned) {
            return ResponseData::error([], 'User is banned', 403);
        }
        if (!$user->is_active) {
            return ResponseData::error([], 'User is inactive', 403);
        }
        if ($user->is_suspended) {
            return ResponseData::error([], 'User is suspended', 403);
        }
        return ResponseData::success($user, 'User details retrieved successfully.', 200);
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
