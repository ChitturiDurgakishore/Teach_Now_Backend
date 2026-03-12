<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobSeeker;
use Illuminate\Support\Facades\Auth;

class JobSeekerController extends Controller
{
    // Profile creation
    public function createProfile(Request $request)
    {
        try {

            $request->validate([
                'title' => 'nullable|string|max:150',
                'phone' => 'nullable|string|max:20',
                'location' => 'nullable|string|max:200',
                'experience_years' => 'nullable|integer',
                'availability' => 'nullable|in:open,not_looking',
                'dob' => 'nullable|date',
                'portfolio_website' => 'nullable|string',
                'bio' => 'nullable|string',
                'profile_photo' => 'nullable|string'
            ]);

            $user = Auth::user();

            $existingProfile = JobSeeker::where('user_id', $user->id)->first();

            if ($existingProfile) {
                return response()->json([
                    'status' => false,
                    'message' => 'Profile already exists'
                ], 409);
            }

            $profile = JobSeeker::create([
                'user_id' => $user->id,
                'title' => $request->title,
                'phone' => $request->phone,
                'location' => $request->location,
                'experience_years' => $request->experience_years ?? 0,
                'availability' => $request->availability ?? 'open',
                'dob' => $request->dob,
                'portfolio_website' => $request->portfolio_website,
                'bio' => $request->bio,
                'profile_photo' => $request->profile_photo
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Profile created successfully',
                'data' => $profile
            ], 201);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Profile creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get profile
    public function getProfile()
    {
        try {

            $user = Auth::user();

            $profile = JobSeeker::where('user_id', $user->id)->first();

            if (!$profile) {
                return response()->json([
                    'status' => false,
                    'message' => 'Profile not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $profile
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Update profile
    public function updateProfile(Request $request)
    {
        try {

            $user = Auth::user();

            $profile = JobSeeker::where('user_id', $user->id)->first();

            if (!$profile) {
                return response()->json([
                    'status' => false,
                    'message' => 'Profile not found'
                ], 404);
            }

            $request->validate([
                'title' => 'nullable|string|max:150',
                'phone' => 'nullable|string|max:20',
                'location' => 'nullable|string|max:200',
                'experience_years' => 'nullable|integer',
                'availability' => 'nullable|in:open,not_looking',
                'dob' => 'nullable|date',
                'portfolio_website' => 'nullable|string',
                'bio' => 'nullable|string',
                'profile_photo' => 'nullable|string'
            ]);

            $profile->update($request->only([
                'title',
                'phone',
                'location',
                'experience_years',
                'availability',
                'dob',
                'portfolio_website',
                'bio',
                'profile_photo'
            ]));

            return response()->json([
                'status' => true,
                'message' => 'Profile updated successfully',
                'data' => $profile
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Profile update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Delete profile
    public function deleteProfile()
    {
        try {

            $user = Auth::user();

            $profile = JobSeeker::where('user_id', $user->id)->first();

            if (!$profile) {
                return response()->json([
                    'status' => false,
                    'message' => 'Profile not found'
                ], 404);
            }

            $profile->delete();

            return response()->json([
                'status' => true,
                'message' => 'Profile deleted successfully'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
