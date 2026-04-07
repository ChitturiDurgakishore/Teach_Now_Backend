<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmployerUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobQuestion;
use App\Models\JobAnswer;
use App\Services\MailService;
use Illuminate\Support\Facades\Log;
use App\Models\JobSeeker;
use App\Models\HomepageTestimonial;
use App\Models\Employer;
use Illuminate\Support\Facades\DB;
use App\Services\SubscriptionService;
use App\Models\Subscription;


class RecruiterController extends Controller
{
    // Recruiter login
    public function login(Request $request)
    {
        try {

            $request->validate([
                'email' => 'required|email',
                'password' => 'required'
            ]);

            $user = EmployerUser::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            if (!$user->is_active == 1) {
                return response()->json([
                    'status' => false,
                    'message' => 'Account disabled'
                ], 403);
            }

            Auth::guard('employer_user')->login($user);

            $request->session()->regenerate();

            return response()->json([
                'status' => true,
                'message' => 'Recruiter login successful',
                'role' => 'recruiter',
                'user' => $user
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //  Logout recruiter
    public function logout(Request $request)
    {
        try {

            Auth::guard('employer_user')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'status' => true,
                'message' => 'Recruiter logged out successfully'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Recruiter profile
    // Get Recruiter Profile with Company Details
    public function getProfile()
    {
        try {
            $user = Auth::guard('employer_user')->user();
            $profile = EmployerUser::with('employer')
                ->find($user->id);

            if (!$profile) {
                return response()->json([
                    'status' => false,
                    'message' => 'Profile not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Profile fetched successfully',
                'data' => $profile
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // Recruiter Job creation


    public function createJob(Request $request)
    {
        try {

            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'category_id' => 'required|exists:categories,id',
                'salary_min' => 'nullable|numeric',
                'salary_max' => 'nullable|numeric',
                'vacancies' => 'nullable|integer',
                'location' => 'nullable|string|max:200',
                'experience_required' => 'nullable|integer',
                'job_type' => 'required|in:full_time,part_time,internship,contract',
                'application_deadline' => 'nullable|date',
                'keywords' => 'nullable|string',
                'questions' => 'nullable|array',
                'questions.*.question' => 'required_with:questions|string',
                'questions.*.question_type' => 'required_with:questions|in:mcq,boolean,numeric,text',
                'questions.*.recruiter_answer' => 'nullable|string',
                'gender' => 'nullable|in:male,female,both',
                'experience_type' => 'required|in:fresher,experienced',
            ]);

            $recruiter = Auth::guard('employer_user')->user();

            if (!$recruiter) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            DB::beginTransaction();

            // 🔥 GET EMPLOYER ID
            $employerId = $recruiter->employer_id;

            // 🔥 CONSUME CREDIT FROM EMPLOYER PLAN
            $subscriptionService = app(SubscriptionService::class);

            $result = $subscriptionService->consumeJobCredit($employerId);

            if (!$result['status']) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => $result['message']
                ], 403);
            }

            $subscription = $result['subscription'];

            // 🔥 CREATE JOB
            $job = Job::create([
                'employer_id' => $employerId,
                'created_by' => $recruiter->id,
                'category_id' => $request->category_id,
                'title' => $request->title,
                'description' => $request->description,
                'salary_min' => $request->salary_min,
                'salary_max' => $request->salary_max,
                'vacancies' => $request->vacancies,
                'location' => $request->location,
                'experience_required' => $request->experience_required,
                'job_type' => $request->job_type,
                'application_deadline' => $request->application_deadline,
                'keywords' => $request->keywords,
                'gender' => $request->gender ?? 'both',
                'experience_type' => $request->experience_type,

                // 🔥 IMPORTANT: dynamic expiry from plan
                'expires_at' => now()->addDays($subscription->plan->job_live_days),

                'is_active' => true
            ]);

            $questionsCreated = [];

            if ($request->has('questions')) {
                foreach ($request->questions as $q) {

                    $question = JobQuestion::create([
                        'job_id' => $job->id,
                        'question' => $q['question'],
                        'question_type' => $q['question_type'],
                        'recruiter_answer' => $q['recruiter_answer'] ?? null
                    ]);

                    $questionsCreated[] = $question;
                }
            }

            // 🔥 MAILS (unchanged)
            try {

                $mailService = new MailService();

                $mailService->send('job_created_recruiter', [
                    'name' => $recruiter->name,
                    'job_title' => $job->title
                ], $recruiter->email);

                $mailService->send('job_created_employer', [
                    'company_name' => $recruiter->employer->company_name,
                    'job_title' => $job->title,
                    'recruiter_name' => $recruiter->name
                ], $recruiter->employer->email);
            } catch (\Exception $mailException) {

                Log::error('Job creation mail failed', [
                    'job_id' => $job->id,
                    'error' => $mailException->getMessage()
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Job created successfully',
                'data' => [
                    'job' => $job,
                    'questions' => $questionsCreated
                ]
            ], 201);
        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Job creation failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Job creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Job update
    public function updateJob(Request $request, $id)
    {
        try {

            $request->validate([
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'category_id' => 'nullable|exists:categories,id',
                'salary_min' => 'nullable|numeric',
                'salary_max' => 'nullable|numeric',
                'vacancies' => 'nullable|integer',
                'location' => 'nullable|string|max:200',
                'experience_required' => 'nullable|integer',
                'job_type' => 'nullable|in:full_time,part_time,internship,contract',
                'application_deadline' => 'nullable|date',
                'keywords' => 'nullable|string', // 🔥 NEW
                'gender' => 'nullable|in:male,female,both', // 🔥 NEW
                // questions validation
                'questions' => 'nullable|array',
                'questions.*.question' => 'required_with:questions|string',
                'questions.*.question_type' => 'required_with:questions|in:mcq,boolean,numeric,text',
                'questions.*.recruiter_answer' => 'nullable|string',
                'experience_type' => 'required|in:fresher,experienced',
            ]);

            $recruiter = Auth::guard('employer_user')->user();

            $job = Job::where('id', $id)
                ->where('created_by', $recruiter->id)
                ->first();

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            // 🔥 Update job fields (INCLUDING keywords)
            $job->update($request->only([
                'title',
                'description',
                'category_id',
                'salary_min',
                'salary_max',
                'vacancies',
                'location',
                'experience_required',
                'job_type',
                'application_deadline',
                'keywords', // ✅ NEW
                'gender', // ✅ NEW,
                'experience_type'
            ]));

            $questionsUpdated = [];

            // 🔥 Update questions if provided
            if ($request->has('questions')) {

                // remove old questions
                JobQuestion::where('job_id', $job->id)->delete();

                foreach ($request->questions as $q) {

                    $question = JobQuestion::create([
                        'job_id' => $job->id,
                        'question' => $q['question'],
                        'question_type' => $q['question_type'],
                        'recruiter_answer' => $q['recruiter_answer'] ?? null
                    ]);

                    $questionsUpdated[] = $question;
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Job updated successfully',
                'data' => [
                    'job' => $job,
                    'questions' => $questionsUpdated
                ]
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Job update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Republish Job

    public function republishJob($id)
    {
        try {

            $job = Job::find($id);

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            $recruiter = Auth::guard('employer_user')->user();

            if (!$recruiter) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // ✅ Employer ownership check
            if ($job->employer_id !== $recruiter->employer_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            DB::beginTransaction();

            /*
        |--------------------------------------------------------------------------
        | 🔥 CHECK & CONSUME EMPLOYER CREDITS
        |--------------------------------------------------------------------------
        */

            $subscriptionService = app(SubscriptionService::class);

            $result = $subscriptionService->consumeJobCredit($recruiter->employer_id);

            if (!$result['status']) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => $result['message']
                ], 403);
            }

            $subscription = $result['subscription'];

            /*
        |--------------------------------------------------------------------------
        | 🔥 OPTIONAL: Prevent republishing active job
        |--------------------------------------------------------------------------
        */

            if ($job->is_active && $job->expires_at > now()) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Job is already active'
                ], 400);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 REPUBLISH JOB
        |--------------------------------------------------------------------------
        */

            $job->update([
                'expires_at' => now()->addDays($subscription->plan->job_live_days),
                'is_active' => true,
                'job_status' => 'open' // recommended
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Job republished successfully'
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Republish failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    //Feature job
    public function toggleJobFeatured($id)
    {
        try {

            $job = Job::findOrFail($id);

            $job->featured = !$job->featured;
            $job->save();

            return response()->json([
                'status' => true,
                'message' => 'Job feature status updated successfully',
                'data' => $job
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'status' => false,
                'message' => 'Job not found'
            ], 404);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to update job feature status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Mark job as filled
    public function markJobFilled($id)
    {
        try {

            $recruiter = Auth::guard('employer_user')->user();

            $job = Job::where('id', $id)
                ->where('created_by', $recruiter->id)
                ->first();

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            $job->update([
                'job_status' => 'filled'
            ]);

            // 🔥 MAILS (QUEUE + SAFE)
            try {

                $mailService = new MailService();

                // ✅ Recruiter mail
                $mailService->send('job_filled_recruiter', [
                    'name' => $recruiter->name,
                    'job_title' => $job->title
                ], $recruiter->email);

                // ✅ Employer mail
                $mailService->send('job_filled_employer', [
                    'company_name' => $recruiter->employer->company_name,
                    'job_title' => $job->title,
                    'recruiter_name' => $recruiter->name
                ], $recruiter->employer->email);

                Log::info('Job filled mails queued', [
                    'job_id' => $job->id,
                    'recruiter_id' => $recruiter->id
                ]);
            } catch (\Exception $mailException) {

                Log::error('Job filled mail failed', [
                    'job_id' => $job->id,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Job marked as filled'
            ], 200);
        } catch (\Exception $e) {

            Log::error('Mark job filled failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get recruiter Jobs

    public function getRecruiterJobs()
    {
        try {

            $recruiter = Auth::guard('employer_user')->user();

            $jobs = Job::where('expires_at', '>', now())->where('created_by', $recruiter->id)
                ->latest()
                ->get();

            return response()->json([
                'status' => true,
                'total_jobs' => $jobs->count(),
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

    // Expired Jobs

    public function getExpiredJobsForRecruiter()
    {
        $recruiter = Auth::guard('employer_user')->user();

        if (!$recruiter) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $jobs = Job::where('employer_id', $recruiter->employer_id)
            ->where('expires_at', '<', now())
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'data' => $jobs
        ]);
    }

    // Get all applications for recruiter's jobs

    public function getApplications()
    {
        try {
            $recruiter = Auth::guard('employer_user')->user();

            $applications = JobApplication::whereHas('job', function ($q) use ($recruiter) {
                $q->where('created_by', $recruiter->id);
            })
                ->with(['jobSeeker', 'job:id,title'])
                ->latest()
                ->get();

            return response()->json([
                'status' => true,
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


    // Get applications for a specific job

    public function getJobApplications($jobId)
    {
        try {

            $recruiter = Auth::guard('employer_user')->user();

            $job = Job::where('id', $jobId)
                ->where('created_by', $recruiter->id)
                ->first();

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            $applications = JobApplication::where('job_id', $jobId)
                ->with(['jobSeeker.user'])
                ->latest()
                ->get();

            // Manually load the answers for each application to avoid the SQL error
            // Manually load the answers for each application
            $applications = JobApplication::where('job_id', $jobId)
                ->with(['jobSeeker.user'])
                ->latest()
                ->get();

            foreach ($applications as $application) {
                // We fetch the answers manually
                $answers = \App\Models\JobAnswer::where('job_id', $application->job_id)
                    ->where('job_seeker_id', $application->job_seeker_id)
                    ->with('question')
                    ->get();

                // We force the 'answers' attribute to contain this data
                $application->setAttribute('answers', $answers);
            }

            return response()->json([
                'status' => true,
                'total_applications' => $applications->count(),
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

    public function viewApplicantProfile($applicationId)
    {
        try {
            // 1. Fetch the application with basics
            $application = JobApplication::with([
                'jobSeeker.user',
                'jobSeeker.skills:id,name',
                'resume',
                'jobSeeker.educations',     // ✅ ADD
                'jobSeeker.experiences',
            ])->find($applicationId);

            if (!$application) {
                return response()->json([
                    'status' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            // 2. Manually load the answers using the application's data
            // This ensures $application->job_id and $application->job_seeker_id are available
            $answers = JobAnswer::where('job_id', $application->job_id)
                ->where('job_seeker_id', $application->job_seeker_id)
                ->with('question')
                ->get();

            // 3. Attach it to the application object
            $application->setRelation('answers', $answers);

            return response()->json([
                'status' => true,
                'data' => $application
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Shortlist candidate

    public function shortlistCandidate($applicationId)
    {
        try {

            $application = JobApplication::with(['job', 'jobSeeker.user'])->find($applicationId);

            if (!$application) {
                return response()->json([
                    'status' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            $application->update([
                'status' => 'shortlisted'
            ]);

            // 🔥 MAIL (QUEUE)
            try {

                $user = $application->jobSeeker->user;

                $mailService = new MailService();

                $mailService->send('candidate_shortlisted', [
                    'name' => $user->name,
                    'job_title' => $application->job->title
                ], $user->email);

                Log::info('Recruiter shortlisted mail queued', [
                    'application_id' => $applicationId
                ]);
            } catch (\Exception $mailException) {

                Log::error('Recruiter shortlist mail failed', [
                    'application_id' => $applicationId,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Candidate shortlisted successfully'
            ], 200);
        } catch (\Exception $e) {

            Log::error('Recruiter shortlisting failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Shortlisting failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Reject candidate



    public function rejectCandidate($applicationId)
    {
        try {

            $application = JobApplication::with(['job', 'jobSeeker.user'])->find($applicationId);

            if (!$application) {
                return response()->json([
                    'status' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            $application->update([
                'status' => 'rejected'
            ]);

            // 🔥 MAIL (QUEUE)
            try {

                $user = $application->jobSeeker->user;

                $mailService = new MailService();

                $mailService->send('candidate_rejected', [
                    'name' => $user->name,
                    'job_title' => $application->job->title
                ], $user->email);

                Log::info('Recruiter rejected mail queued', [
                    'application_id' => $applicationId
                ]);
            } catch (\Exception $mailException) {

                Log::error('Recruiter reject mail failed', [
                    'application_id' => $applicationId,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Candidate rejected successfully'
            ], 200);
        } catch (\Exception $e) {

            Log::error('Recruiter rejection failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Rejection failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Shortlisted Candidates list
    public function getShortlistedCandidates($jobId)
    {
        try {

            $shortlisted = JobApplication::where('job_id', $jobId)
                ->where('status', 'shortlisted')
                ->with('jobSeeker')
                ->paginate(10);

            return response()->json([
                'status' => true,
                'total_shortlisted' => $shortlisted->total(),
                'data' => $shortlisted
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch shortlisted candidates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get all shortlisted candidates across all jobs
    public function getAllShortlistedCandidates()
    {
        try {
            $recruiter = Auth::guard('employer_user')->user();

            $shortlisted = JobApplication::whereHas('job', function ($q) use ($recruiter) {
                $q->where('created_by', $recruiter->id);
            })
                ->where('status', 'shortlisted')
                ->with(['jobSeeker', 'job:id,title'])
                ->latest()
                ->get();

            return response()->json([
                'status' => true,
                'data' => $shortlisted
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch shortlisted candidates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function dashboard()
    {
        try {

            $recruiter = Auth::guard('employer_user')->user();

            if (!$recruiter) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $employerId = $recruiter->employer_id;

            $activeJobs = Job::where('created_by', $recruiter->id)
                ->where('job_status', 'open')
                ->count();

            $jobsFilled = Job::where('created_by', $recruiter->id)
                ->where('job_status', 'filled')
                ->count();

            $totalApplicants = JobApplication::whereHas('job', function ($q) use ($recruiter) {
                $q->where('created_by', $recruiter->id);
            })->count();

            // 🔥 CREDIT INFO (FROM EMPLOYER)
            $subscriptions = Subscription::with('plan')
                ->where('employer_id', $employerId)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->get();

            $totalCredits = $subscriptions->sum('job_posts_total');
            $usedCredits = $subscriptions->sum('job_posts_used');
            $remainingCredits = $totalCredits - $usedCredits;

            // 🔥 CURRENT ACTIVE PLAN (for display)
            $currentPlan = $subscriptions
                ->sortBy('starts_at')
                ->first();

            $planData = null;

            if ($currentPlan) {
                $planData = [
                    'plan_name' => $currentPlan->plan->name ?? null,
                    'expires_at' => $currentPlan->expires_at
                ];
            }

            $recentJobs = Job::where('created_by', $recruiter->id)
                ->withCount('jobApplications')
                ->latest()
                ->limit(5)
                ->get();

            $recentApplications = JobApplication::whereHas('job', function ($q) use ($recruiter) {
                $q->where('created_by', $recruiter->id);
            })
                ->latest()
                ->limit(5)
                ->with(['jobSeeker', 'job:id,title'])
                ->get();

            return response()->json([
                'status' => true,
                'data' => [
                    'active_jobs' => $activeJobs,
                    'jobs_filled' => $jobsFilled,
                    'total_applicants' => $totalApplicants,

                    // 🔥 NEW CREDIT DATA
                    'credits' => [
                        'total_credits' => $totalCredits,
                        'used_credits' => $usedCredits,
                        'remaining_credits' => $remainingCredits,
                        'current_plan' => $planData
                    ],

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

    // Testimonails for recruiter dashboard

    public function createTestimonial(Request $request)
    {
        try {

            $request->validate([
                'message' => 'required|string',
                'display_order' => 'nullable|integer'
            ]);

            // 🔥 Detect logged-in user (recruiter OR employer)
            $user = Auth::guard('employer_user')->user()
                ?? Auth::guard('employer')->user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Only employers/recruiters allowed'
                ], 403);
            }

            $name = null;
            $designation = null;
            $company = null;
            $photo = null;

            // 🔥 Recruiter flow
            if (Auth::guard('employer_user')->check()) {

                $employer = Employer::find($user->employer_id);

                if (!$employer) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Employer not found'
                    ], 404);
                }

                $name = $user->name;
                $designation = 'Recruiter';
                $company = $employer->company_name;
                $photo = $employer->company_logo;
            }

            // 🔥 Employer flow
            elseif (Auth::guard('employer')->check()) {

                $employer = $user;

                $name = $employer->company_name;
                $designation = 'Employer';
                $company = $employer->company_name;
                $photo = $employer->company_logo;
            }

            $testimonial = HomepageTestimonial::create([
                'name' => $name,
                'designation' => $designation,
                'company' => $company,
                'message' => $request->message,
                'photo' => $photo,
                'display_order' => $request->display_order ?? 0,
                'is_active' => true,
                'user_id' => $user->id
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

    // Get Testimonials

    public function getTestimonials()
    {
        try {

            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $testimonials = HomepageTestimonial::where('user_id', $user->id)
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

    //update testimonial
    public function updateTestimonial(Request $request, $id)
    {
        try {

            // 🔥 Detect logged-in user (recruiter OR employer)
            $user = Auth::guard('employer_user')->user()
                ?? Auth::guard('employer')->user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Only employers/recruiters allowed'
                ], 403);
            }

            $testimonial = HomepageTestimonial::find($id);

            if (!$testimonial) {
                return response()->json([
                    'status' => false,
                    'message' => 'Testimonial not found'
                ], 404);
            }

            // 🔥 Optional: ensure user owns testimonial
            if ($testimonial->user_id != $user->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $name = $testimonial->name;
            $designation = $testimonial->designation;
            $company = $testimonial->company;
            $photo = $testimonial->photo;

            // 🔥 Recruiter flow
            if (Auth::guard('employer_user')->check()) {

                $employer = Employer::find($user->employer_id);

                if ($employer) {
                    $name = $user->name;
                    $designation = 'Recruiter';
                    $company = $employer->company_name;
                    $photo = $employer->company_logo;
                }
            }

            // 🔥 Employer flow
            elseif (Auth::guard('employer')->check()) {

                $employer = $user;

                $name = $employer->company_name;
                $designation = 'Employer';
                $company = $employer->company_name;
                $photo = $employer->company_logo;
            }

            // 🔥 Update (ONLY message + order from request)
            $testimonial->update([
                'name' => $name,
                'designation' => $designation,
                'company' => $company,
                'message' => $request->message ?? $testimonial->message,
                'photo' => $photo,
                'display_order' => $request->display_order ?? $testimonial->display_order,
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

    // Delete testimonial
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
                'message' => 'Deletion failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Contact Status unpdate

    public function updateContactStatusByRecruiter(Request $request, $id)
    {
        try {

            $request->validate([
                'contact_status' => 'required|in:called,messaged,not_picked,not_reachable'
            ]);

            $recruiter = Auth::guard('employer_user')->user();

            if (!$recruiter) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $application = JobApplication::with('job')->find($id);

            if (!$application) {
                return response()->json([
                    'status' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            // 🔥 Recruiter belongs to employer
            if ($application->job->employer_id != $recruiter->employer_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $application->update([
                'contact_status' => $request->contact_status
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Contact status updated (Recruiter)',
                'data' => $application
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
