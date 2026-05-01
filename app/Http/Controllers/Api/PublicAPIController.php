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
use App\Models\Location;

use App\Models\TeachingResource;
use Illuminate\Support\Facades\Storage;
use App\Models\ResourceDownload;

use Illuminate\Support\Facades\Auth;

use App\Models\AboutUsSection;
use App\Models\PrivacyPolicySections;
use App\Models\Skill;
use App\Models\TermsConditionsSections;
use App\Models\PopularSearch;
use App\Models\ContactMessage;
use App\Models\EmailOtp;
use App\Models\EmployerUser;
use App\Models\User;
use App\Services\MailService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services;


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

            // 🔥 Job titles
            $jobTitles = Job::where('status', 'approved')
                ->where('job_status', 'open')
                ->pluck('title');

            // 🔥 Categories
            $categories = Category::pluck('name');

            // 🔥 Locations
            $locations = Location::pluck('name')
                ->unique()
                ->values();

            // 🔥 Merge titles + categories
            $suggestions = $jobTitles
                ->merge($categories)
                ->unique()
                ->values();

            return response()->json([
                'status' => true,
                'data' => [
                    'suggestions' => $suggestions,
                    'locations' => $locations
                ]
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

            $user = Auth::user();
            $keyword = strtolower(trim($request->input('keyword')));
            $location = $request->input('location');
            $perPage = $request->get('per_page', 10);

            $words = $keyword ? array_filter(explode(' ', $keyword)) : [];

            // 🔥 Log search
            SearchLog::create([
                'keyword' => $keyword,
                'location' => $location,
                'ip_address' => $request->ip(),
                'user_id' => $user ? $user->id : null
            ]);

            /*
        |--------------------------------------------------------------------------
        | 🔥 BASE QUERY
        |--------------------------------------------------------------------------
        */
            $baseQuery = Job::with(['employer:id,company_name,company_logo,institution_type'])
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->where('status', 'approved')
                ->where('job_status', 'open')
                ->where('application_deadline', '>', now());

            /*
        |--------------------------------------------------------------------------
        | 🔥 STRICT FILTERS
        |--------------------------------------------------------------------------
        */

            if ($request->filled('institution_type')) {
                $baseQuery->whereHas('employer', function ($q) use ($request) {
                    $q->where('institution_type', $request->institution_type);
                });
            }

            if ($request->filled('experience')) {
                $baseQuery->where('experience_required', $request->experience);
            }

            if ($request->filled('job_type')) {
                $types = is_array($request->job_type)
                    ? $request->job_type
                    : explode(',', $request->job_type);

                $baseQuery->whereIn('job_type', $types);
            }

            if ($request->filled('gender')) {
                $baseQuery->where('gender', $request->gender);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔍 SEARCH JOBS
        |--------------------------------------------------------------------------
        */
            $searchQuery = clone $baseQuery;

            if ($keyword) {
                $searchQuery->where(function ($q) use ($keyword, $words) {

                    $q->where('title', 'LIKE', "%{$keyword}%");

                    foreach ($words as $word) {
                        $q->orWhere('title', 'LIKE', "%{$word}%")
                            ->orWhere('keywords', 'LIKE', "%{$word}%")
                            ->orWhere('description', 'LIKE', "%{$word}%");
                    }
                });

                $searchQuery->orderByRaw("
                CASE
                    WHEN title LIKE ? THEN 1
                    WHEN keywords LIKE ? THEN 2
                    WHEN description LIKE ? THEN 3
                    ELSE 4
                END
            ", ["%{$keyword}%", "%{$keyword}%", "%{$keyword}%"]);

                foreach ($words as $word) {
                    $searchQuery->orderByRaw("title LIKE ? DESC", ["%{$word}%"]);
                    $searchQuery->orderByRaw("keywords LIKE ? DESC", ["%{$word}%"]);
                }
            }

            if ($location) {
                $searchQuery->where('location', 'LIKE', "%{$location}%");
            }

            $searchJobs = $searchQuery->paginate($perPage, ['*'], 'search_page');

            /*
        |--------------------------------------------------------------------------
        | 🔁 SIMILAR JOBS
        |--------------------------------------------------------------------------
        */
            $similarQuery = clone $baseQuery;

            if (!empty($words)) {
                $similarQuery->where(function ($q) use ($words) {
                    foreach ($words as $word) {
                        $q->orWhere('title', 'LIKE', "%{$word}%")
                            ->orWhere('keywords', 'LIKE', "%{$word}%")
                            ->orWhere('description', 'LIKE', "%{$word}%");
                    }
                });
            }

            if ($location) {
                $similarQuery->where('location', 'LIKE', "%{$location}%");
            }

            if ($searchJobs->total() > 0) {
                $similarQuery->whereNotIn('id', $searchJobs->pluck('id'));
            }

            $similarJobs = $similarQuery->paginate($perPage, ['*'], 'similar_page');

            /*
        |--------------------------------------------------------------------------
        | 🔥 FALLBACK LEVEL 1
        |--------------------------------------------------------------------------
        */
            if ($searchJobs->total() == 0) {

                $fallbackQuery = Job::with(['employer:id,company_name,company_logo,institution_type'])
                    ->where('is_active', true)
                    ->where('expires_at', '>', now())
                    ->where('status', 'approved')
                    ->where('job_status', 'open')
                    ->where('application_deadline', '>', now());

                if (!empty($words)) {
                    $fallbackQuery->where(function ($q) use ($words) {
                        foreach ($words as $word) {
                            $q->orWhere('title', 'LIKE', "%{$word}%")
                                ->orWhere('keywords', 'LIKE', "%{$word}%")
                                ->orWhere('description', 'LIKE', "%{$word}%");
                        }
                    });
                }

                if ($location) {
                    $fallbackQuery->where('location', 'LIKE', "%{$location}%");
                }

                $similarJobs = $fallbackQuery->paginate($perPage, ['*'], 'similar_page');

                /*
            |--------------------------------------------------------------------------
            | 🔥 FALLBACK LEVEL 2 (FILL WITH LATEST)
            |--------------------------------------------------------------------------
            */
                if ($similarJobs->total() < 5) {

                    $latestQuery = Job::with(['employer:id,company_name,company_logo,institution_type'])
                        ->where('is_active', true)
                        ->where('expires_at', '>', now())
                        ->where('status', 'approved')
                        ->where('job_status', 'open')
                        ->where('application_deadline', '>', now())
                        ->latest();

                    if ($location) {
                        $latestQuery->where('location', 'LIKE', "%{$location}%");
                    }

                    if ($similarJobs->total() > 0) {
                        $latestQuery->whereNotIn('id', $similarJobs->pluck('id'));
                    }

                    $extraJobs = $latestQuery->take(5)->get();

                    $merged = collect($similarJobs->items())->merge($extraJobs);

                    $similarJobs = new \Illuminate\Pagination\LengthAwarePaginator(
                        $merged,
                        $merged->count(),
                        $perPage,
                        1
                    );
                }
            }

            /*
        |--------------------------------------------------------------------------
        | 🔧 FORMAT
        |--------------------------------------------------------------------------
        */
            $formatJobs = function ($jobs) {
                return collect($jobs->items())->map(function ($job) {

                    $jobArray = $job->toArray();

                    $jobArray['employer'] = [
                        'id' => $job->employer->id ?? null,
                        'company_name' => $job->employer->company_name ?? null,
                        'company_logo' => $job->employer->company_logo ?? null,
                        'institution_type' => $job->employer->institution_type ?? null
                    ];

                    return $jobArray;
                });
            };

            /*
        |--------------------------------------------------------------------------
        | ❌ NO DATA
        |--------------------------------------------------------------------------
        */
            if ($searchJobs->total() == 0 && $similarJobs->total() == 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'No jobs found'
                ], 404);
            }

            /*
        |--------------------------------------------------------------------------
        | ✅ RESPONSE
        |--------------------------------------------------------------------------
        */
            return response()->json([
                'status' => true,
                'fallback' => $searchJobs->total() == 0,

                'search_jobs' => [
                    'total' => $searchJobs->total(),
                    'data' => $formatJobs($searchJobs)
                ],

                'similar_jobs' => [
                    'total' => $similarJobs->total(),
                    'data' => $formatJobs($similarJobs)
                ]

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
    public function getJobsByCategory($slug)
    {
        try {
            $category = Category::where('slug', $slug)->first();

            if (!$category) {
                return response()->json([
                    'status' => false,
                    'message' => 'Category not found'
                ], 404);
            }

            $mediaUrl = config('app.media_url'); // ✅ fetch from env

            $jobs = Job::with('employer:id,company_name,company_logo')
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->where('category_id', $category->id)
                ->where('status', 'approved')
                ->where('job_status', 'open')
                ->where('application_deadline', '>', now())
                ->latest()
                ->get();

            $jobs = $jobs->map(function ($job) use ($mediaUrl) {
                return [
                    'id' => $job->id,
                    'title' => $job->title,
                    'location' => $job->location,
                    'salary_min' => $job->salary_min,
                    'salary_max' => $job->salary_max,
                    'job_type' => $job->job_type,
                    'slug' => $job->slug,

                    'company_name' => $job->employer->company_name ?? null,
                    'company_logo' => $job->employer && $job->employer->company_logo
                        ? $mediaUrl . '/' . $job->employer->company_logo
                        : null,
                ];
            });

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

    public function AllCategoryindex()
    {
        // Retrieve only the visible categories
        $categories = Category::where('is_visible', true)
            ->orderBy('name', 'asc') // Alphabetical sorting is standard for simple lists
            ->get();

        return response()->json([
            'status' => true,
            'data' => $categories
        ], 200);
    }



    //HeroSection API
    public function getHeroSectionData()
    {
        try {

            // 🔹 Hero Section
            $hero = HomepageHeroSection::where('is_active', true)->first();

            // 🔹 CTA Section
            $cta = HomepageCtaSection::where('is_active', true)->get();
            $popular_searches = PopularSearch::where('is_featured', 1)
                ->orderBy('order')
                ->get();

            return response()->json([
                'status' => true,
                'data' => [
                    'hero' => $hero,
                    'cta' => $cta,
                    'popular_searches' => $popular_searches
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

            // 🔥 Top searches (keyword + location combination)
            $topSearchesRaw = SearchLog::select('keyword', 'location')
                ->whereNotNull('keyword')
                ->where('keyword', '!=', '')
                ->whereNotNull('location')
                ->where('location', '!=', '')
                ->groupBy('keyword', 'location')
                ->orderByRaw('COUNT(*) DESC')
                ->limit(6)
                ->get();

            // 🔥 Format output
            $topSearches = $topSearchesRaw->map(function ($item) {

                $title = $item->keyword . ' jobs in ' . $item->location;

                return [
                    'title' => $title,
                    'keyword' => $item->keyword,
                    'location' => $item->location,
                    'url' => 'open/search/jobs/search' . '?keyword=' . urlencode($item->keyword) . '&location=' . urlencode($item->location)
                ];
            });

            return response()->json([
                'status' => true,
                'data' => [
                    'sections' => $sections,
                    'top_searches' => $topSearches
                ]
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

            $companies = Employer::where('is_verified', 1)
                ->where('is_featured', 1)
                ->where('company_featured', 1)
                ->whereNotNull('featured_until') // 🔥 important
                ->where('featured_until', '>', now()) // 🔥 expiry check
                ->select('id', 'company_name', 'company_logo', 'slug', 'city')
                ->withCount([
                    'jobs as jobs_count' => function ($query) {
                        $query->where('status', 'approved')
                            ->where('job_status', 'open');
                    }
                ])
                ->orderByDesc('jobs_count')
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

            $jobs = Job::where('is_active', true)
                ->where('expires_at', '>', now())

                // 🔥 FEATURE CHECK
                ->where('featured', 1)
                ->whereNotNull('featured_until') // ✅ important
                ->where('featured_until', '>', now()) // ✅ expiry check

                ->where('admin_featured', 1) // (keep if required)
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
    public function getBlogBySlug($slug)
    {
        try {
            $blog_slug = Blog::where('slug', $slug)->first();
            $blog = Blog::where('id', $blog_slug->id)
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
            $similarBlogs = Blog::where('id', '!=', $blog_slug->id)
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

            $logo = HomepageCompanyLogo::where('is_active', true)->latest()
                ->first([
                    'company_name',
                    'company_logo',
                    'slug',
                    'company_url',
                    'meta_description',
                    'meta_keywords',
                    'meta_title'
                ]);;

            return response()->json([
                'status' => true,
                'data' => [
                    'menus' => $menus,
                    'companies' => $logo,
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

    public function getCompanyPublicProfile(Request $request, $slug)
    {
        try {

            $perPage = $request->get('per_page', 10);

            // ✅ Get company
            $company = Employer::where('slug', $slug)->first();

            if (!$company) {
                return response()->json([
                    'status' => false,
                    'message' => 'Company not found'
                ], 404);
            }

            // ✅ Jobs query (separate for pagination 🔥)
            $jobsQuery = Job::where('employer_id', $company->id)
                ->where('job_status', 'open')
                ->where('status', 'approved')
                ->where('is_active', true)
                ->where('expires_at', '>', now());

            $jobs = $jobsQuery->latest()->paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'Company profile and active jobs fetched',

                'data' => [
                    'company' => $company,

                    // 🔥 count (no need to load all jobs)
                    'total_active_jobs' => $jobs->total(),

                    // 🔥 pagination meta
                    'current_page' => $jobs->currentPage(),
                    'last_page' => $jobs->lastPage(),
                    'per_page' => $jobs->perPage(),

                    // 🔥 jobs
                    'jobs' => $jobs->items()
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

    // Location based job search API
    public function getJobsByLocation(Request $request, $slug)
    {
        try {

            $perPage = $request->get('per_page', 10);

            // ✅ Get location
            $location = Location::where('slug', $slug)->first();

            if (!$location) {
                return response()->json([
                    'status' => false,
                    'message' => 'Location not found'
                ], 404);
            }

            // ✅ Jobs query
            $jobs = Job::where('is_active', true)
                ->where('expires_at', '>', now())
                ->where('location', 'LIKE', '%' . $location->name . '%')
                ->where('status', 'approved')
                ->where('job_status', 'open')
                ->latest()
                ->paginate($perPage);

            return response()->json([
                'status' => true,
                'location' => $location->name,

                // 🔥 pagination meta
                'total' => $jobs->total(),
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'per_page' => $jobs->perPage(),

                // 🔥 data
                'data' => $jobs->items()

            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch jobs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Teaching resources

    public function viewResource($slug)
    {
        try {

            $resource = TeachingResource::where('slug', $slug)
                ->where('is_visible', true)
                ->first();

            if (!$resource) {
                return response()->json([
                    'status' => false,
                    'message' => 'Resource not found'
                ], 404);
            }

            // 🔥 Extract keywords from slug
            $keywords = explode('-', $resource->slug);

            // 🔥 Build query for similar resources
            $similarQuery = TeachingResource::where('is_visible', true)
                ->where('id', '!=', $resource->id);

            foreach ($keywords as $word) {
                $similarQuery->orWhere('slug', 'LIKE', "%{$word}%");
            }

            $similarResources = $similarQuery
                ->latest()
                ->take(6)
                ->get([
                    'id',
                    'title',
                    'slug',
                    'resource_photo',
                    'read_time'
                ]);

            return response()->json([
                'status' => true,
                'data' => [
                    'resource' => $resource,
                    'similar_resources' => $similarResources
                ]
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch resource',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function listResources(Request $request)
    {
        try {

            $perPage = $request->get('per_page', 10); // default 10

            $resources = TeachingResource::where('is_visible', true)
                ->where('is_featured', true)
                ->paginate($perPage);

            return response()->json([
                'status' => true,
                'data' => $resources->items(),
                'pagination' => [
                    'current_page' => $resources->currentPage(),
                    'last_page' => $resources->lastPage(),
                    'per_page' => $resources->perPage(),
                    'total' => $resources->total(),
                ]
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch resources',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //download resource API

    public function download(Request $request, $slug)
    {
        try {

            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 GET RESOURCE
        |--------------------------------------------------------------------------
        */

            $resource = TeachingResource::where('slug', $slug)
                ->where('is_visible', true)
                ->first();

            if (!$resource || !$resource->pdf) {
                return response()->json([
                    'status' => false,
                    'message' => 'Resource not found'
                ], 404);
            }

            $filePath = public_path($resource->pdf); // ✅ IMPORTANT FIX

            /*
        |--------------------------------------------------------------------------
        | 🔥 LOG DOWNLOAD
        |--------------------------------------------------------------------------
        */

            ResourceDownload::create([
                'user_id' => $user->id,
                'resource_type' => 'teaching_resource',
                'resource_id' => $resource->id,
                'file_name' => basename($resource->pdf),
                'file_path' => $resource->pdf,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            /*
        |--------------------------------------------------------------------------
        | 🔥 RETURN DOWNLOAD
        |--------------------------------------------------------------------------
        */

            return response()->download(
                $filePath,
                $resource->title . '.pdf'
            );
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Download failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Filter

    public function getFilters()
    {
        try {

            /*
        |--------------------------------------------------------------------------
        | 🔥 CATEGORY COUNTS (CORRECT WAY)
        |--------------------------------------------------------------------------
        */

            $categories = Category::where('is_visible', true)
                ->select('id', 'name', 'slug')
                ->get()
                ->map(function ($category) {

                    $category->jobs_count = Job::where('category_id', $category->id)
                        ->where('is_active', true)
                        ->where('expires_at', '>', now())
                        ->where('status', 'approved')
                        ->where('job_status', 'open')
                        ->count();

                    return $category;
                });

            /*
        |--------------------------------------------------------------------------
        | 🔥 LOCATION COUNTS (OPTIMIZED GROUPING)
        |--------------------------------------------------------------------------
        */

            // ✅ Get counts in ONE QUERY
            $jobLocationCounts = Job::selectRaw('location, COUNT(*) as total')
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->where('status', 'approved')
                ->where('job_status', 'open')
                ->groupBy('location')
                ->pluck('total', 'location'); // [location => count]

            $locations = Location::where('is_visible', true)
                ->select('id', 'name')
                ->get()
                ->map(function ($location) use ($jobLocationCounts) {

                    $count = 0;

                    foreach ($jobLocationCounts as $jobLocation => $total) {
                        if (stripos($jobLocation, $location->name) !== false) {
                            $count += $total;
                        }
                    }

                    $location->jobs_count = $count;

                    return $location;
                });

            /*
        |--------------------------------------------------------------------------
        | 🔥 RESPONSE
        |--------------------------------------------------------------------------
        */

            return response()->json([
                'status' => true,
                'data' => [
                    'categories' => $categories,
                    'locations' => $locations,
                    'categories_count' => $categories->count(),
                    'locations_count' => $locations->count()
                ]
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch filters',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    //Public pages APIs
    public function aboutUs()
    {
        $data = AboutUsSection::whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => function ($q) {
                $q->where('is_active', true)->orderBy('display_order');
            }])
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    //privacy policy
    public function privacyPolicy()
    {
        $data = PrivacyPolicySections::whereNull('parent_id')
            ->where('is_active', true)
            ->with('children')
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    //TnC
    public function termsConditions()
    {
        $data = TermsConditionsSections::whereNull('parent_id')
            ->where('is_active', true)
            ->with('children')
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function getskills()
    {
        $skills = Skill::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'status' => true,
            'total' => $skills->count(),
            'data' => $skills
        ], 200);
    }


    //Plans API public

    public function getActivePlans()
    {
        try {

            $plans = \App\Models\Plan::where('is_active', true)
                ->orderBy('offer_price', 'asc')
                ->get();

            return response()->json([
                'status' => true,
                'data' => $plans
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch plans',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //CV Templates API for public
    public function getActiveCVTemplates()
    {
        try {

            $templates = \App\Models\CVTemplate::where('is_active', true)
                ->latest()
                ->get();

            return response()->json([
                'status' => true,
                'data' => $templates
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch CV templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //OTP API for email verification

    public function sendOtp(Request $request)
    {
        try {

            $request->validate([
                'email' => 'required|email',
                'role' => 'nullable|in:job_seeker'
            ]);

            $email = $request->email;
            $role = $request->role ?? null;

            /*
        |--------------------------------------------------------------------------
        | 🔥 CHECK EXISTENCE BASED ON ROLE
        |--------------------------------------------------------------------------
        */

            if ($role === 'job_seeker') {

                // ✅ Check in users table
                $exists = User::where('email', $email)->exists();

                if ($exists) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Email already registered as job seeker'
                    ], 409);
                }
            } else {

                // ✅ Employer flow (no role passed)
                if (Employer::where('email', $email)->exists()) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Email already registered as employer'
                    ], 409);
                }

                if (EmployerUser::where('email', $email)->exists()) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Hi, you are already registered as a recruiter'
                    ], 409);
                }
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 GENERATE OTP
        |--------------------------------------------------------------------------
        */

            $otp = rand(100000, 999999);

            DB::table('email_otps')->updateOrInsert(
                ['email' => $email],
                [
                    'otp' => $otp,
                    'expires_at' => now()->addMinutes(10),
                    'is_verified' => false,
                    'updated_at' => now(),
                    'created_at' => now()
                ]
            );

            /*
        |--------------------------------------------------------------------------
        | 🔥 SEND MAIL
        |--------------------------------------------------------------------------
        */

            try {
                $mailService = new MailService();

                $mailService->send('email_verification_otp', [
                    'otp' => $otp
                ], $email);
            } catch (\Exception $mailEx) {
                Log::error("OTP mail failed: " . $mailEx->getMessage());
            }

            return response()->json([
                'status' => true,
                'message' => 'OTP sent successfully'
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to send OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //verifying OTP

    public function verifyOtp(Request $request)
    {
        try {

            $request->validate([
                'email' => 'required|email',
                'otp' => 'required'
            ]);

            $record = DB::table('email_otps')
                ->where('email', $request->email)
                ->first();

            if (!$record) {
                return response()->json([
                    'status' => false,
                    'message' => 'OTP not found'
                ], 404);
            }

            if ($record->otp != $request->otp) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid OTP'
                ], 400);
            }

            if (now()->gt($record->expires_at)) {
                return response()->json([
                    'status' => false,
                    'message' => 'OTP expired'
                ], 400);
            }

            // ✅ Mark verified
            DB::table('email_otps')
                ->where('email', $request->email)
                ->update([
                    'is_verified' => true
                ]);

            return response()->json([
                'status' => true,
                'message' => 'Email verified successfully'
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
