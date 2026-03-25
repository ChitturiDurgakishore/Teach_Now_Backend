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
use App\Models\PopularTitle;
use App\Models\TeachingResource;

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

            $keyword = strtolower(trim($request->input('keyword')));
            $location = $request->input('location');
            $categoryId = $request->input('category_id');
            $jobType = $request->input('job_type');
            $experience = $request->input('experience');
            $salaryMin = $request->input('salary_min');
            $salaryMax = $request->input('salary_max');
            $gender = $request->input('gender');
            $experience_type = $request->input('experience_type');

            $query = Job::query()
                ->where('status', 'approved')
                ->where('job_status', 'open');

            /*
        |--------------------------------------------------------------------------
        | 🔥 KEYWORD SEARCH (SMART + FLEXIBLE)
        |--------------------------------------------------------------------------
        */
            if ($keyword) {

                $keywords = array_values(array_filter(explode(' ', $keyword)));

                $query->where(function ($q) use ($keyword, $keywords) {

                    // ✅ Exact phrase match (highest priority)
                    $q->where('title', 'LIKE', "%{$keyword}%")
                        ->orWhere('slug', 'LIKE', "%{$keyword}%");

                    // ✅ Multi-word strict + flexible match
                    if (count($keywords) > 1) {

                        $q->orWhere(function ($sub) use ($keywords) {

                            foreach ($keywords as $word) {

                                // 🔥 normalize word (teaching → teach)
                                $root = preg_replace('/(ing|ed|s)$/', '', $word);

                                $sub->where(function ($inner) use ($word, $root) {

                                    $inner->where('title', 'LIKE', "%{$word}%")
                                        ->orWhere('keywords', 'LIKE', "%{$word}%");

                                    // 🔥 root match (important)
                                    if ($root && $root !== $word) {
                                        $inner->orWhere('title', 'LIKE', "%{$root}%")
                                            ->orWhere('keywords', 'LIKE', "%{$root}%");
                                    }
                                });
                            }
                        });
                    } else {

                        // ✅ Single word
                        $word = $keywords[0] ?? null;

                        if ($word) {
                            $root = preg_replace('/(ing|ed|s)$/', '', $word);

                            $q->orWhere('title', 'LIKE', "%{$word}%")
                                ->orWhere('keywords', 'LIKE', "%{$word}%");

                            if ($root && $root !== $word) {
                                $q->orWhere('title', 'LIKE', "%{$root}%")
                                    ->orWhere('keywords', 'LIKE', "%{$root}%");
                            }
                        }
                    }
                });

                // 🔥 Relevance sorting
                $query->orderByRaw("
                CASE
                    WHEN title LIKE ? THEN 1
                    WHEN slug LIKE ? THEN 2
                    ELSE 3
                END
            ", [
                    "%{$keyword}%",
                    "%{$keyword}%"
                ]);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 FILTERS
        |--------------------------------------------------------------------------
        */

            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            if ($location) {
                $query->where('location', 'LIKE', "%{$location}%");
            }

            if ($jobType) {
                $query->where('job_type', $jobType);
            }

            if (!is_null($experience)) {
                $query->where('experience_required', '<=', $experience);
            }

            if ($experience_type) {
                $query->where('experience_type', $experience_type);
            }

            if ($salaryMin) {
                $query->where('salary_max', '>=', $salaryMin);
            }

            if ($salaryMax) {
                $query->where('salary_min', '<=', $salaryMax);
            }

            if ($gender) {
                $query->whereIn('gender', [$gender, 'both']);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 EXECUTE
        |--------------------------------------------------------------------------
        */

            $jobs = $query->latest()->paginate(10);

            /*
        |--------------------------------------------------------------------------
        | 🔥 SEARCH LOG
        |--------------------------------------------------------------------------
        */
            if ($keyword || $location) {
                SearchLog::create([
                    'keyword' => $keyword,
                    'location' => $location,
                    'ip_address' => $request->ip(),
                    'user_id' => auth()->id()
                ]);
            }

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

            $jobs = Job::where('category_id', $category->id)
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
            $cta = HomepageCtaSection::where('is_active', true)->get();

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
                ->select('id', 'company_name', 'company_logo', 'slug', 'city') // ✅ keep before
                ->withCount([
                    'jobs as jobs_count' => function ($query) {
                        $query->where('status', 'approved')
                            ->where('job_status', 'open');
                    }
                ]) // ✅ after select
                ->orderByDesc('jobs_count') // 🔥 optional (best UX)
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

    public function getCompanyPublicProfile($slug)
    {
        try {
            $companyDetails = Employer::where('slug', $slug)->first();
            // 1. Fetch the employer (company) details
            // We only fetch active/approved jobs for this public view
            $company = Employer::with(['jobs' => function ($query) {
                $query->where('job_status', 'open')
                    ->where('status', 'approved')
                    ->latest();
            }])->find($companyDetails->id);

            if (!$companyDetails) {
                return response()->json([
                    'status' => false,
                    'message' => 'Company not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Company profile and active jobs fetched',
                'data' => [
                    'company' => $companyDetails,
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

    // Location based job search API
    public function getJobsByLocation($slug)
    {
        try {

            // 🔥 Step 1: get location from slug
            $location = Location::where('slug', $slug)->first();

            if (!$location) {
                return response()->json([
                    'status' => false,
                    'message' => 'Location not found'
                ], 404);
            }

            // 🔥 Step 2: filter jobs (using location name)
            $jobs = Job::where('location', 'LIKE', '%' . $location->name . '%')
                ->where('status', 'approved')
                ->where('job_status', 'open')
                ->latest()
                ->get();

            return response()->json([
                'status' => true,
                'location' => $location->name,
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

    public function listResources()
    {
        try {

            $resources = TeachingResource::where('is_visible', true)->where('is_featured', true)->get();

            return response()->json([
                'status' => true,
                'data' => $resources
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch resources',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Filter

    public function getFilters()
    {
        try {

            $categories = Category::select('id', 'name', 'slug')
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $locations = Location::select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json([
                'status' => true,
                'data' => [
                    'categories' => $categories,
                    'locations' => $locations
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
}
