<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageHeroSection;
use App\Models\HomepageStat;
use App\Models\HomepageTestimonial;
use App\Models\HomepageCtaSection;
use App\Models\NavigationLink;
use App\Models\FooterSection;
use App\Models\FooterLink;
use Illuminate\Http\Request;
use App\Models\HomepageCompanyLogo;
use App\Models\FAQ;
use App\Models\Blog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Models\JobSeeker;
use App\Models\Skill;
use App\Models\JobCategory;
use App\Models\JobType;
use App\Models\Location;
use App\Models\EmailTemplate;

class AdminCMSController extends Controller
{


    //Helper function for Media Uploads


    public function uploadFile($file, $folder)
    {
        $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

        $path = $file->storeAs("media/$folder", $filename);

        return str_replace('public/', 'storage/', $path);
    }

    // Get Hero section
    public function getHeroSection()
    {
        try {

            $hero = HomepageHeroSection::first();

            return response()->json([
                'status' => true,
                'data' => $hero
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch hero section',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Update Hero section

    public function updateHeroSection(Request $request)
    {
        try {

            $request->validate([
                'title' => 'required|string|max:255',
                'subtitle' => 'nullable|string|max:255',
                'button_text' => 'nullable|string|max:100',
                'button_link' => 'nullable|string|max:255',
                'background_image' => 'nullable|image|mimes:jpg,jpeg,png|max:4096'
            ]);

            $hero = HomepageHeroSection::first();

            // 🔥 Handle image upload
            $imagePath = null;

            if ($request->hasFile('background_image')) {

                // delete old image if exists
                if ($hero && $hero->background_image) {
                    Storage::delete(str_replace('storage/', 'public/', $hero->background_image));
                }

                // upload new image
                $imagePath = $this->uploadFile(
                    $request->file('background_image'),
                    'banners'
                );
            }

            if (!$hero) {

                $hero = HomepageHeroSection::create([
                    'title' => $request->title,
                    'subtitle' => $request->subtitle,
                    'button_text' => $request->button_text,
                    'button_link' => $request->button_link,
                    'background_image' => $imagePath
                ]);
            } else {

                $hero->update([
                    'title' => $request->title,
                    'subtitle' => $request->subtitle,
                    'button_text' => $request->button_text,
                    'button_link' => $request->button_link,
                    'background_image' => $imagePath ?? $hero->background_image
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Hero section updated successfully',
                'data' => $hero
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Hero section update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // =================================================================

    // Stats Section

    public function getStats()
    {
        try {

            $stats = HomepageStat::first();

            return response()->json([
                'status' => true,
                'data' => $stats
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Update Stats Section

    public function updateStats(Request $request)
    {
        try {

            $request->validate([
                'total_jobs' => 'nullable|integer',
                'total_companies' => 'nullable|integer',
                'total_candidates' => 'nullable|integer',
                'total_recruiters' => 'nullable|integer'
            ]);

            $stats = HomepageStat::first();

            if (!$stats) {

                $stats = HomepageStat::create([
                    'total_jobs' => $request->total_jobs ?? 0,
                    'total_companies' => $request->total_companies ?? 0,
                    'total_candidates' => $request->total_candidates ?? 0,
                    'total_recruiters' => $request->total_recruiters ?? 0
                ]);
            } else {

                $stats->update([
                    'total_jobs' => $request->total_jobs ?? $stats->total_jobs,
                    'total_companies' => $request->total_companies ?? $stats->total_companies,
                    'total_candidates' => $request->total_candidates ?? $stats->total_candidates,
                    'total_recruiters' => $request->total_recruiters ?? $stats->total_recruiters
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Stats updated successfully',
                'data' => $stats
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Stats update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // =================================================================

    // Testimonial Section

    public function getTestimonials()
    {
        try {

            $testimonials = HomepageTestimonial::orderBy('display_order')->get();

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
                'is_active' => true
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


    public function updateTestimonial(Request $request, $id)
    {
        try {

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
                'display_order' => $request->display_order ?? $testimonial->display_order
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

    public function deleteTestimonial($id)
    {
        try {

            $testimonial = HomepageTestimonial::find($id);

            if (!$testimonial) {
                return response()->json([
                    'status' => false,
                    'message' => 'Testimonial not found'
                ], 404);
            }

            $testimonial->delete();

            return response()->json([
                'status' => true,
                'message' => 'Testimonial deleted successfully'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleTestimonial($id)
    {
        try {

            $testimonial = HomepageTestimonial::find($id);

            if (!$testimonial) {
                return response()->json([
                    'status' => false,
                    'message' => 'Testimonial not found'
                ], 404);
            }

            $testimonial->update([
                'is_active' => !$testimonial->is_active
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Testimonial status updated',
                'data' => $testimonial
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // =================================================================

    // CTA Section
    public function getCTASection()
    {
        try {

            $ctas = HomepageCtaSection::where('is_active', true)
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'total' => $ctas->count(),
                'data' => $ctas
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch CTA section',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storeCTASection(Request $request)
    {
        try {

            $request->validate([
                'title' => 'required|string|max:255',
                'subtitle' => 'nullable|string|max:255',
                'button_text' => 'nullable|string|max:100',
                'button_link' => 'nullable|string|max:255',
                'background_image' => 'nullable|string'
            ]);

            $cta = HomepageCtaSection::create([
                'title' => $request->title,
                'subtitle' => $request->subtitle,
                'button_text' => $request->button_text,
                'button_link' => $request->button_link,
                'background_image' => $request->background_image,
                'is_active' => true
            ]);

            return response()->json([
                'status' => true,
                'message' => 'CTA created successfully',
                'data' => $cta
            ], 201);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'CTA creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Update CTA Section
    public function updateCTASection(Request $request, $id)
    {
        try {

            $cta = HomepageCtaSection::findOrFail($id);

            $request->validate([
                'title' => 'required|string|max:255',
                'subtitle' => 'nullable|string|max:255',
                'button_text' => 'nullable|string|max:100',
                'button_link' => 'nullable|string|max:255',
                'background_image' => 'nullable|string'
            ]);

            $cta->update([
                'title' => $request->title,
                'subtitle' => $request->subtitle,
                'button_text' => $request->button_text,
                'button_link' => $request->button_link,
                'background_image' => $request->background_image
            ]);

            return response()->json([
                'status' => true,
                'message' => 'CTA updated successfully',
                'data' => $cta
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'CTA update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Delete CTA Section

    public function deleteCTA($id)
    {
        try {

            $cta = HomepageCtaSection::findOrFail($id);
            $cta->delete();

            return response()->json([
                'status' => true,
                'message' => 'CTA deleted successfully'
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Toggle CTA section

    public function toggleCTA($id)
    {
        $cta = HomepageCtaSection::findOrFail($id);

        $cta->is_active = !$cta->is_active;
        $cta->save();

        return response()->json([
            'status' => true,
            'message' => 'CTA status updated',
            'data' => $cta
        ]);
    }

    // =================================================================
    // Navigation Links
    public function getNavigationLinks()
    {
        try {

            $links = NavigationLink::whereNull('parent_id')
                ->with('childrenRecursive')
                ->orderBy('display_order')
                ->get();

            return response()->json([
                'status' => true,
                'data' => $links
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch navigation links',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function createNavigationLink(Request $request)
    {
        try {

            $request->validate([
                'title' => 'required|string|max:150',
                'url' => 'nullable|string|max:255',
                'parent_id' => 'nullable|exists:navigation_links,id',
                'display_order' => 'nullable|integer'
            ]);

            $link = NavigationLink::create([
                'title' => $request->title,
                'url' => $request->url,
                'parent_id' => $request->parent_id,
                'display_order' => $request->display_order ?? 0,
                'is_active' => true,
                'show_in_nav' => true,
                'slug' => Str::slug($request->title)
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Navigation link created successfully',
                'data' => $link
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateNavigationLink(Request $request, $id)
    {
        try {

            $link = NavigationLink::findOrFail($id);

            $link->update([
                'title' => $request->title ?? $link->title,
                'url' => $request->url ?? $link->url,
                'parent_id' => $request->parent_id ?? $link->parent_id,
                'display_order' => $request->display_order ?? $link->display_order
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Navigation link updated successfully',
                'data' => $link
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteNavigationLink($id)
    {
        try {

            $link = NavigationLink::findOrFail($id);

            // delete children
            NavigationLink::where('parent_id', $id)->delete();

            $link->delete();

            return response()->json([
                'status' => true,
                'message' => 'Navigation link deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleNavigationLink($id)
    {
        try {

            $link = NavigationLink::findOrFail($id);

            $link->update([
                'is_active' => !$link->is_active
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Navigation status updated',
                'data' => $link
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // =================================================================
    // Footer Sections and Links

    public function getFooterSections()
    {
        try {

            $sections = FooterSection::with(['links' => function ($query) {
                $query->orderBy('display_order');
            }])
                ->orderBy('display_order')
                ->get();

            return response()->json([
                'status' => true,
                'total' => $sections->count(),
                'data' => $sections
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch footer sections',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function createFooterSection(Request $request)
    {
        try {

            $request->validate([
                'title' => 'required|string|max:150',
                'display_order' => 'nullable|integer'
            ]);

            $section = FooterSection::create([
                'title' => $request->title,
                'display_order' => $request->display_order ?? 0,
                'is_active' => true
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Footer section created successfully',
                'data' => $section
            ], 201);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateFooterSection(Request $request, $id)
    {
        try {

            $section = FooterSection::find($id);

            if (!$section) {
                return response()->json([
                    'status' => false,
                    'message' => 'Footer section not found'
                ], 404);
            }

            $section->update([
                'title' => $request->title ?? $section->title,
                'display_order' => $request->display_order ?? $section->display_order
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Footer section updated successfully',
                'data' => $section
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteFooterSection($id)
    {
        try {

            $section = FooterSection::find($id);

            if (!$section) {
                return response()->json([
                    'status' => false,
                    'message' => 'Footer section not found'
                ], 404);
            }

            $section->delete();

            return response()->json([
                'status' => true,
                'message' => 'Footer section deleted successfully'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleFooterSection($id)
    {
        try {

            $section = FooterSection::find($id);

            if (!$section) {
                return response()->json([
                    'status' => false,
                    'message' => 'Footer section not found'
                ], 404);
            }

            $section->update([
                'is_active' => !$section->is_active
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Footer section status updated',
                'data' => $section
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // =================================================================

    // Footer Links

    public function getFooterLinks()
    {
        try {

            $links = FooterLink::with('section')
                ->orderBy('display_order')
                ->get();

            return response()->json([
                'status' => true,
                'total' => $links->count(),
                'data' => $links
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch footer links',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function createFooterLink(Request $request)
    {
        try {

            $request->validate([
                'section_id' => 'required|exists:footer_sections,id',
                'title' => 'required|string|max:150',
                'url' => 'required|string|max:255',
                'icon' => 'nullable|string',
                'display_order' => 'nullable|integer'
            ]);

            $link = FooterLink::create([
                'section_id' => $request->section_id,
                'title' => $request->title,
                'url' => $request->url,
                'icon' => $request->icon,
                'display_order' => $request->display_order ?? 0,
                'is_active' => true
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Footer link created successfully',
                'data' => $link
            ], 201);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function updateFooterLink(Request $request, $id)
    {
        try {

            $link = FooterLink::find($id);

            if (!$link) {
                return response()->json([
                    'status' => false,
                    'message' => 'Footer link not found'
                ], 404);
            }

            $link->update([
                'section_id' => $request->section_id ?? $link->section_id,
                'title' => $request->title ?? $link->title,
                'url' => $request->url ?? $link->url,
                'icon' => $request->icon ?? $link->icon,
                'display_order' => $request->display_order ?? $link->display_order
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Footer link updated successfully',
                'data' => $link
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteFooterLink($id)
    {
        try {

            $link = FooterLink::find($id);

            if (!$link) {
                return response()->json([
                    'status' => false,
                    'message' => 'Footer link not found'
                ], 404);
            }

            $link->delete();

            return response()->json([
                'status' => true,
                'message' => 'Footer link deleted successfully'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleFooterLink($id)
    {
        try {

            $link = FooterLink::find($id);

            if (!$link) {
                return response()->json([
                    'status' => false,
                    'message' => 'Footer link not found'
                ], 404);
            }

            $link->update([
                'is_active' => !$link->is_active
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Footer link status updated',
                'data' => $link
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // =================================================================
    // Company Logo and Title

    public function getCompanyLogos()
    {
        try {

            $logos = HomepageCompanyLogo::orderBy('display_order')->get();

            return response()->json([
                'status' => true,
                'total' => $logos->count(),
                'data' => $logos
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch company logos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===============================================================

    // Create company logo and title
    public function createCompanyLogo(Request $request)
    {
        try {

            $request->validate([
                'company_name' => 'required|string|max:255',
                'company_logo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
                'slug' => 'nullable|string|max:255',
                'company_url' => 'nullable|string|max:255',
                'display_order' => 'nullable|integer',
                'is_featured' => 'nullable|boolean',
                'is_verified' => 'nullable|boolean',
                'is_active' => 'nullable|boolean',
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string',
                'meta_keywords' => 'nullable|string'
            ]);

            // 🔥 Use common upload helper (FIXED)
            $logoPath = $this->uploadFile(
                $request->file('company_logo'),
                'company_logos'
            );

            $logo = HomepageCompanyLogo::create([
                'company_name' => $request->company_name,
                'company_logo' => $logoPath, // ✅ consistent path
                'slug' => $request->slug,
                'company_url' => $request->company_url,
                'display_order' => $request->display_order ?? 0,
                'is_featured' => $request->is_featured ?? false,
                'is_verified' => $request->is_verified ?? false,
                'is_active' => $request->is_active ?? true,
                'meta_title' => $request->meta_title,
                'meta_description' => $request->meta_description,
                'meta_keywords' => $request->meta_keywords
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Company logo created successfully',
                'data' => $logo
            ], 201);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // update company logo and title


    public function updateCompanyLogo(Request $request, $id = null)
    {
        try {

            $request->validate([
                'company_name' => 'required|string|max:255',
                'company_logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'slug' => 'nullable|string',
                'company_url' => 'nullable|string',
                'display_order' => 'nullable|integer',
                'is_featured' => 'nullable|boolean',
                'is_verified' => 'nullable|boolean',
                'is_active' => 'nullable|boolean',
                'meta_title' => 'nullable|string',
                'meta_description' => 'nullable|string',
                'meta_keywords' => 'nullable|string'
            ]);

            if ($id) {

                // 🔍 Find existing
                $logo = HomepageCompanyLogo::find($id);

                if (!$logo) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Company logo not found'
                    ], 404);
                }

                // 🔥 Handle logo update
                if ($request->hasFile('company_logo')) {

                    // ✅ Safe delete old file
                    if ($logo->company_logo) {
                        $oldPath = str_replace('storage/', 'public/', $logo->company_logo);

                        if (Storage::exists($oldPath)) {
                            Storage::delete($oldPath);
                        }
                    }

                    // ✅ Upload new file (standardized)
                    $logo->company_logo = $this->uploadFile(
                        $request->file('company_logo'),
                        'company_logos'
                    );
                }

                // 🔥 Update other fields
                $logo->update([
                    'company_name' => $request->company_name,
                    'slug' => $request->slug,
                    'company_url' => $request->company_url,
                    'display_order' => $request->display_order ?? 0,
                    'is_featured' => $request->is_featured ?? false,
                    'is_verified' => $request->is_verified ?? false,
                    'is_active' => $request->is_active ?? true,
                    'meta_title' => $request->meta_title,
                    'meta_description' => $request->meta_description,
                    'meta_keywords' => $request->meta_keywords
                ]);
            } else {

                // 🔥 Create new
                $logoPath = null;

                if ($request->hasFile('company_logo')) {
                    $logoPath = $this->uploadFile(
                        $request->file('company_logo'),
                        'company_logos'
                    );
                }

                $logo = HomepageCompanyLogo::create([
                    'company_name' => $request->company_name,
                    'company_logo' => $logoPath,
                    'slug' => $request->slug,
                    'company_url' => $request->company_url,
                    'display_order' => $request->display_order ?? 0,
                    'is_featured' => $request->is_featured ?? false,
                    'is_verified' => $request->is_verified ?? false,
                    'is_active' => $request->is_active ?? true,
                    'meta_title' => $request->meta_title,
                    'meta_description' => $request->meta_description,
                    'meta_keywords' => $request->meta_keywords
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Company logo saved successfully',
                'data' => $logo
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteCompanyLogo($id)
    {
        try {

            $logo = HomepageCompanyLogo::find($id);

            if (!$logo) {
                return response()->json([
                    'status' => false,
                    'message' => 'Logo not found'
                ], 404);
            }

            $logo->delete();

            return response()->json([
                'status' => true,
                'message' => 'Logo deleted successfully'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // =================================================================

    // FAQs
    //    Creating FAQs
    public function createFAQ(Request $request)
    {
        try {

            $request->validate([
                'question' => 'required|string|max:255',
                'answer' => 'required|string',
                'display_order' => 'nullable|integer'
            ]);

            $faq = FAQ::create([
                'question' => $request->question,
                'answer' => $request->answer,
                'display_order' => $request->display_order ?? 0,
                'is_active' => 1
            ]);

            return response()->json([
                'status' => true,
                'message' => 'FAQ created successfully',
                'data' => $faq
            ], 201);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Getting FAQs
    public function getFAQs()
    {
        $faqs = FAQ::orderBy('display_order')->get();

        return response()->json([
            'status' => true,
            'total' => $faqs->count(),
            'data' => $faqs
        ]);
    }

    // Updating FAQS
    public function updateFAQ(Request $request, $id)
    {
        $faq = FAQ::find($id);

        if (!$faq) {
            return response()->json([
                'status' => false,
                'message' => 'FAQ not found'
            ], 404);
        }

        $faq->update($request->all());

        return response()->json([
            'status' => true,
            'message' => 'FAQ updated',
            'data' => $faq
        ]);
    }

    // Deleting FAQs
    public function deleteFAQ($id)
    {
        $faq = FAQ::find($id);

        if (!$faq) {
            return response()->json([
                'status' => false,
                'message' => 'FAQ not found'
            ], 404);
        }

        $faq->delete();

        return response()->json([
            'status' => true,
            'message' => 'FAQ deleted'
        ]);
    }

    // =================================================================

    // Blogs management

    // Get Blogs

    public function getBlogs()
    {
        try {

            $blogs = Blog::latest()->paginate(10);

            return response()->json([
                'status' => true,
                'total' => $blogs->total(),
                'data' => $blogs
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch blogs',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // Create Blogs

    public function createBlog(Request $request)
    {
        try {

            $request->validate([
                'title' => 'required|string|max:255',
                'slug' => 'required|string|max:255|unique:blogs,slug',
                'content' => 'required|string',
                'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string',
                'meta_keywords' => 'nullable|string'
            ]);

            // Upload image if exists
            $imagePath = null;

            if ($request->hasFile('image')) {
                $imagePath = $this->uploadFile(
                    $request->file('image'),
                    'blogs'
                );
            }

            $blog = Blog::create([
                'title' => $request->title,
                'slug' => $request->slug,
                'content' => $request->content,
                'image' => $imagePath,
                'meta_title' => $request->meta_title,
                'meta_description' => $request->meta_description,
                'meta_keywords' => $request->meta_keywords,
                'is_active' => true
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Blog created successfully',
                'data' => $blog
            ], 201);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Blog creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Update Blogs

    public function updateBlog(Request $request, $id)
    {
        try {

            $blog = Blog::find($id);

            if (!$blog) {
                return response()->json([
                    'status' => false,
                    'message' => 'Blog not found'
                ], 404);
            }

            $request->validate([
                'title' => 'nullable|string|max:255',
                'slug' => 'nullable|string|max:255|unique:blogs,slug,' . $id,
                'content' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string',
                'meta_keywords' => 'nullable|string'
            ]);

            // 🔥 Handle image update
            if ($request->hasFile('image')) {

                // delete old image
                if ($blog->image) {
                    Storage::delete(str_replace('storage/', 'public/', $blog->image));
                }

                // upload new image
                $blog->image = $this->uploadFile(
                    $request->file('image'),
                    'blogs'
                );
            }

            // update other fields
            $blog->update([
                'title' => $request->title ?? $blog->title,
                'slug' => $request->slug ?? $blog->slug,
                'content' => $request->content ?? $blog->content,
                'meta_title' => $request->meta_title ?? $blog->meta_title,
                'meta_description' => $request->meta_description ?? $blog->meta_description,
                'meta_keywords' => $request->meta_keywords ?? $blog->meta_keywords
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Blog updated successfully',
                'data' => $blog
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Blog update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Deleting Blogs

    public function deleteBlog($id)
    {
        try {

            $blog = Blog::find($id);

            if (!$blog) {
                return response()->json([
                    'status' => false,
                    'message' => 'Blog not found'
                ], 404);
            }

            $blog->delete();

            return response()->json([
                'status' => true,
                'message' => 'Blog deleted successfully'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Toggle Blog Status

    public function toggleBlogStatus($id)
    {
        try {

            $blog = Blog::find($id);

            if (!$blog) {
                return response()->json([
                    'status' => false,
                    'message' => 'Blog not found'
                ], 404);
            }

            $blog->update([
                'is_featured' => !$blog->is_featured
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Blog status updated',
                'data' => $blog
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Navigation Bar Full Control FUnctions

    // Create navigation link with parent-child relationship
    public function store(Request $request)
    {
        try {

            $request->validate([
                'title' => 'required|string|max:255',
                'url' => 'nullable|string',
                'parent_id' => 'nullable|exists:navigation_links,id',
                'display_order' => 'nullable|integer',
                'show_in_nav' => 'nullable|boolean',
                'is_active' => 'nullable|boolean'
            ]);

            $link = NavigationLink::create([
                'title' => $request->title,
                'url' => $request->url,
                'parent_id' => $request->parent_id,
                'display_order' => $request->display_order ?? 0,
                'show_in_nav' => $request->show_in_nav ?? true,
                'is_active' => $request->is_active ?? true,
                'slug' => Str::slug($request->title)
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Navigation link created',
                'data' => $link
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get All Links
    public function index()
    {
        $links = NavigationLink::with('children')
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $links
        ]);
    }

    // Update navigation link
    public function update(Request $request, $id)
    {
        try {

            $link = NavigationLink::findOrFail($id);

            $request->validate([
                'title' => 'required|string|max:255',
                'url' => 'nullable|string',
                'parent_id' => 'nullable|exists:navigation_links,id',
                'display_order' => 'nullable|integer'
            ]);

            $link->update([
                'title' => $request->title,
                'url' => $request->url,
                'parent_id' => $request->parent_id,
                'display_order' => $request->display_order ?? $link->display_order,
                'slug' => Str::slug($request->title),
                'meta_title' => $request->meta_title ?? $link->meta_title,
                'meta_description' => $request->meta_description ?? $link->meta_description,
                'meta_keywords' => $request->meta_keywords ?? $link->meta_keywords

            ]);

            return response()->json([
                'status' => true,
                'message' => 'Updated successfully',
                'data' => $link
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Delete Link
    public function delete($id)
    {
        try {

            $link = NavigationLink::findOrFail($id);

            // delete children first
            NavigationLink::where('parent_id', $id)->delete();

            $link->delete();

            return response()->json([
                'status' => true,
                'message' => 'Deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Toggle Link Status

    public function toggleActive($id)
    {
        $link = NavigationLink::findOrFail($id);

        $link->is_active = !$link->is_active;
        $link->save();

        return response()->json([
            'status' => true,
            'message' => 'Status updated',
            'data' => $link
        ]);
    }

    // Toggle Show in Nav

    public function toggleShowInNav($id)
    {
        $link = NavigationLink::findOrFail($id);

        $link->show_in_nav = !$link->show_in_nav;
        $link->save();

        return response()->json([
            'status' => true,
            'message' => 'Navbar visibility updated',
            'data' => $link
        ]);
    }

    // Skills Section Management

    public function getSkills()
    {
        try {

            $skills = Skill::orderBy('name')->get();

            return response()->json([
                'status' => true,
                'total' => $skills->count(),
                'data' => $skills
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch skills',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Update skill

    public function createSkill(Request $request)
    {
        try {

            $request->validate([
                'name' => 'required|string|max:100|unique:skills,name'
            ]);

            $skill = Skill::create([
                'name' => strtolower(trim($request->name)),
                'is_custom' => false
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Skill created successfully',
                'data' => $skill
            ], 201);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Update Skills

    public function updateSkill(Request $request, $id)
    {
        try {

            $skill = Skill::find($id);

            if (!$skill) {
                return response()->json([
                    'status' => false,
                    'message' => 'Skill not found'
                ], 404);
            }

            $request->validate([
                'name' => 'required|string|max:100|unique:skills,name,' . $id
            ]);

            $skill->update([
                'name' => strtolower(trim($request->name))
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Skill updated successfully',
                'data' => $skill
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Delete skills

    public function deleteSkill($id)
    {
        try {

            $skill = Skill::find($id);

            if (!$skill) {
                return response()->json([
                    'status' => false,
                    'message' => 'Skill not found'
                ], 404);
            }

            $skill->delete();

            return response()->json([
                'status' => true,
                'message' => 'Skill deleted successfully'
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Toggle skill status

    public function toggleSkill($id)
    {
        try {

            $skill = Skill::find($id);

            if (!$skill) {
                return response()->json([
                    'status' => false,
                    'message' => 'Skill not found'
                ], 404);
            }

            $skill->update([
                'is_active' => !$skill->is_active
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Skill status updated',
                'data' => $skill
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // Email Template Management
    //    Get email templates
    public function getEmailTemplates()
    {
        try {

            $templates = EmailTemplate::orderBy('id', 'desc')->get();

            return response()->json([
                'status' => true,
                'total' => $templates->count(),
                'data' => $templates
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Create or update email template

    public function createEmailTemplate(Request $request)
    {
        try {

            $request->validate([
                'name' => 'required|string|max:150',
                'slug' => 'required|string|max:150|unique:email_templates,slug',
                'subject' => 'required|string|max:255',
                'body' => 'required|string'
            ]);

            $template = EmailTemplate::create([
                'name' => $request->name,
                'slug' => $request->slug,
                'subject' => $request->subject,
                'body' => $request->body,
                'is_active' => true
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Template created successfully',
                'data' => $template
            ], 201);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Update email template
    public function updateEmailTemplate(Request $request, $id)
    {
        try {

            $template = EmailTemplate::find($id);

            if (!$template) {
                return response()->json([
                    'status' => false,
                    'message' => 'Template not found'
                ], 404);
            }

            $request->validate([
                'name' => 'required|string|max:150',
                'subject' => 'required|string|max:255',
                'body' => 'required|string'
            ]);

            $template->update([
                'name' => $request->name,
                'subject' => $request->subject,
                'body' => $request->body
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Template updated successfully',
                'data' => $template
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Delete email template
    public function deleteEmailTemplate($id)
    {
        try {

            $template = EmailTemplate::find($id);

            if (!$template) {
                return response()->json([
                    'status' => false,
                    'message' => 'Template not found'
                ], 404);
            }

            $template->delete();

            return response()->json([
                'status' => true,
                'message' => 'Template deleted successfully'
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Toggle email template status

    public function toggleEmailTemplate($id)
    {
        try {

            $template = EmailTemplate::find($id);

            if (!$template) {
                return response()->json([
                    'status' => false,
                    'message' => 'Template not found'
                ], 404);
            }

            $template->update([
                'is_active' => !$template->is_active
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Template status updated',
                'data' => $template
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
