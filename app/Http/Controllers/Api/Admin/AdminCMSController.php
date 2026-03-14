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

class AdminCMSController extends Controller
{
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

    //Updated Hero section

    public function updateHeroSection(Request $request)
    {
        try {

            $request->validate([
                'title' => 'required|string|max:255',
                'subtitle' => 'nullable|string|max:255',
                'button_text' => 'nullable|string|max:100',
                'button_link' => 'nullable|string|max:255',
                'background_image' => 'nullable|string'
            ]);

            $hero = HomepageHeroSection::first();

            if (!$hero) {

                $hero = HomepageHeroSection::create([
                    'title' => $request->title,
                    'subtitle' => $request->subtitle,
                    'button_text' => $request->button_text,
                    'button_link' => $request->button_link,
                    'background_image' => $request->background_image
                ]);
            } else {

                $hero->update([
                    'title' => $request->title,
                    'subtitle' => $request->subtitle,
                    'button_text' => $request->button_text,
                    'button_link' => $request->button_link,
                    'background_image' => $request->background_image
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
                'photo' => 'nullable|string',
                'display_order' => 'nullable|integer'
            ]);

            $testimonial = HomepageTestimonial::create([
                'name' => $request->name,
                'designation' => $request->designation,
                'company' => $request->company,
                'message' => $request->message,
                'photo' => $request->photo,
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

            $cta = HomepageCtaSection::first();

            return response()->json([
                'status' => true,
                'data' => $cta
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch CTA section',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateCTASection(Request $request)
    {
        try {

            $request->validate([
                'title' => 'required|string|max:255',
                'subtitle' => 'nullable|string|max:255',
                'button_text' => 'nullable|string|max:100',
                'button_link' => 'nullable|string|max:255',
                'background_image' => 'nullable|string'
            ]);

            $cta = HomepageCtaSection::first();

            if (!$cta) {

                $cta = HomepageCtaSection::create([
                    'title' => $request->title,
                    'subtitle' => $request->subtitle,
                    'button_text' => $request->button_text,
                    'button_link' => $request->button_link,
                    'background_image' => $request->background_image
                ]);
            } else {

                $cta->update([
                    'title' => $request->title,
                    'subtitle' => $request->subtitle,
                    'button_text' => $request->button_text,
                    'button_link' => $request->button_link,
                    'background_image' => $request->background_image
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'CTA section updated successfully',
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

    // =================================================================
    // Navigation Links
    public function getNavigationLinks()
    {
        try {

            $links = NavigationLink::orderBy('display_order')->get();

            return response()->json([
                'status' => true,
                'total' => $links->count(),
                'data' => $links
            ], 200);
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
                'url' => 'required|string|max:255',
                'display_order' => 'nullable|integer'
            ]);

            $link = NavigationLink::create([
                'title' => $request->title,
                'url' => $request->url,
                'display_order' => $request->display_order ?? 0,
                'is_active' => true
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

            $link = NavigationLink::find($id);

            if (!$link) {
                return response()->json([
                    'status' => false,
                    'message' => 'Navigation link not found'
                ], 404);
            }

            $link->update([
                'title' => $request->title ?? $link->title,
                'url' => $request->url ?? $link->url,
                'display_order' => $request->display_order ?? $link->display_order
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Navigation link updated successfully',
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

    public function deleteNavigationLink($id)
    {
        try {

            $link = NavigationLink::find($id);

            if (!$link) {
                return response()->json([
                    'status' => false,
                    'message' => 'Navigation link not found'
                ], 404);
            }

            $link->delete();

            return response()->json([
                'status' => true,
                'message' => 'Navigation link deleted successfully'
            ], 200);
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

            $link = NavigationLink::find($id);

            if (!$link) {
                return response()->json([
                    'status' => false,
                    'message' => 'Navigation link not found'
                ], 404);
            }

            $link->update([
                'is_active' => !$link->is_active
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Navigation status updated',
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
    // Footer Sections and Links

    public function getFooterSections()
    {
        try {

            $sections = FooterSection::orderBy('display_order')->get();

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
}
