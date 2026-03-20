<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Location;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;



class LocationController extends Controller
{

    //Helper function for Media Uploads


    public function uploadFile($file, $folder)
    {
        $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

        $path = $file->storeAs("public/media/$folder", $filename);

        return str_replace('public/', 'storage/', $path);
    }


    // Get all locations (Public)
    public function index()
    {
        $locations = Location::where('is_featured', true)->get();

        return response()->json([
            'status' => 200,
            'data' => $locations
        ]);
    }

    // Get all locations (Admin)
    public function all()
    {
        $locations = Location::all();

        return response()->json([
            'status' => 200,
            'data' => $locations
        ]);
    }

    // Create location (Admin)


    public function store(Request $request)
    {
        try {

            $validatedData = $request->validate([
                'name' => 'required|string|max:150',
                'country' => 'nullable|string|max:150',
                'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string',
                'meta_keywords' => 'nullable|string'
            ]);

            // 🔥 Handle image upload (FIXED)
            $imagePath = null;

            if ($request->hasFile('image')) {

                $file = $request->file('image');

                $path = Storage::disk('public')->putFile('media/locations', $file);

                $imagePath = 'storage/' . $path;
            }

            $location = Location::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'country' => $request->country,
                'image' => $imagePath,
                'meta_title' => $request->meta_title,
                'meta_description' => $request->meta_description,
                'meta_keywords' => $request->meta_keywords,
                'is_visible' => $request->is_visible ?? true,
                'is_featured' => $request->is_featured ?? false
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Location created successfully',
                'data' => $location
            ], 201);
        } catch (\Exception $e) {

            Log::error("Location Store Error: " . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while creating the location.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server Error'
            ], 500);
        }
    }


    // Update location

    public function update(Request $request, $id)
    {
        try {

            $location = Location::findOrFail($id);

            $request->validate([
                'name' => 'required|string|max:150',
                'country' => 'nullable|string|max:150',
                'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string',
                'meta_keywords' => 'nullable|string'
            ]);

            $imagePath = $location->image; // keep old by default

            // 🔥 Handle image update (FIXED)
            if ($request->hasFile('image')) {

                // delete old image
                if ($location->image) {
                    $oldPath = str_replace('storage/', 'public/', $location->image);

                    if (Storage::exists($oldPath)) {
                        Storage::delete($oldPath);
                    }
                }

                // upload new image (FORCED PUBLIC DISK ✅)
                $file = $request->file('image');

                $path = Storage::disk('public')->putFile('media/locations', $file);

                $imagePath = 'storage/' . $path;
            }

            // update
            $location->update([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'country' => $request->country ?? $location->country,
                'image' => $imagePath,
                'meta_title' => $request->meta_title,
                'meta_description' => $request->meta_description,
                'meta_keywords' => $request->meta_keywords,
                'is_visible' => $request->is_visible ?? $location->is_visible,
                'is_featured' => $request->is_featured ?? $location->is_featured
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Location updated successfully',
                'data' => $location
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'status' => false,
                'message' => 'Location not found.'
            ], 404);
        } catch (\Exception $e) {

            Log::error("Location Update Error [ID: $id]: " . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while updating the location.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server Error'
            ], 500);
        }
    }

    // Delete location
    public function destroy($id)
    {
        try {
            // 1. Find record
            $location = Location::findOrFail($id);

            // 🔥 Delete image from storage
            if ($location->image) {
                $path = str_replace('storage/', 'public/', $location->image);

                if (Storage::exists($path)) {
                    Storage::delete($path);
                }
            }

            // 🔥 Soft delete (recommended)
            $location->delete();

            return response()->json([
                'status' => true,
                'message' => 'Location deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'status' => false,
                'message' => 'Location not found or already deleted.'
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {

            Log::error("Database Error on Delete [ID: $id]: " . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Cannot delete this location because it is being used by other records.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 409);
        } catch (\Exception $e) {

            Log::error("General Error on Delete [ID: $id]: " . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while trying to delete the location.'
            ], 500);
        }
    }
}
