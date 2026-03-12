<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Location;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class LocationController extends Controller
{

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
            // 1. Validation (Laravel handles ValidationException automatically,
            // but it's good to keep inside or just before the try block)
            $validatedData = $request->validate([
                'name' => 'required|string|max:150',
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string',
                'meta_keywords' => 'nullable|string'
            ]);

            // 2. Database Operation
            $location = Location::create([
                'name' => $request->name,
                'slug' => \Illuminate\Support\Str::slug($request->name),
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
            // 3. Log the error for your own records (check storage/logs/laravel.log)
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
            // 1. Find the record
            // This will throw a ModelNotFoundException if not found
            $location = Location::findOrFail($id);

            // 2. Validation
            $request->validate([
                'name' => 'required|string|max:150',
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string',
                'meta_keywords' => 'nullable|string'
            ]);

            // 3. Perform the update
            $location->update([
                'name' => $request->name,
                'slug' => \Illuminate\Support\Str::slug($request->name),
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
            // Specifically catch when the ID doesn't exist
            return response()->json([
                'status' => false,
                'message' => 'Location not found.'
            ], 404);
        } catch (\Exception $e) {
            // Catch any other unexpected errors (DB connection, etc.)
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
            // 1. Find the record
            $location = Location::findOrFail($id);

            // 2. Attempt to delete
            $location->delete();

            return response()->json([
                'status' => true,
                'message' => 'Location deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Handle case where ID doesn't exist
            return response()->json([
                'status' => false,
                'message' => 'Location not found or already deleted.'
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database-specific errors (like foreign key constraints)
            Log::error("Database Error on Delete [ID: $id]: " . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Cannot delete this location because it is being used by other records.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 409); // 409 Conflict is appropriate here

        } catch (\Exception $e) {
            // Catch-all for anything else
            Log::error("General Error on Delete [ID: $id]: " . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while trying to delete the location.'
            ], 500);
        }
    }
}
