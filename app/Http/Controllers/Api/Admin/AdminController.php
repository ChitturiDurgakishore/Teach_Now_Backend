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

class AdminController extends Controller
{
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

            $job = Job::with([
                'employer:id,company_name',
                'category:id,name',
                'jobQuestions'
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

            $job = Job::find($id);

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            $job->update([
                'status' => 'approved'
            ]);

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

    //Reject a Job Posting
    public function rejectJob($id)
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
                'status' => 'rejected'
            ]);

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

    //Mark Feature Job Posting
    public function featureJob($id)
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
                'admin_featured' => true
            ]);

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

            $job = Job::find($id);

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

            $employer = Employer::with([
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
                'document_id' => 'required|exists:document_verifications,id',
                'status' => 'required|in:approved,rejected',
                'admin_remark' => 'nullable|string'
            ]);

            $employer = Employer::find($id);

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employer not found'
                ], 404);
            }

            // 🔹 Find document
            $document = DocumentVerification::where('id', $request->document_id)
                ->where('employer_id', $id)
                ->first();

            if (!$document) {
                return response()->json([
                    'status' => false,
                    'message' => 'Document not found for this employer'
                ], 404);
            }

            // 🔥 Update document status
            $document->update([
                'status' => $request->status,
                'is_verified' => $request->status === 'approved',
                'admin_remark' => $request->admin_remark
            ]);

            // 🔥 If approved → mark employer verified
            if ($request->status === 'approved') {
                $employer->update([
                    'is_verified' => true
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Verification updated successfully',
                'data' => [
                    'employer_verified' => $employer->is_verified,
                    'document_status' => $document->status
                ]
            ], 200);
        } catch (\Exception $e) {

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

            $employer = Employer::find($id);

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employer not found'
                ], 404);
            }

            $employer->update([
                'is_featured' => true
            ]);

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

            $employer = Employer::find($id);

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

            $employer = Employer::find($id);

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employer not found'
                ], 404);
            }

            $employer->delete();

            return response()->json([
                'status' => true,
                'message' => 'Employer deleted successfully'
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

            $recruiter = EmployerUser::with([
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

            $recruiter = EmployerUser::find($id);

            if (!$recruiter) {
                return response()->json([
                    'status' => false,
                    'message' => 'Recruiter not found'
                ], 404);
            }

            $recruiter->update([
                'is_active' => 0
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Recruiter disabled successfully'
            ], 200);
        } catch (\Exception $e) {

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

            $recruiter = EmployerUser::find($id);

            if (!$recruiter) {
                return response()->json([
                    'status' => false,
                    'message' => 'Recruiter not found'
                ], 404);
            }

            $recruiter->delete();

            return response()->json([
                'status' => true,
                'message' => 'Recruiter deleted successfully'
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

            $jobSeeker = JobSeeker::with([
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

            $jobSeeker = JobSeeker::find($id);

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

            $jobSeeker = JobSeeker::find($id);

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

            $application = JobApplication::with([
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

            $applications = JobApplication::where('job_id', $jobId)
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

            $application = JobApplication::find($id);

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
}
