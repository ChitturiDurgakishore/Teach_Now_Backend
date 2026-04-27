<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employer;
use App\Models\EmployerUser;
use App\Models\Job;
use App\Models\JobSeeker;
use App\Models\JobApplication;
use App\Models\User;
use App\Models\DocumentVerification;
use App\Models\Order;
use App\Services\MailService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\Notification;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Payment;


class AdminController extends Controller
{


    //Notification service
    protected $notification;

    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
    }


    // Dashboard and analytics
    public function dashboard()
    {
        try {

            // Basic statistics
            $totalEmployers = Employer::count();

            $totalRecruiters = EmployerUser::count();

            $totalJobs = Job::count();

            $activeJobs = Job::where('job_status', 'open')->count();

            $jobsFilled = Job::where('job_status', 'filled')->count();

            $totalJobSeekers = JobSeeker::count();

            $totalApplications = JobApplication::count();

            $shortlistedCandidates = JobApplication::where('status', 'shortlisted')->count();

            // Recent Jobs
            $recentJobs = Job::with([
                'employer:id,company_name,company_logo',
            ])
                ->latest()
                ->limit(5)
                ->get();

            // Recent Applications
            $recentApplications = JobApplication::with([
                'job:id,title',

                'jobSeeker.user:id,name,email',
                'jobSeeker:id,user_id,profile_photo'
            ])
                ->latest()
                ->limit(5)
                ->get();

            return response()->json([
                'status' => true,
                'data' => [
                    'total_employers' => $totalEmployers,
                    'total_recruiters' => $totalRecruiters,
                    'total_jobs' => $totalJobs,
                    'active_jobs' => $activeJobs,
                    'jobs_filled' => $jobsFilled,
                    'total_job_seekers' => $totalJobSeekers,
                    'total_applications' => $totalApplications,
                    'shortlisted_candidates' => $shortlistedCandidates,
                    'recent_jobs' => $recentJobs,
                    'recent_applications' => $recentApplications
                ]
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Dashboard fetch failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Jobs management
    //Get All Jobs
    public function getAllJobs(Request $request)
    {
        try {

            /*
        |--------------------------------------------------------------------------
        | 🔥 STATS (MUTUALLY EXCLUSIVE)
        |--------------------------------------------------------------------------
        */

            $total_jobs = Job::withTrashed()->count();

            $deleted_jobs = Job::onlyTrashed()->count();

            // ✅ Approved + Active
            $active_jobs = Job::where('status', 'approved')
                ->where('job_status', 'open')
                ->where('expires_at', '>', now())
                ->count();

            // ✅ Approved + Expired
            $expired_jobs = Job::where('status', 'approved')
                ->where('expires_at', '<=', now())
                ->count();

            // ✅ Rejected
            $rejected_jobs = Job::where('status', 'rejected')->count();

            // ✅ Other (IMPORTANT FIX 🔥)
            $inactive_jobs = Job::where('status', 'approved')
                ->where('job_status', '!=', 'open')
                ->count();

            // ✅ Featured (subset of active — DO NOT mix in totals)
            $featured_jobs_count = Job::where('status', 'approved')
                ->where('job_status', 'open')
                ->where('admin_featured', 1)
                ->where('featured', 1)
                ->where('featured_until', '>', now())
                ->where('expires_at', '>', now())
                ->count();


            /*
        |--------------------------------------------------------------------------
        | 🔥 LISTING (SEARCH + PAGINATION)
        |--------------------------------------------------------------------------
        */

            $perPage = $request->get('per_page', 10);
            $search = $request->get('search');

            $query = Job::with([
                'employer:id,company_name,company_logo',
                'category:id,name'
            ]);

            if ($search) {
                $query->where(function ($q) use ($search) {

                    $q->where('title', 'like', "%$search%")
                        ->orWhere('location', 'like', "%$search%")
                        ->orWhere('job_type', 'like', "%$search%")
                        ->orWhereHas('employer', function ($q2) use ($search) {
                            $q2->where('company_name', 'like', "%$search%");
                        })
                        ->orWhereHas('category', function ($q3) use ($search) {
                            $q3->where('name', 'like', "%$search%");
                        });
                });
            }

            $jobs = $query->latest()->paginate($perPage);


            /*
        |--------------------------------------------------------------------------
        | ✅ RESPONSE
        |--------------------------------------------------------------------------
        */

            return response()->json([
                'status' => true,

                // 🔥 STATS
                'total_jobs' => $total_jobs,
                'deleted_jobs' => $deleted_jobs,
                'active_jobs' => $active_jobs,
                'expired_jobs' => $expired_jobs,
                'rejected_jobs' => $rejected_jobs,
                'inactive_jobs' => $inactive_jobs, // 🔥 NEW (fixes mismatch)
                'featured_jobs_count' => $featured_jobs_count,

                // 🔥 PAGINATION
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'per_page' => $jobs->perPage(),
                'next_page_url' => $jobs->nextPageUrl(),
                'prev_page_url' => $jobs->previousPageUrl(),

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

    //Get Single Job Details
    public function getJobDetails($id)
    {
        try {

            $job = Job::withTrashed()->with([
                'employer:id,company_name,company_logo',
                'category:id,name',
                'questions'
            ])
                ->find($id);

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $job
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch job',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Approve or Reject Job Posting
    public function approveJob($id)
    {
        try {

            $job = Job::withTrashed()->find($id);

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            $job->update([
                'status' => 'approved'
            ]);

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            // ✅ Employer (MAIN)
            $this->notification->send(
                'job_approved',
                'employer',
                $job->employer_id,
                'Job Approved',
                "Your job '{$job->title}' has been approved",
                [
                    'job_id' => $job->id
                ]
            );

            // ✅ Recruiter (if exists)
            if ($job->created_by) {
                $this->notification->send(
                    'job_approved',
                    'recruiter',
                    $job->created_by,
                    'Job Approved',
                    "Your job '{$job->title}' has been approved",
                    [
                        'job_id' => $job->id
                    ]
                );
            }

            $admins = \App\Models\User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                $this->notification->send(
                    'job_approved',
                    'admin',
                    $admin->id,
                    'Job Approved',
                    "Job '{$job->title}' approved successfully",
                    [
                        'job_id' => $job->id,
                        'employer_id' => $job->employer_id
                    ]
                );
            }

            return response()->json([
                'status' => true,
                'message' => 'Job approved successfully'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Reject a Job Posting
    public function rejectJob($id)
    {
        try {

            $job = Job::withTrashed()->find($id);

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            $job->update([
                'status' => 'rejected'
            ]);

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            // ❌ Employer (MAIN)
            $this->notification->send(
                'job_rejected',
                'employer',
                $job->employer_id,
                'Job Rejected',
                "Your job '{$job->title}' has been rejected",
                [
                    'job_id' => $job->id
                ]
            );

            // ❌ Recruiter (if exists)
            if ($job->created_by) {
                $this->notification->send(
                    'job_rejected',
                    'recruiter',
                    $job->created_by,
                    'Job Rejected',
                    "Your job '{$job->title}' has been rejected",
                    [
                        'job_id' => $job->id
                    ]
                );
            }

            // ⚙️ Admin (optional log)
            $admins = \App\Models\User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                $this->notification->send(
                    'job_rejected',
                    'admin',
                    $admin->id,
                    'Job Rejected',
                    "Job '{$job->title}' has been rejected",
                    [
                        'job_id' => $job->id,
                        'employer_id' => $job->employer_id
                    ]
                );
            }

            return response()->json([
                'status' => true,
                'message' => 'Job rejected successfully'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function featureJob($id)
    {
        try {

            $job = Job::withTrashed()->find($id);

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            // 🔥 TOGGLE BOOLEAN (0 ↔ 1)
            $isNowFeatured = $job->admin_featured ? 0 : 1;

            $job->update([
                'admin_featured' => $isNowFeatured
            ]);

            $statusText = $isNowFeatured ? 'featured' : 'unfeatured';
            $titleText = $isNowFeatured ? 'Job Featured' : 'Job Unfeatured';

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            // ✅ Employer
            $this->notification->send(
                'job_admin_featured',
                'employer',
                $job->employer_id,
                $titleText,
                "Your job '{$job->title}' has been {$statusText} by admin",
                [
                    'job_id' => $job->id,
                    'featured' => $isNowFeatured
                ]
            );

            // ✅ Recruiter (if exists)
            if ($job->created_by) {
                $this->notification->send(
                    'job_admin_featured',
                    'recruiter',
                    $job->created_by,
                    $titleText,
                    "Your job '{$job->title}' has been {$statusText} by admin",
                    [
                        'job_id' => $job->id,
                        'featured' => $isNowFeatured
                    ]
                );
            }

            // ✅ Admins (LOG)
            $admins = \App\Models\User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                $this->notification->send(
                    'job_admin_featured',
                    'admin',
                    $admin->id,
                    $titleText,
                    "Job '{$job->title}' has been {$statusText}",
                    [
                        'job_id' => $job->id,
                        'employer_id' => $job->employer_id,
                        'featured' => $isNowFeatured
                    ]
                );
            }

            return response()->json([
                'status' => true,
                'message' => "Job {$statusText} successfully",
                'data' => [
                    'job_id' => $job->id,
                    'admin_featured' => $isNowFeatured
                ]
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    //Delete a Job Posting
    public function deleteJob($id)
    {
        try {

            $job = Job::withTrashed()->find($id);

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            $job->delete();

            return response()->json([
                'status' => true,
                'message' => 'Job deleted successfully'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Deleted Jobs

    public function getExpiredJobsForAdmin()
    {
        $jobs = Job::where('expires_at', '<', now())
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'data' => $jobs
        ]);
    }

    //Republish

    public function adminRepublishJob($id)
    {
        try {

            $job = Job::find($id);

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            $job->update([
                'expires_at' => now()->addDays(30),
                'is_active' => true,
                'job_status' => 'open' // 🔥 recommended
            ]);

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            // 🔄 Employer (MAIN)
            $this->notification->send(
                'job_republished',
                'employer',
                $job->employer_id,
                'Job Republished',
                "Your job '{$job->title}' has been republished by admin",
                [
                    'job_id' => $job->id
                ]
            );

            // 🔄 Recruiter (if exists)
            if ($job->created_by) {
                $this->notification->send(
                    'job_republished',
                    'recruiter',
                    $job->created_by,
                    'Job Republished',
                    "Your job '{$job->title}' has been republished by admin",
                    [
                        'job_id' => $job->id
                    ]
                );
            }

            $admins = \App\Models\User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                $this->notification->send(
                    'job_republished',
                    'admin',
                    $admin->id,
                    'Job Republished',
                    "Job '{$job->title}' republished successfully",
                    [
                        'job_id' => $job->id,
                        'employer_id' => $job->employer_id
                    ]
                );
            }

            return response()->json([
                'status' => true,
                'message' => 'Job republished by admin successfully'
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Republish failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //======================================================================================

    //Employers management
    //Get All Employers
    public function getEmployers(Request $request)
    {
        try {

            $perPage = $request->get('per_page', 10);
            $search = $request->get('search');

            /*
        |--------------------------------------------------------------------------
        | 🔥 QUERY WITH SEARCH
        |--------------------------------------------------------------------------
        */

            $query = Employer::query();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('company_name', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%")
                        ->orWhere('phone', 'like', "%$search%")
                        ->orWhere('city', 'like', "%$search%");
                });
            }

            $employers = $query
                ->latest()
                ->paginate($perPage);

            return response()->json([
                'status' => true,
                'total_employers' => $employers->total(),
                'current_page' => $employers->currentPage(),
                'last_page' => $employers->lastPage(),
                'per_page' => $employers->perPage(),
                'next_page_url' => $employers->nextPageUrl(),
                'prev_page_url' => $employers->previousPageUrl(),
                'data' => $employers->items()
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch employers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Individual Employer Details
    public function getEmployerDetails($id)
    {
        try {

            $employer = Employer::withTrashed()->with([
                'employerUsers',
                'jobs',
                'documents' // 🔥 added this
            ])->find($id);

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employer not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $employer
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch employer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Verify Employer


    public function verifyEmployer(Request $request, $id)
    {
        try {

            $request->validate([
                'status' => 'required|in:approved,rejected',
                'admin_remark' => 'required_if:status,rejected|nullable|string'
            ]);

            $employer = Employer::withTrashed()->find($id);

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employer not found'
                ], 404);
            }

            // 🔥 Update employer
            $employer->update([
                'is_verified' => $request->status === 'approved'
            ]);
            $employerdocuments = DocumentVerification::where('employer_id', $id)->get();
            foreach ($employerdocuments as $document) {
                $document->update([
                    'status' => $request->status === 'approved' ? 'approved' : 'rejected',
                    'admin_remark' => $request->admin_remark ?? null
                ]);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            if ($request->status === 'approved') {

                // ✅ Employer approved
                $this->notification->send(
                    'employer_verified',
                    'employer',
                    $employer->id,
                    'Account Verified',
                    "Your company '{$employer->company_name}' has been verified successfully",
                    []
                );
            } else {

                // ❌ Employer rejected
                $this->notification->send(
                    'employer_rejected',
                    'employer',
                    $employer->id,
                    'Verification Failed',
                    "Your verification was rejected. Reason: " . ($request->admin_remark ?? 'Not specified'),
                    []
                );
            }

            $admins = \App\Models\User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                $this->notification->send(
                    'employer_verification',
                    'admin',
                    $admin->id,
                    'Employer Verification Updated',
                    "Employer '{$employer->company_name}' marked as '{$request->status}'",
                    [
                        'employer_id' => $employer->id
                    ]
                );
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 MAIL (UNCHANGED)
        |--------------------------------------------------------------------------
        */

            try {

                $mailService = new MailService();

                if ($request->status === 'approved') {

                    $mailService->send('employer_verified', [
                        'name' => $employer->company_name
                    ], $employer->email);
                } else {

                    $mailService->send('employer_rejected', [
                        'name' => $employer->company_name,
                        'remark' => $request->admin_remark ?? 'Not specified'
                    ], $employer->email);
                }
            } catch (\Exception $mailException) {

                Log::error('Employer verification mail failed', [
                    'employer_id' => $employer->id,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Employer verification updated successfully',
                'data' => [
                    'employer_verified' => $employer->is_verified
                ]
            ], 200);
        } catch (\Exception $e) {

            Log::error('Employer verification failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    //Feature Employer

    public function featureEmployer($id)
    {
        try {

            $employer = Employer::withTrashed()->find($id);

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employer not found'
                ], 404);
            }

            // 🔥 TOGGLE (0 ↔ 1)
            $isNowFeatured = $employer->is_featured ? 0 : 1;

            $employer->update([
                'is_featured' => $isNowFeatured
            ]);

            $statusText = $isNowFeatured ? 'featured' : 'unfeatured';
            $titleText = $isNowFeatured ? 'Company Featured' : 'Company Unfeatured';

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            // ✅ Employer
            $this->notification->send(
                'employer_featured',
                'employer',
                $employer->id,
                $titleText,
                "Your company '{$employer->company_name}' has been {$statusText} by admin",
                [
                    'featured' => $isNowFeatured
                ]
            );

            // ✅ Admins (loop)
            $admins = \App\Models\User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                $this->notification->send(
                    'employer_featured',
                    'admin',
                    $admin->id,
                    $titleText,
                    "Employer '{$employer->company_name}' {$statusText}",
                    [
                        'employer_id' => $employer->id,
                        'featured' => $isNowFeatured
                    ]
                );
            }

            return response()->json([
                'status' => true,
                'message' => "Employer {$statusText} successfully",
                'data' => [
                    'employer_id' => $employer->id,
                    'is_featured' => $isNowFeatured
                ]
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    //Update Employer Details
    public function updateEmployer(Request $request, $id)
    {
        try {

            $employer = Employer::withTrashed()->find($id);

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employer not found'
                ], 404);
            }

            $employer->update($request->all());

            return response()->json([
                'status' => true,
                'message' => 'Employer updated successfully',
                'data' => $employer
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Delete Employer
    public function deleteEmployer($id)
    {
        try {

            $employer = Employer::withTrashed()->find($id);

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employer not found'
                ], 404);
            }

            // 🔥 Store before delete
            $name = $employer->company_name;
            $email = $employer->email;

            $employer->delete();

            // 🔥 MAIL (QUEUE + SAFE)
            try {

                $mailService = new MailService();

                // ✅ Employer mail
                $mailService->send('employer_deleted', [
                    'name' => $name
                ], $email);

                Log::info('Employer deletion mail queued', [
                    'employer_email' => $email
                ]);
            } catch (\Exception $mailException) {

                Log::error('Employer deletion mail failed', [
                    'employer_email' => $email,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Employer deleted successfully'
            ], 200);
        } catch (\Exception $e) {

            Log::error('Employer delete failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //======================================================================================

    //Recruiters management for Admin

    //Get All Recruiters
    public function getRecruiters()
    {
        try {

            $recruiters = EmployerUser::with('employer:id,company_name,company_logo')
                ->latest()
                ->paginate(10);

            return response()->json([
                'status' => true,
                'total_recruiters' => $recruiters->total(),
                'data' => $recruiters
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch recruiters',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //get individual recruiter details
    public function getRecruiterDetails($id)
    {
        try {

            $recruiter = EmployerUser::withTrashed()->with([
                'employer:id,company_name,company_logo',
                'jobs'
            ])->find($id);

            if (!$recruiter) {
                return response()->json([
                    'status' => false,
                    'message' => 'Recruiter not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $recruiter
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch recruiter',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Disabling a Recruiter Account



    public function toggleRecruiterStatus($id)
    {
        try {

            $recruiter = EmployerUser::withTrashed()->find($id);

            if (!$recruiter) {
                return response()->json([
                    'status' => false,
                    'message' => 'Recruiter not found'
                ], 404);
            }

            // 🔥 TOGGLE (0 ↔ 1)
            $isNowActive = $recruiter->is_active ? 0 : 1;

            $recruiter->update([
                'is_active' => $isNowActive
            ]);

            $statusText = $isNowActive ? 'enabled' : 'disabled';
            $titleText = $isNowActive ? 'Account Enabled' : 'Account Disabled';

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            // ✅ Recruiter
            $this->notification->send(
                'recruiter_status',
                'recruiter',
                $recruiter->id,
                $titleText,
                "Your recruiter account has been {$statusText} by admin",
                [
                    'status' => $isNowActive
                ]
            );

            // ✅ Employer
            $this->notification->send(
                'recruiter_status',
                'employer',
                $recruiter->employer_id,
                $isNowActive ? 'Recruiter Enabled' : 'Recruiter Disabled',
                "Recruiter '{$recruiter->name}' has been {$statusText}",
                [
                    'recruiter_id' => $recruiter->id,
                    'status' => $isNowActive
                ]
            );

            // ✅ Admins (loop)
            $admins = \App\Models\User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                $this->notification->send(
                    'recruiter_status',
                    'admin',
                    $admin->id,
                    $isNowActive ? 'Recruiter Enabled' : 'Recruiter Disabled',
                    "Recruiter '{$recruiter->name}' {$statusText} successfully",
                    [
                        'recruiter_id' => $recruiter->id,
                        'employer_id' => $recruiter->employer_id,
                        'status' => $isNowActive
                    ]
                );
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 MAIL
        |--------------------------------------------------------------------------
        */

            try {

                $mailService = new MailService();

                if ($isNowActive) {
                    // ✅ ENABLE MAILS
                    $mailService->send('recruiter_enabled', [
                        'name' => $recruiter->name,
                        'company_name' => $recruiter->employer->company_name
                    ], $recruiter->email);
                } else {
                    // ✅ DISABLE MAILS
                    $mailService->send('recruiter_disabled', [
                        'name' => $recruiter->name,
                        'company_name' => $recruiter->employer->company_name
                    ], $recruiter->email);
                }
            } catch (\Exception $mailException) {

                Log::error('Recruiter status mail failed', [
                    'recruiter_id' => $recruiter->id,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => "Recruiter {$statusText} successfully",
                'data' => [
                    'recruiter_id' => $recruiter->id,
                    'is_active' => $isNowActive
                ]
            ], 200);
        } catch (\Exception $e) {

            Log::error('Toggle recruiter failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Delete a Recruiter Account


    public function deleteRecruiter($id)
    {
        try {

            $recruiter = EmployerUser::withTrashed()->with('employer')->find($id);

            if (!$recruiter) {
                return response()->json([
                    'status' => false,
                    'message' => 'Recruiter not found'
                ], 404);
            }

            // 🔥 Store before delete
            $name = $recruiter->name;
            $email = $recruiter->email;
            $companyName = $recruiter->employer->company_name;
            $employerEmail = $recruiter->employer->email;
            $employerId = $recruiter->employer_id;
            $recruiterId = $recruiter->id;

            $recruiter->delete();

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            // ❌ Recruiter (MAIN)
            $this->notification->send(
                'recruiter_deleted',
                'recruiter',
                $recruiterId,
                'Account Removed',
                "Your recruiter account has been removed by admin",
                []
            );

            // 🏢 Employer
            $this->notification->send(
                'recruiter_deleted',
                'employer',
                $employerId,
                'Recruiter Removed',
                "Recruiter '{$name}' has been removed from your company",
                [
                    'recruiter_id' => $recruiterId
                ]
            );

            // ⚙️ Admin (optional log)
            $admins = \App\Models\User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                $this->notification->send(
                    'recruiter_deleted',
                    'admin',
                    $admin->id,
                    'Recruiter Deleted',
                    "Recruiter '{$name}' deleted successfully",
                    [
                        'recruiter_id' => $recruiterId,
                        'employer_id' => $employerId
                    ]
                );
            }





            /*
        |--------------------------------------------------------------------------
        | 🔥 MAIL (UNCHANGED)
        |--------------------------------------------------------------------------
        */

            try {

                $mailService = new MailService();

                // ✅ Recruiter mail
                $mailService->send('recruiter_removed', [
                    'name' => $name,
                    'company_name' => $companyName
                ], $email);

                // ✅ Employer mail
                $mailService->send('recruiter_deleted_employer', [
                    'name' => $name,
                    'email' => $email,
                    'company_name' => $companyName
                ], $employerEmail);
            } catch (\Exception $mailException) {

                Log::error('Recruiter delete mail failed', [
                    'recruiter_email' => $email,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Recruiter deleted successfully'
            ], 200);
        } catch (\Exception $e) {

            Log::error('Delete recruiter failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //======================================================================================

    //Job Seekers management for Admin

    //Get All Job Seekers

    public function getJobSeekers(Request $request)
    {
        try {

            $total_jobseekers = JobSeeker::count();
            $active_jobseekers = JobSeeker::whereHas('user', function ($q) {
                $q->where('is_active', 1);
            })->count();
            $perPage = $request->get('per_page', 10);
            $search = $request->get('search');

            /*
        |--------------------------------------------------------------------------
        | 🔥 QUERY WITH SEARCH
        |--------------------------------------------------------------------------
        */

            $query = JobSeeker::with('user:id,name,email,is_active');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%$search%") // e.g. "English Teacher"
                        ->orWhere('location', 'like', "%$search%")
                        ->orWhereHas('user', function ($q2) use ($search) {
                            $q2->where('name', 'like', "%$search%")
                                ->orWhere('email', 'like', "%$search%");
                        });
                });
            }

            $jobSeekers = $query
                ->latest()
                ->paginate($perPage);

            return response()->json([
                'status' => true,
                'total_job_seekers' => $total_jobseekers,
                'active_job_seekers' => $active_jobseekers,
                'current_page' => $jobSeekers->currentPage(),
                'last_page' => $jobSeekers->lastPage(),
                'per_page' => $jobSeekers->perPage(),
                'next_page_url' => $jobSeekers->nextPageUrl(),
                'prev_page_url' => $jobSeekers->previousPageUrl(),
                'data' => $jobSeekers->items()
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch job seekers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get individual job seeker details
    public function getJobSeekerDetails($id)
    {
        try {

            $jobSeeker = JobSeeker::withTrashed()->with([
                'user:id,name,email,is_active',
                'resumes',
                'cvs', // ✅ ADD THIS RELATION (JobSeekerCV)

                // 🔥 UPDATED JOB + EMPLOYER
                'jobApplications.job' => function ($query) {
                    $query->select('id', 'title', 'employer_id')
                        ->with([
                            'employer:id,company_name,company_logo'
                        ]);
                }

            ])->find($id);

            if (!$jobSeeker) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job seeker not found'
                ], 404);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 NORMALIZE APPLICATIONS (IMPORTANT)
        |--------------------------------------------------------------------------
        */

            $jobSeeker->jobApplications->transform(function ($app) {

                $resumeData = null;

                // ✅ CV
                if ($app->resume_type === 'cv' && $app->cv_id) {

                    $cv = \App\Models\JobSeekerCV::find($app->cv_id);

                    if ($cv) {
                        $resumeData = [
                            'type' => 'cv',
                            'title' => $cv->title,
                            'url' => $cv->pdf_path ? asset($cv->pdf_path) : null
                        ];
                    }
                }

                // ✅ Resume
                elseif ($app->resume_type === 'resume' && $app->resume_id) {

                    $resume = \App\Models\Resume::find($app->resume_id);

                    if ($resume) {
                        $resumeData = [
                            'type' => 'resume',
                            'title' => $resume->file_name,
                            'url' => $resume->file_url
                        ];
                    }
                }

                // attach clean field
                $app->resume_details = $resumeData;

                return $app;
            });

            return response()->json([
                'status' => true,
                'data' => $jobSeeker
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch job seeker',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    //Disable a Job Seeker Account

    public function toggleJobSeekerStatus($id)
    {
        try {

            $jobSeeker = JobSeeker::withTrashed()->find($id);

            if (!$jobSeeker) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job seeker not found'
                ], 404);
            }

            $user = User::find($jobSeeker->user_id);

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // 🔥 TOGGLE (0 ↔ 1)
            $isNowActive = $user->is_active ? 0 : 1;

            $user->update([
                'is_active' => $isNowActive
            ]);

            $statusText = $isNowActive ? 'enabled' : 'disabled';
            $titleText = $isNowActive ? 'Account Enabled' : 'Account Disabled';

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            // ✅ Job Seeker
            $this->notification->send(
                'jobseeker_status',
                'jobseeker',
                $user->id,
                $titleText,
                "Your account has been {$statusText} by admin",
                [
                    'status' => $isNowActive
                ]
            );

            // ✅ Admins (loop)
            $admins = \App\Models\User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                $this->notification->send(
                    'jobseeker_status',
                    'admin',
                    $admin->id,
                    $isNowActive ? 'Job Seeker Enabled' : 'Job Seeker Disabled',
                    "Job seeker '{$user->name}' {$statusText} successfully",
                    [
                        'job_seeker_id' => $jobSeeker->id,
                        'user_id' => $user->id,
                        'status' => $isNowActive
                    ]
                );
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 MAIL (OPTIONAL)
        |--------------------------------------------------------------------------
        */

            try {

                $mailService = new MailService();

                if ($isNowActive) {
                    $mailService->send('jobseeker_enabled', [
                        'name' => $user->name
                    ], $user->email);
                } else {
                    $mailService->send('jobseeker_disabled', [
                        'name' => $user->name
                    ], $user->email);
                }
            } catch (\Exception $mailException) {

                Log::error('Job seeker status mail failed', [
                    'user_id' => $user->id,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => "Job seeker {$statusText} successfully",
                'data' => [
                    'job_seeker_id' => $jobSeeker->id,
                    'user_id' => $user->id,
                    'is_active' => $isNowActive
                ]
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Delete a Job Seeker Account

    public function deleteJobSeeker($id)
    {
        try {

            $jobSeeker = JobSeeker::withTrashed()->find($id);

            if (!$jobSeeker) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job seeker not found'
                ], 404);
            }

            $user = User::find($jobSeeker->user_id);

            $jobSeeker->delete();

            if ($user) {
                $user->delete();
            }

            return response()->json([
                'status' => true,
                'message' => 'Job seeker deleted successfully'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //======================================================================================

    //Application monitoring for Admin
    public function getApplications()
    {
        try {

            $applications = JobApplication::with([
                'job:id,title',
                'jobSeeker.user:id,name,email'
            ])
                ->latest()
                ->paginate(10);

            return response()->json([
                'status' => true,
                'total_applications' => $applications->total(),
                'data' => $applications
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch applications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Get individual application details
    public function getApplicationDetails($id)
    {
        try {

            $application = JobApplication::withTrashed()->with([
                'job',
                'jobSeeker.user',
                'jobSeeker.resumes',
                'answers.question'
            ])->find($id);

            if (!$application) {
                return response()->json([
                    'status' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $application
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch application',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Filter application by Job

    public function getApplicationsByJob($jobId)
    {
        try {

            $applications = JobApplication::withTrashed()->where('job_id', $jobId)
                ->with([
                    'jobSeeker.user:id,name,email'
                ])
                ->latest()
                ->get();

            return response()->json([
                'status' => true,
                'total' => $applications->total(),
                'data' => $applications
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch job applications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Delete application

    public function deleteApplication($id)
    {
        try {

            $application = JobApplication::withTrashed()->find($id);

            if (!$application) {
                return response()->json([
                    'status' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            $application->delete();

            return response()->json([
                'status' => true,
                'message' => 'Application deleted successfully'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    // ==============================================================================
    //Admin Newly uploaded documents

    public function getNewDocuments(Request $request)
    {
        try {

            $perPage = $request->get('per_page', 10);

            /*
        |--------------------------------------------------------------------------
        | 🔥 DOCUMENTS (PENDING)
        |--------------------------------------------------------------------------
        */

            $documents = DocumentVerification::with('employer:id,company_name,company_logo')
                ->where('status', 'pending')
                ->latest()
                ->paginate($perPage);

            /*
        |--------------------------------------------------------------------------
        | 🔥 COUNTS
        |--------------------------------------------------------------------------
        */

            // ✅ Total documents
            $total_documents = DocumentVerification::count();

            // ✅ Verified institutes (employers)
            $verified_institutes = \App\Models\Employer::where('is_verified', 1)->count();
            $unverified_institutes = \App\Models\Employer::where('is_verified', 0)->count();

            // ✅ Optional: total pending (useful for badge)
            $pending_documents = DocumentVerification::where('status', 'pending')->count();


            /*
        |--------------------------------------------------------------------------
        | ✅ RESPONSE
        |--------------------------------------------------------------------------
        */

            return response()->json([
                'status' => true,

                // 🔥 COUNTS
                'total_documents' => $total_documents,
                'pending_documents' => $pending_documents,
                'verified_institutes' => $verified_institutes,
                'unverified_institutes' => $unverified_institutes,

                // 🔥 PAGINATION
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'next_page_url' => $documents->nextPageUrl(),
                'prev_page_url' => $documents->previousPageUrl(),

                'data' => $documents->items()

            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    //Payment management for Admin
    public function getPayments(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);

            // Load 'plan' directly since it's defined in your Order model
            $payments = Order::with([
                'employer:id,company_name,company_logo',
                'plan:id,name'
            ])
                ->latest()
                ->paginate($perPage);

            $data = $payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'employer_name' => $payment->employer->company_name ?? null,
                    'employer_logo' => $payment->employer->company_logo
                        ? config('app.media_url') . $payment->employer->company_logo
                        : null,

                    // Access plan directly from the Order model
                    'plan_name' => $payment->plan->name ?? null,

                    'amount' => $payment->amount,
                    'currency' => $payment->currency,

                    // Use 'status' as defined in your $fillable
                    'status' => $payment->status,

                    // Use 'razorpay_payment_id' as defined in your $fillable
                    'transaction_id' => $payment->razorpay_payment_id,
                    'created_at' => $payment->created_at,
                ];
            });

            return response()->json([
                'status' => true,
                'total_payments' => $payments->total(),
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch payments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getPaymentDetails($id)
    {
        try {
            $payment = Order::with([
                'employer',
                'plan', // Use plan directly since it's in Order model
                'subscription.plan',
                'invoice'
            ])->find($id);

            if (!$payment) {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            $baseUrl = rtrim(config('app.media_url'), '/');

            $data = [
                'employer' => [
                    'id'    => $payment->employer->id ?? null,
                    'name'  => $payment->employer->company_name ?? null,
                    // ... other fields
                ],

                'payment' => [
                    'id'             => $payment->id,
                    'transaction_id' => $payment->transaction_id, // Match your table column
                    'amount'         => $payment->amount,
                    'status'         => $payment->payment_status,  // Match your table column
                    'created_at'     => $payment->created_at,
                ],

                'subscription' => [
                    // This will now work because of the hasOne(Subscription::class, 'order_id')
                    'id'         => $payment->subscription->id ?? null,
                    'plan_name'  => $payment->plan->name ?? $payment->subscription->plan->name ?? null,
                    'starts_at'  => $payment->subscription->starts_at ?? null,
                    'expires_at' => $payment->subscription->expires_at ?? null,
                ],

                'invoice' => [
                    // This will now work because of the hasOne(Invoice::class, 'order_id')
                    'id'             => $payment->invoice->id ?? null,
                    'invoice_number' => $payment->invoice->invoice_number ?? null,
                    'pdf_url'        => $payment->invoice->pdf_path
                        ? $baseUrl . '/' . ltrim($payment->invoice->pdf_path, '/')
                        : null,
                ]
            ];

            return response()->json([
                'status' => true,
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch payment details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
