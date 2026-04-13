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
use App\Services\MailService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\Notification;


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
                'employer:id,company_name'
            ])
                ->latest()
                ->limit(5)
                ->get();

            // Recent Applications
            $recentApplications = JobApplication::with([
                'job:id,title',
                'jobSeeker:id,user_id'
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
    public function getAllJobs()
    {
        try {

            $jobs = Job::with([
                'employer:id,company_name',
                'category:id,name'
            ])
                ->latest()
                ->paginate(10);

            return response()->json([
                'status' => true,
                'total_jobs' => $jobs->total(),
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

    //Get Single Job Details
    public function getJobDetails($id)
    {
        try {

            $job = Job::withTrashed()->with([
                'employer:id,company_name',
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

            // ✅ Admin (optional log notification)
            $this->notification->send(
                'job_approved',
                'admin',
                null,
                'Job Approved',
                "Job '{$job->title}' approved successfully",
                [
                    'job_id' => $job->id,
                    'employer_id' => $job->employer_id
                ]
            );

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
            $this->notification->send(
                'job_rejected',
                'admin',
                null,
                'Job Rejected',
                "Job '{$job->title}' has been rejected",
                [
                    'job_id' => $job->id,
                    'employer_id' => $job->employer_id
                ]
            );

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

    // Mark Feature Job Posting
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

            $job->update([
                'admin_featured' => true
            ]);

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            // ⭐ Employer (MAIN)
            $this->notification->send(
                'job_admin_featured',
                'employer',
                $job->employer_id,
                'Job Featured',
                "Your job '{$job->title}' has been featured by admin",
                [
                    'job_id' => $job->id
                ]
            );

            // ⭐ Recruiter (if exists)
            if ($job->created_by) {
                $this->notification->send(
                    'job_admin_featured',
                    'recruiter',
                    $job->created_by,
                    'Job Featured',
                    "Your job '{$job->title}' has been featured by admin",
                    [
                        'job_id' => $job->id
                    ]
                );
            }

            // ⚙️ Admin (optional log)
            $this->notification->send(
                'job_admin_featured',
                'admin',
                null,
                'Job Featured',
                "Job '{$job->title}' marked as featured",
                [
                    'job_id' => $job->id,
                    'employer_id' => $job->employer_id
                ]
            );

            return response()->json([
                'status' => true,
                'message' => 'Job marked as featured'
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

            // ⚙️ Admin (optional log)
            $this->notification->send(
                'job_republished',
                'admin',
                null,
                'Job Republished',
                "Job '{$job->title}' republished successfully",
                [
                    'job_id' => $job->id,
                    'employer_id' => $job->employer_id
                ]
            );

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
    public function getEmployers()
    {
        try {

            $employers = Employer::latest()->paginate(10);

            return response()->json([
                'status' => true,
                'total_employers' => $employers->total(),
                'data' => $employers
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

            // ⚙️ Admin log (optional)
            $this->notification->send(
                'employer_verification',
                'admin',
                null,
                'Employer Verification Updated',
                "Employer '{$employer->company_name}' marked as '{$request->status}'",
                [
                    'employer_id' => $employer->id
                ]
            );

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

            $employer->update([
                'is_featured' => true
            ]);

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            // ⭐ Employer (MAIN)
            $this->notification->send(
                'employer_featured',
                'employer',
                $employer->id,
                'Company Featured',
                "Your company '{$employer->company_name}' has been featured by admin",
                []
            );

            // ⚙️ Admin (optional log)
            $this->notification->send(
                'employer_featured',
                'admin',
                null,
                'Employer Featured',
                "Employer '{$employer->company_name}' marked as featured",
                [
                    'employer_id' => $employer->id
                ]
            );

            return response()->json([
                'status' => true,
                'message' => 'Employer marked as featured'
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

            $recruiters = EmployerUser::with('employer:id,company_name')
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
                'employer:id,company_name',
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



    public function disableRecruiter($id)
    {
        try {

            $recruiter = EmployerUser::withTrashed()->find($id);

            if (!$recruiter) {
                return response()->json([
                    'status' => false,
                    'message' => 'Recruiter not found'
                ], 404);
            }

            $recruiter->update([
                'is_active' => 0
            ]);

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            // 🚫 Recruiter (MAIN)
            $this->notification->send(
                'recruiter_disabled',
                'recruiter',
                $recruiter->id,
                'Account Disabled',
                "Your recruiter account has been disabled by admin",
                []
            );

            // 🏢 Employer
            $this->notification->send(
                'recruiter_disabled',
                'employer',
                $recruiter->employer_id,
                'Recruiter Disabled',
                "Recruiter '{$recruiter->name}' has been disabled",
                [
                    'recruiter_id' => $recruiter->id
                ]
            );

            // ⚙️ Admin (optional log)
            $this->notification->send(
                'recruiter_disabled',
                'admin',
                null,
                'Recruiter Disabled',
                "Recruiter '{$recruiter->name}' disabled successfully",
                [
                    'recruiter_id' => $recruiter->id,
                    'employer_id' => $recruiter->employer_id
                ]
            );

            /*
        |--------------------------------------------------------------------------
        | 🔥 MAIL (UNCHANGED)
        |--------------------------------------------------------------------------
        */

            try {

                $mailService = new MailService();

                // ✅ Recruiter mail
                $mailService->send('recruiter_disabled', [
                    'name' => $recruiter->name,
                    'company_name' => $recruiter->employer->company_name
                ], $recruiter->email);

                // ✅ Employer mail
                $mailService->send('recruiter_disabled_employer', [
                    'name' => $recruiter->name,
                    'email' => $recruiter->email,
                    'company_name' => $recruiter->employer->company_name
                ], $recruiter->employer->email);
            } catch (\Exception $mailException) {

                Log::error('Recruiter disable mail failed', [
                    'recruiter_id' => $recruiter->id,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Recruiter disabled successfully'
            ], 200);
        } catch (\Exception $e) {

            Log::error('Disable recruiter failed', [
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
            $this->notification->send(
                'recruiter_deleted',
                'admin',
                null,
                'Recruiter Deleted',
                "Recruiter '{$name}' deleted successfully",
                [
                    'recruiter_id' => $recruiterId,
                    'employer_id' => $employerId
                ]
            );

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

    public function getJobSeekers()
    {
        try {

            $jobSeekers = JobSeeker::with('user:id,name,email')
                ->latest()
                ->paginate(10);

            return response()->json([
                'status' => true,
                'total_job_seekers' => $jobSeekers->total(),
                'data' => $jobSeekers
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
                'user:id,name,email',
                'resumes',
                'jobApplications.job:id,title'
            ])->find($id);

            if (!$jobSeeker) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job seeker not found'
                ], 404);
            }

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

    public function disableJobSeeker($id)
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

            if ($user) {
                $user->update([
                    'is_active' => 0
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Job seeker disabled successfully'
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


}
