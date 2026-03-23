<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;


class CategoryController extends Controller
{

    // Get all categories (PUBLIC)
    public function index()
    {
        $categories = Category::where('is_visible', true)
            ->whereHas('jobs', function ($query) {
                $query->where('status', 'approved')
                    ->where('job_status', 'open');
            })
            ->get();

        return response()->json([
            'status' => true,
            'data' => $categories
        ], 200);
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
            'name' => 'required|string|max:150',
            'icon' => 'nullable|image|mimes:jpg,jpeg,png,svg|max:2048',
            'is_visible' => 'nullable|boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string'
        ]);

        try {

            $iconPath = null;

            // 🔥 Handle icon upload
            if ($request->hasFile('icon')) {

                $file = $request->file('icon');

                // store in storage/app/public/category_icons
                $path = $file->store('media/categories', 'public');

                // save public path
                $iconPath = 'storage/' . $path;
            }

            $category = Category::create([
                'name'             => $request->name,
                'slug'             => Str::slug($request->name),
                'icon'             => $iconPath,
                'is_visible'       => $request->is_visible ?? true,
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

            // 🔹 Find category
            $category = Category::findOrFail($id);

            // 🔹 Validate
            $request->validate([
                'name' => 'required|string|max:150',
                'icon' => 'nullable|image|mimes:jpg,jpeg,png,svg|max:2048',
                'is_visible' => 'nullable|boolean',
                'is_featured' => 'nullable|boolean',
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string',
                'meta_keywords' => 'nullable|string'
            ]);

            $iconPath = $category->icon; // keep old by default

            // 🔥 If new icon uploaded
            if ($request->hasFile('icon')) {

                // 🔥 Delete old image if exists
                if ($category->icon) {
                    $oldPath = str_replace('storage/', 'public/', $category->icon);
                    if (Storage::exists($oldPath)) {
                        Storage::delete($oldPath);
                    }
                }

                // 🔥 Upload new image
                $file = $request->file('icon');
                $path = $file->store('media/categories', 'public');
                $iconPath = 'storage/' . $path;
            }

            // 🔹 Update category
            $category->update([
                'name'             => $request->name,
                'slug'             => Str::slug($request->name),
                'icon'             => $iconPath,
                'is_visible'       => $request->is_visible ?? $category->is_visible,
                'is_featured'      => $request->is_featured ?? $category->is_featured,
                'meta_title'       => $request->meta_title,
                'meta_description' => $request->meta_description,
                'meta_keywords'    => $request->meta_keywords,
            ]);

            return response()->json([
                'status'  => 200,
                'message' => 'Category updated successfully',
                'data'    => $category
            ], 200);
        } catch (ModelNotFoundException $e) {

            return response()->json([
                'status'  => 404,
                'message' => 'Category not found',
            ], 404);
        } catch (\Exception $e) {

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

            // 🔥 Delete icon file if exists
            if ($category->icon) {

                $path = str_replace('storage/', 'public/', $category->icon);

                if (Storage::exists($path)) {
                    Storage::delete($path);
                }
            }

            // 🔹 Delete category
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
