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

            $query = Job::query()
                ->where('status', 'approved')
                ->where('job_status', 'open');

            // 🔥 PRIORITY SEARCH
            if ($keyword) {

                $keywords = explode(' ', $keyword);

                $query->where(function ($q) use ($keywords) {

                    foreach ($keywords as $word) {
                        $q->orWhere('keywords', 'LIKE', "%$word%")     // 🥇 highest priority
                            ->orWhere('title', 'LIKE', "%$word%")        // 🥈 medium
                            ->orWhere('description', 'LIKE', "%$word%"); // 🥉 lowest
                    }
                });

                // 🔥 ORDER BY MATCH PRIORITY
                $query->orderByRaw("
                CASE
                    WHEN keywords LIKE ? THEN 1
                    WHEN title LIKE ? THEN 2
                    WHEN description LIKE ? THEN 3
                    ELSE 4
                END
            ", [
                    "%$keyword%",
                    "%$keyword%",
                    "%$keyword%"
                ]);
            }

            // 🔥 LOCATION PRIORITY
            if ($location) {
                $query->orderByRaw("
                CASE
                    WHEN location LIKE ? THEN 1
                    ELSE 2
                END
            ", ['%' . $location . '%']);
            }

            $jobs = $query->latest()->paginate(10);

            // 🔥 SIMILAR JOBS (based on same category or keyword)
            $similarJobs = [];

            if ($jobs->count() > 0) {

                $firstJob = $jobs->first();

                $similarJobs = Job::where('id', '!=', $firstJob->id)
                    ->where('status', 'approved')
                    ->where('job_status', 'open')
                    ->where(function ($q) use ($firstJob) {
                        $q->where('category_id', $firstJob->category_id)
                            ->orWhere('keywords', 'LIKE', "%{$firstJob->keywords}%");
                    })
                    ->limit(5)
                    ->get();
            }

            // 🔥 SEARCH LOG
            SearchLog::create([
                'keyword' => $keyword,
                'location' => $location,
                'ip_address' => $request->header('X-Forwarded-For') ?? $request->ip(),
                'user_id' => auth()->id() ?? null
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
                'data' => $jobs->items(),
                'similar_jobs' => $similarJobs // 🔥 NEW
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Job search failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Category search API
    public function getJobsByCategory($categoryId)
    {
        try {

            $jobs = Job::where('category_id', $categoryId)
                ->where('status', 'approved')
                ->where('job_status', 'open')
                ->latest()
                ->get();

            return response()->json([
                'status' => true,
                'data' => $jobs
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch jobs',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    //HeroSection API
    public function getHeroSectionData()
    {
        try {

            // 🔹 Hero Section
            $hero = HomepageHeroSection::where('is_active', true)->first();

            // 🔹 CTA Section
            $cta = HomepageCtaSection::where('is_active', true)->first();

            return response()->json([
                'status' => true,
                'data' => [
                    'hero' => $hero,
                    'cta' => $cta
                ]
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch homepage hero data',
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

            $companies = Employer::where('is_verified', 1)->where('is_featured', 1)->where('company_featured', 1)
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

            $blogs = Blog::where('is_active', true)->where('is_featured', true)
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

    // Blogs
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

            // 🔥 Extract keywords
            $keywords = [];

            if ($blog->meta_keywords) {
                $keywords = array_map('trim', explode(',', strtolower($blog->meta_keywords)));
            }

            // 🔥 Find similar blogs
            $similarBlogs = Blog::where('id', '!=', $id)
                ->where('is_active', true)
                ->when(!empty($keywords), function ($query) use ($keywords) {

                    foreach ($keywords as $keyword) {
                        $query->orWhere('meta_keywords', 'LIKE', '%' . $keyword . '%');
                    }
                })
                ->latest()
                ->take(5)
                ->get();

            //Latest blogs
            $LatestBlogs = Blog::where('is_active', true)
                ->latest()
                ->limit(4)
                ->get();

            return response()->json([
                'status' => true,
                'data' => [
                    'blog' => $blog,
                    'similar_blogs' => $similarBlogs,
                    'latest_blogs' => $LatestBlogs
                ]
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

    // Navigation bar including Company details and nav links
    public function getNavbarData()
    {
        try {

            $menus = NavigationLink::whereNull('parent_id')
                ->where('show_in_nav', true)
                ->where('is_active', true)
                ->orderBy('display_order')
                ->with('childrenRecursive') // 🔥 key change
                ->get();

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
                'data' => [
                    'menus' => $menus,
                    'companies' => [
                        'total' => $logos->count(),
                        'list' => $logos
                    ]
                ]
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch header data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Company details API

    public function getCompanyPublicProfile($id)
    {
        try {
            // 1. Fetch the employer (company) details
            // We only fetch active/approved jobs for this public view
            $company = Employer::with(['jobs' => function ($query) {
                $query->where('job_status', 'open')
                    ->where('status', 'approved')
                    ->latest();
            }])->find($id);

            if (!$company) {
                return response()->json([
                    'status' => false,
                    'message' => 'Company not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Company profile and active jobs fetched',
                'data' => [
                    'company' => $company,
                    'total_active_jobs' => $company->jobs->count(),
                    'jobs' => $company->jobs
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch company details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
