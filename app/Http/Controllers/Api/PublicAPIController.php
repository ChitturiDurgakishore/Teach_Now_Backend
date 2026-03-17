<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HomepageHeroSection;
use App\Models\HomepageStat;
use App\Models\HomepageTestimonial;
use App\Models\HomepageCtaSection;
use App\Models\NavigationLink;
use App\Models\FooterSection;
use App\Models\Employer;
use App\Models\Job;
use App\Models\HomepageCompanyLogo;
use App\Models\FAQ;
use App\Models\Blog;
use App\Models\Category;
use App\Models\SearchLog;


class PublicAPIController extends Controller
{
    // Only public APIs for frontend
    // No admin logic
    // Read‑only APIs


    // Search Filter API
    //  Suggestions

    public function searchSuggestions(Request $request)
    {
        try {

            $keyword = $request->keyword;

            $jobTitles = Job::where('title', 'LIKE', "%{$keyword}%")
                ->pluck('title');

            $categories = Category::where('name', 'LIKE', "%{$keyword}%")
                ->pluck('name');

            $suggestions = $jobTitles
                ->merge($categories)
                ->unique()
                ->values();

            return response()->json([
                'status' => true,
                'data' => $suggestions
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch suggestions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Actual Job search API

    public function searchJobs(Request $request)
    {
        try {

            $keyword = $request->input('keyword');
            $location = $request->input('location');

            $jobs = Job::query()

                ->when($keyword, function ($query) use ($keyword) {
                    $query->where('title', 'LIKE', '%' . $keyword . '%');
                })

                ->when($location, function ($query) use ($location) {
                    $query->orderByRaw("
                    CASE
                        WHEN location LIKE ? THEN 1
                        ELSE 2
                    END
                ", ['%' . $location . '%']);
                })

                ->latest()
                ->paginate(10);
            SearchLog::create([
                'keyword' => $keyword,
                'location' => $location,
                'ip_address' => $request->header('X-Forwarded-For') ?? $request->ip(),
                'user_id' => auth()->id() //
            ]);

            if ($jobs->total() == 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'total_jobs' => $jobs->total(),
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'data' => $jobs->items()
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Job search failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    //    Hero API
    public function getHero()
    {
        try {

            $hero = HomepageHeroSection::where('is_active', true)->first();

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
    //   Stats API
    public function getStats()
    {
        try {

            $stats = HomepageStat::where('is_active', true)->first();

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

    // Testimonials API
    public function getTestimonials()
    {
        try {

            $testimonials = HomepageTestimonial::where('is_active', true)
                ->orderBy('display_order')
                ->get();

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


    // CTA API
    public function getCTA()
    {
        try {

            $cta = HomepageCtaSection::where('is_active', true)->first();

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

    // Navigation API
    public function getNavigation()
    {
        try {

            $links = NavigationLink::where('is_active', true)
                ->orderBy('display_order')
                ->get(['title', 'url']);

            return response()->json([
                'status' => true,
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

    // Footer API
    public function getFooter()
    {
        try {

            $sections = FooterSection::where('is_active', true)
                ->with(['links' => function ($query) {
                    $query->where('is_active', true)
                        ->orderBy('display_order')
                        ->select('id', 'section_id', 'title', 'url', 'icon');
                }])
                ->orderBy('display_order')
                ->get(['id', 'title']);

            return response()->json([
                'status' => true,
                'data' => $sections
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch footer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Employers API

    public function getFeaturedCompanies()
    {
        try {

            $companies = Employer::where('is_verified', 1)->where('is_featured', 1)
                ->select('id', 'company_name', 'company_logo', 'slug')
                ->get();

            return response()->json([
                'status' => true,
                'total' => $companies->count(),
                'data' => $companies
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch featured companies',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Featured Jobs API
    public function getFeaturedJobs()
    {
        try {

            $jobs = Job::where('featured', 1)->where('admin_featured', 1)
                ->where('status', 'approved')
                ->where('job_status', 'open')
                ->with(['employer:id,company_name,company_logo'])
                ->latest()
                ->get();

            return response()->json([
                'status' => true,
                'total' => $jobs->count(),
                'data' => $jobs
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch featured jobs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Company Title and Logo API

    public function getCompanyLogos()
    {
        try {

            $logos = HomepageCompanyLogo::where('is_active', true)
                ->orderBy('display_order')
                ->get([
                    'company_name',
                    'company_logo',
                    'slug',
                    'company_url',
                    'meta_description',
                    'meta_keywords',
                    'meta_title'
                ]);

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

    // Getting FAQs

    public function getFAQs()
    {
        try {

            $faqs = FAQ::where('is_active', true)
                ->orderBy('display_order')
                ->get(['question', 'answer']);

            return response()->json([
                'status' => true,
                'total' => $faqs->count(),
                'data' => $faqs
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch FAQs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Blogs
    public function getBlogs()
    {
        try {

            $blogs = Blog::where('is_active', true)
                ->latest()
                ->paginate(10);

            return response()->json([
                'status' => true,
                'total_blogs' => $blogs->total(),
                'current_page' => $blogs->currentPage(),
                'data' => $blogs->items()
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch blogs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getBlogBySlug($id)
    {
        try {

            $blog = Blog::where('id', $id)
                ->where('is_active', true)
                ->first();

            if (!$blog) {
                return response()->json([
                    'status' => false,
                    'message' => 'Blog not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $blog
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch blog',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getLatestBlogs()
    {
        try {

            $blogs = Blog::where('is_active', true)
                ->latest()
                ->limit(4)
                ->get();

            return response()->json([
                'status' => true,
                'data' => $blogs
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch latest blogs',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
