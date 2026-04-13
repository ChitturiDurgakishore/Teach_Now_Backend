<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\Category;
use App\Models\Location;
use App\Models\Employer;
use App\Models\HomepageSection;
use App\Models\NavigationLink;
use App\Models\FooterSection;
use App\Models\FooterLink;
use Illuminate\Http\Request;

class AdminSEOController extends Controller
{
    //Job SEO update
    public function updateJobSEO(Request $request, $id)
    {
        try {

            $request->validate([
                'slug' => 'nullable|string|max:255',
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string',
                'meta_keywords' => 'nullable|string'
            ]);

            $job = Job::find($id);

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            $job->update($request->only([
                'slug',
                'meta_title',
                'meta_description',
                'meta_keywords'
            ]));

            return response()->json([
                'status' => true,
                'message' => 'Job SEO updated successfully',
                'data' => $job
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Job SEO update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Category SEO update

    public function updateCategorySEO(Request $request, $id)
    {
        try {

            $category = Category::find($id);

            if (!$category) {
                return response()->json([
                    'status' => false,
                    'message' => 'Category not found'
                ], 404);
            }

            $category->update($request->only([
                'slug',
                'meta_title',
                'meta_description',
                'meta_keywords'
            ]));

            return response()->json([
                'status' => true,
                'message' => 'Category SEO updated',
                'data' => $category
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Category SEO update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Location SEO update

    public function updateLocationSEO(Request $request, $id)
    {
        try {

            $location = Location::find($id);

            if (!$location) {
                return response()->json([
                    'status' => false,
                    'message' => 'Location not found'
                ], 404);
            }

            $location->update($request->only([
                'slug',
                'meta_title',
                'meta_description',
                'meta_keywords'
            ]));

            return response()->json([
                'status' => true,
                'message' => 'Location SEO updated',
                'data' => $location
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Location SEO update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Employer SEO update

    public function updateEmployerSEO(Request $request, $id)
    {
        try {

            $employer = Employer::find($id);

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employer not found'
                ], 404);
            }

            $employer->update($request->only([
                'slug',
                'meta_title',
                'meta_description',
                'meta_keywords'
            ]));

            return response()->json([
                'status' => true,
                'message' => 'Employer SEO updated',
                'data' => $employer
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Employer SEO update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Homepage SEO update

    public function updateHomepageSectionSEO(Request $request, $id)
    {
        try {

            $section = HomepageSection::find($id);

            if (!$section) {
                return response()->json([
                    'status' => false,
                    'message' => 'Section not found'
                ], 404);
            }

            $section->update($request->only([
                'slug',
                'meta_title',
                'meta_description',
                'meta_keywords'
            ]));

            return response()->json([
                'status' => true,
                'message' => 'Homepage section SEO updated',
                'data' => $section
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'SEO update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Navigation link SEO update

    public function updateNavigationLinkSEO(Request $request, $id)
    {
        try {

            $link = NavigationLink::find($id);

            if (!$link) {
                return response()->json([
                    'status' => false,
                    'message' => 'Navigation link not found'
                ], 404);
            }

            $link->update($request->only([
                'slug',
                'meta_title',
                'meta_description',
                'meta_keywords'
            ]));

            return response()->json([
                'status' => true,
                'message' => 'Navigation SEO updated',
                'data' => $link
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'SEO update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Footer section SEO update

    public function updateFooterSectionSEO(Request $request, $id)
    {
        try {

            $section = FooterSection::find($id);

            if (!$section) {
                return response()->json([
                    'status' => false,
                    'message' => 'Footer section not found'
                ], 404);
            }

            $section->update($request->only([
                'slug',
                'meta_title',
                'meta_description',
                'meta_keywords'
            ]));

            return response()->json([
                'status' => true,
                'message' => 'Footer section SEO updated',
                'data' => $section
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'SEO update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Footer link SEO update

    public function updateFooterLinkSEO(Request $request, $id)
    {
        try {

            $link = FooterLink::find($id);

            if (!$link) {
                return response()->json([
                    'status' => false,
                    'message' => 'Footer link not found'
                ], 404);
            }

            $link->update($request->only([
                'slug',
                'meta_title',
                'meta_description',
                'meta_keywords'
            ]));

            return response()->json([
                'status' => true,
                'message' => 'Footer link SEO updated',
                'data' => $link
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'SEO update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
