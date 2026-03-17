<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobSeeker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\HomepageTestimonial;

class JobSeekerController extends Controller
{


    //Helper function for Media Uploads


    public function uploadFile($file, $folder)
    {
        $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

        $path = $file->storeAs("public/media/$folder", $filename);

        return str_replace('public/', 'storage/', $path);
    }


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
                'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
            ]);

            $user = Auth::user();

            $existingProfile = JobSeeker::where('user_id', $user->id)->first();

            if ($existingProfile) {
                return response()->json([
                    'status' => false,
                    'message' => 'Profile already exists'
                ], 409);
            }

            // 🔥 Upload profile photo if exists
            $profilePhotoPath = null;

            if ($request->hasFile('profile_photo')) {
                $profilePhotoPath = $this->uploadFile(
                    $request->file('profile_photo'),
                    'profile_images'
                );
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
                'profile_photo' => $profilePhotoPath
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

            $profile = JobSeeker::with('user')->where('user_id', $user->id)->first();

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
                'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
            ]);

            // 🔥 Handle profile photo update
            if ($request->hasFile('profile_photo')) {

                // delete old image
                if ($profile->profile_photo) {
                    Storage::delete(str_replace('storage/', 'public/', $profile->profile_photo));
                }

                // upload new image
                $profile->profile_photo = $this->uploadFile(
                    $request->file('profile_photo'),
                    'profile_images'
                );
            }

            // update other fields
            $profile->update($request->only([
                'title',
                'phone',
                'location',
                'experience_years',
                'availability',
                'dob',
                'portfolio_website',
                'bio'
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

    // ==============================================================================================

    public function createTestimonial(Request $request)
    {
        try {

            $request->validate([
                'name' => 'required|string|max:150',
                'designation' => 'nullable|string|max:150',
                'company' => 'nullable|string|max:150',
                'message' => 'required|string',
                'display_order' => 'nullable|integer'
            ]);

            $user = Auth::user();

            // 🔥 Get JobSeeker profile (you can extend later for employer)
            $jobSeeker = JobSeeker::where('user_id', $user->id)->first();

            $photo = $jobSeeker && $jobSeeker->profile_photo
                ? $jobSeeker->profile_photo
                : null;

            $testimonial = HomepageTestimonial::create([
                'name' => $request->name,
                'designation' => $request->designation,
                'company' => $request->company,
                'message' => $request->message,
                'photo' => $photo, // ✅ auto-filled
                'display_order' => $request->display_order ?? 0,
                'is_active' => true,
                'user_id' => $user->id // Track who created it
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Testimonial created successfully',
                'data' => $testimonial
            ], 201);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getTestimonials()
    {
        try {
            $user = Auth::user();
            $testimonials = HomepageTestimonial::where('user_id', $user->id)->orderBy('display_order')->get();

            return response()->json([
                'status' => true,
                'total' => $testimonials->count(),
                'data' => $testimonials
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch testimonials',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateTestimonial(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $testimonial = HomepageTestimonial::find($id);

            if (!$testimonial) {
                return response()->json([
                    'status' => false,
                    'message' => 'Testimonial not found'
                ], 404);
            }

            $testimonial->update([
                'name' => $request->name ?? $testimonial->name,
                'designation' => $request->designation ?? $testimonial->designation,
                'company' => $request->company ?? $testimonial->company,
                'message' => $request->message ?? $testimonial->message,
                'photo' => $request->photo ?? $testimonial->photo,
                'display_order' => $request->display_order ?? $testimonial->display_order,
                'user_id' => $user->id
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Testimonial updated successfully',
                'data' => $testimonial
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
