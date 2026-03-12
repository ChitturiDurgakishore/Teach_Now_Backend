<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class CategoryController extends Controller
{

    // Get all categories (PUBLIC)
    public function index()
    {
        $categories = Category::where('is_featured', true)->get();

        return response()->json([
            'status' => true,
            'data' => $categories
        ]);
    }


    // All categories (ADMIN)
    public function all()
    {
        $categories = Category::all();

        return response()->json([
            'status' => true,
            'data' => $categories
        ]);
    }
    // Create category (ADMIN)
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:150'
        ]);

        try {
            $category = Category::create([
                'name'             => $request->name,
                'slug'             => Str::slug($request->name),
                'icon'             => $request->icon,
                'is_visible'       => $request->is_visible,
                'meta_title'       => $request->meta_title,
                'meta_description' => $request->meta_description,
                'meta_keywords'    => $request->meta_keywords,
            ]);

            return response()->json([
                'status'  => 201,
                'message' => 'Category created successfully',
                'data'    => $category
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 400,
                'message' => 'Failed to create category',
                'error'   => $e->getMessage()
            ], 400);
        }
    }

    // Update category (ADMIN)
    public function update(Request $request, $id)
    {
        try {
            // 1. Find the category
            $category = Category::findOrFail($id);

            // 2. Validate input
            $request->validate([
                'name' => 'required|string|max:150',
                'is_visible' => 'boolean'
            ]);

            // 3. Perform the update
            $category->update([
                'name'             => $request->name,
                'slug'             => Str::slug($request->name),
                'icon'             => $request->icon,
                'is_visible'       => $request->is_visible,
                'meta_title'       => $request->meta_title,
                'meta_description' => $request->meta_description,
                'meta_keywords'    => $request->meta_keywords,
                'is_featured'     => $request->is_featured,
            ]);

            // 4. Return 200 OK
            return response()->json([
                'status'  => 200,
                'message' => 'Category updated successfully',
                'data'    => $category
            ], 200);
        } catch (ModelNotFoundException $e) {
            // Return 404 if the ID is wrong
            return response()->json([
                'status'  => 404,
                'message' => 'Category not found',
            ], 404);
        } catch (\Exception $e) {
            // Return 400 for any other validation or database errors
            return response()->json([
                'status'  => 400,
                'message' => 'Update failed',
                'error'   => $e->getMessage()
            ], 400);
        }
    }

    // Delete category(ADMIN)
    public function destroy($id)
    {
        try {
            $category = Category::findOrFail($id);

            $category->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Category deleted successfully'
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status'  => 404,
                'message' => 'Category not found. It may have already been deleted.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the category',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
