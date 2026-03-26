<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employer;
use App\Models\EmployerUser;
use App\Models\Job;
use App\Models\JobApplication;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\JobQuestion;
use App\Models\JobAnswer;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\DocumentVerification;
use App\Services\MailService;
use Illuminate\Support\Facades\Log;


class EmployerController extends Controller
{



    //    //Helper function for Media Uploads


    public function uploadFile($file, $folder)
    {
        $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

        $path = $file->storeAs("public/media/$folder", $filename);

        return str_replace('public/', 'storage/', $path);
    }


    // Create Company


    public function createCompany(Request $request)
    {
        try {

            $request->validate([
                'company_name' => 'required|string|max:200',
                'company_description' => 'nullable|string',
                'industry' => 'nullable|string|max:150',
                'website' => 'nullable|string',
                'company_logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'address' => 'nullable|string',
                'email' => 'required|email',
                'phone' => 'nullable|string',
                'country' => 'nullable|string',
                'city' => 'nullable|string',
                'map_link' => 'nullable|string',
                'password' => 'required|min:6'
            ]);

            // 🔥 Upload logo (FIXED)
            $logoPath = null;

            if ($request->hasFile('company_logo')) {

                $file = $request->file('company_logo');

                $path = Storage::disk('public')->putFile('media/company_logos', $file);

                $logoPath = 'storage/' . $path;
            }

            $employer = Employer::create([
                'company_name' => $request->company_name,
                'company_description' => $request->company_description,
                'industry' => $request->industry,
                'website' => $request->website,
                'company_logo' => $logoPath,
                'address' => $request->address,
                'email' => $request->email,
                'phone' => $request->phone,
                'country' => $request->country,
                'city' => $request->city,
                'map_link' => $request->map_link,
                'password' => Hash::make($request->password),
            ]);

            // MAIL (unchanged)
            try {
                $mailService = new MailService();

                $mailService->send('employer_welcome', [
                    'name' => $employer->company_name,
                    'email' => $employer->email
                ], $employer->email);
            } catch (\Exception $mailException) {
                Log::error('Employer welcome mail failed', [
                    'employer_id' => $employer->id,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Company created successfully',
                'data' => $employer
            ], 201);
        } catch (\Exception $e) {

            Log::error('Employer registration failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Company creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // EMployer Feature

    public function toggleEmployerFeatured($id)
    {
        try {

            $employer = Employer::findOrFail($id);

            $employer->company_featured = !$employer->company_featured;
            $employer->save();

            return response()->json([
                'status' => true,
                'message' => 'Employer feature status updated successfully',
                'data' => $employer
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'status' => false,
                'message' => 'Employer not found'
            ], 404);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to update employer feature status',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // Employer Login

    public function login(Request $request)
    {
        try {

            $request->validate([
                'email' => 'required|email',
                'password' => 'required'
            ]);

            $user = Employer::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {

                return response()->json([
                    'status' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // login user with session
            Auth::guard('employer')->login($user);

            $request->session()->regenerate();

            return response()->json([
                'status' => true,
                'message' => 'Employer login successful',
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

    // Employer Logout

    public function logout(Request $request)
    {
        try {

            Auth::guard('employer')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'status' => true,
                'message' => 'Employer logged out successfully'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateCompanyProfile(Request $request)
    {
        try {

            $employer = Auth::guard('employer')->user();

            $request->validate([
                'company_name' => 'required|string|max:200',
                'company_description' => 'nullable|string',
                'industry' => 'nullable|string|max:150',
                'website' => 'nullable|string',
                'company_logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'address' => 'nullable|string',
                'phone' => 'nullable|string',
                'country' => 'nullable|string',
                'city' => 'nullable|string',
                'map_link' => 'nullable|string'
            ]);

            $logoPath = $employer->company_logo;

            // 🔥 Handle logo update (FIXED)
            if ($request->hasFile('company_logo')) {

                // delete old logo
                if ($employer->company_logo) {
                    $oldPath = str_replace('storage/', 'public/', $employer->company_logo);

                    if (Storage::exists($oldPath)) {
                        Storage::delete($oldPath);
                    }
                }

                // upload new logo
                $file = $request->file('company_logo');

                $path = Storage::disk('public')->putFile('media/company_logos', $file);

                $logoPath = 'storage/' . $path;
            }

            $employer->update([
                'company_name' => $request->company_name,
                'company_description' => $request->company_description,
                'industry' => $request->industry,
                'website' => $request->website,
                'company_logo' => $logoPath,
                'address' => $request->address,
                'phone' => $request->phone,
                'country' => $request->country,
                'city' => $request->city,
                'map_link' => $request->map_link
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Company profile updated successfully',
                'data' => $employer
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Company update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Create Employer User (Recruiter)


    public function createEmployerUser(Request $request)
    {
        try {

            $request->validate([
                'name' => 'required|string|max:150',
                'email' => 'required|email|unique:employer_users,email',
                'password' => 'required|min:6'
            ]);

            $employer = Auth::guard('employer')->user();

            $user = EmployerUser::create([
                'employer_id' => $employer->id,
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password)
            ]);

            // 🔥 MAILS (QUEUE + SAFE)
            try {

                $mailService = new MailService();

                // ✅ 1. Mail to Recruiter
                $mailService->send('recruiter_added', [
                    'name' => $user->name,
                    'email' => $user->email,
                    'company_name' => $employer->company_name
                ], $user->email);

                // ✅ 2. Mail to Employer
                $mailService->send('recruiter_created_employer', [
                    'name' => $user->name,
                    'email' => $user->email,
                    'company_name' => $employer->company_name
                ], $employer->email);

                Log::info('Recruiter creation mails queued', [
                    'recruiter_id' => $user->id,
                    'employer_id' => $employer->id
                ]);
            } catch (\Exception $mailException) {

                Log::error('Recruiter mail failed', [
                    'recruiter_id' => $user->id,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Recruiter created successfully',
                'data' => $user
            ], 201);
        } catch (\Exception $e) {

            Log::error('Recruiter creation failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Recruiter creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Get Employer Users (Recruiters)


    public function getEmployerUsers()
    {
        try {

            $employer = Auth::guard('employer')->user();

            $users = EmployerUser::where('employer_id', $employer->id)->get();

            return response()->json([
                'status' => true,
                'total_users' => $users->count(),
                'data' => $users
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch employer users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteEmployerUser($id)
    {
        try {

            $employer = Auth::guard('employer')->user();

            $user = EmployerUser::where('id', $id)
                ->where('employer_id', $employer->id)
                ->first();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Recruiter not found'
                ], 404);
            }

            // 🔥 Store details before delete (IMPORTANT)
            $name = $user->name;
            $email = $user->email;

            $user->delete();


            // 🔥 MAILS (QUEUE + SAFE)
            try {

                $mailService = new MailService();

                // ✅ 1. Mail to Recruiter
                $mailService->send('recruiter_removed', [
                    'name' => $name,
                    'company_name' => $employer->company_name
                ], $email);

                // ✅ 2. Mail to Employer
                $mailService->send('recruiter_deleted_employer', [
                    'name' => $name,
                    'email' => $email,
                    'company_name' => $employer->company_name
                ], $employer->email);

                Log::info('Recruiter deletion mails queued', [
                    'recruiter_email' => $email,
                    'employer_id' => $employer->id
                ]);
            } catch (\Exception $mailException) {

                Log::error('Recruiter deletion mail failed', [
                    'recruiter_email' => $email,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Recruiter deleted successfully'
            ], 200);
        } catch (\Exception $e) {

            Log::error('Recruiter delete failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Employer Dashboard

    public function dashboard()
    {
        try {

            $employer = Auth::guard('employer')->user();

            $totalRecruiters = EmployerUser::where('employer_id', $employer->id)->count();

            $totalJobs = Job::where('employer_id', $employer->id)->count();

            $totalApplications = JobApplication::whereHas('job', function ($q) use ($employer) {
                $q->where('employer_id', $employer->id);
            })->count();

            $shortlisted = JobApplication::where('status', 'shortlisted')
                ->whereHas('job', function ($q) use ($employer) {
                    $q->where('employer_id', $employer->id);
                })->count();

            // Latest 5 Jobs posted by recruiters
            $latestJobs = Job::where('employer_id', $employer->id)
                ->latest()
                ->limit(5)
                ->select('id', 'title', 'job_status', 'created_at')
                ->get();

            // Latest 5 Applications received
            $latestApplications = JobApplication::whereHas('job', function ($q) use ($employer) {
                $q->where('employer_id', $employer->id);
            })
                ->with([
                    'job:id,title',
                    'jobSeeker.user:id,name,email'
                ])
                ->latest()
                ->limit(5)
                ->get();

            return response()->json([
                'status' => true,
                'data' => [
                    'total_recruiters' => $totalRecruiters,
                    'total_jobs' => $totalJobs,
                    'total_applications' => $totalApplications,
                    'shortlisted_candidates' => $shortlisted,

                    'latest_jobs' => $latestJobs,
                    'latest_applications' => $latestApplications
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

    // Jobs Created by Company


    public function getCompanyJobs()
    {
        try {

            $employer = Auth::guard('employer')->user();

            $jobs = Job::where('employer_id', $employer->id)->latest()->get();

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

    // Creating new Job for Company

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
                'keywords' => 'nullable|string', // 🔥 NEW
                'gender' => 'nullable|in:male,female,both',
                // questions validation
                'questions' => 'nullable|array',
                'questions.*.question' => 'required_with:questions|string',
                'questions.*.question_type' => 'required_with:questions|in:mcq,boolean,numeric,text',
                'questions.*.recruiter_answer' => 'nullable|string',
                'experience_type' => 'required|in:fresher,experienced',
            ]);

            $employer = Auth::guard('employer')->user();

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // 🔥 Create Job (with keywords)
            $job = Job::create([
                'employer_id' => $employer->id,
                'created_by' => $employer->id,
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
                'keywords' => $request->keywords, // ✅ NEW
                'gender' => $request->gender ?? 'both',
                'experience_type' => $request->experience_type,
            ]);

            $questionsCreated = [];

            // 🔥 Add questions if provided
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

            return response()->json([
                'status' => true,
                'message' => 'Job created successfully',
                'data' => [
                    'job' => $job,
                    'questions' => $questionsCreated
                ]
            ], 201);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Job creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Update Job for Company


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
                'gender' => 'nullable|in:male,female,both',

                'questions' => 'nullable|array',
                'questions.*.id' => 'nullable|exists:job_questions,id',
                'questions.*.question' => 'required_with:questions|string',
                'questions.*.question_type' => 'required_with:questions|in:mcq,boolean,numeric,text',
                'questions.*.recruiter_answer' => 'nullable|string',
                'experience_type' => 'required|in:fresher,experienced',
            ]);

            $employer = Auth::guard('employer')->user();

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $job = Job::where('id', $id)
                ->where('employer_id', $employer->id)
                ->first();

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            // 🔥 Update Job (INCLUDING keywords)
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
                'gender',
                'experience_type'
            ]));

            $questionsUpdated = [];

            // 🔥 Handle questions
            if ($request->has('questions')) {

                foreach ($request->questions as $q) {

                    if (isset($q['id'])) {

                        $question = JobQuestion::where('id', $q['id'])
                            ->where('job_id', $job->id)
                            ->first();

                        if ($question) {
                            $question->update([
                                'question' => $q['question'],
                                'question_type' => $q['question_type'],
                                'recruiter_answer' => $q['recruiter_answer'] ?? null
                            ]);
                        }
                    } else {

                        $question = JobQuestion::create([
                            'job_id' => $job->id,
                            'question' => $q['question'],
                            'question_type' => $q['question_type'],
                            'recruiter_answer' => $q['recruiter_answer'] ?? null
                        ]);
                    }

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
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Job update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //mark job as filled

    public function markJobFilled($id)
    {
        try {

            $recruiter = Auth::guard('employer')->user();

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

            return response()->json([
                'status' => true,
                'message' => 'Job marked as filled'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Delete Job for Company

    public function deleteJob($id)
    {
        try {

            $employer = Auth::guard('employer')->user();

            $job = Job::where('id', $id)
                ->where('employer_id', $employer->id)
                ->first();

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            // Delete questions first
            JobQuestion::where('job_id', $job->id)->delete();

            $job->delete();

            return response()->json([
                'status' => true,
                'message' => 'Job deleted successfully'
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Job deletion failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Job featuring

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

    //Applications for Company Jobs

    public function getApplications()
    {
        try {

            $employer = Auth::guard('employer')->user();

            $applications = JobApplication::whereHas('job', function ($q) use ($employer) {
                $q->where('employer_id', $employer->id);
            })->latest()->get();

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

    // Application management get applicaion

    //EMployer jobs
    public function getJobApplications($jobId)
    {
        try {

            $employer = Auth::guard('employer')->user();

            $job = Job::where('id', $jobId)
                ->where('employer_id', $employer->id)
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


    // View applicant profile
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

    //Shortlist applicant
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

                Log::info('Candidate shortlisted mail queued', [
                    'application_id' => $applicationId
                ]);
            } catch (\Exception $mailException) {

                Log::error('Shortlist mail failed', [
                    'application_id' => $applicationId,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Candidate shortlisted successfully'
            ], 200);
        } catch (\Exception $e) {

            Log::error('Shortlisting failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Shortlisting failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Reject Candidate

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

                Log::info('Candidate rejected mail queued', [
                    'application_id' => $applicationId
                ]);
            } catch (\Exception $mailException) {

                Log::error('Reject mail failed', [
                    'application_id' => $applicationId,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Candidate rejected successfully'
            ], 200);
        } catch (\Exception $e) {

            Log::error('Rejection failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Rejection failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Get shortlisted candidates

    public function getShortlistedCandidates($jobId)
    {
        try {

            $employer = Auth::guard('employer')->user();

            $job = Job::where('id', $jobId)
                ->where('employer_id', $employer->id)
                ->first();

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            $shortlisted = JobApplication::where('job_id', $jobId)
                ->where('status', 'shortlisted')
                ->with('jobSeeker.user')
                ->get();

            return response()->json([
                'status' => true,
                'total_shortlisted' => $shortlisted->count(),
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

    //=======================================================================================

    //Upload verification documents


    public function uploadDocument(Request $request)
    {
        try {

            $request->validate([
                'document_name' => 'nullable|string',
                'document_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240'
            ]);

            $employer = Auth::guard('employer')->user();
            $file = $request->file('document_file');

            $docName = $request->document_name ?? $file->getClientOriginalName();

            $filePath = $this->uploadFile($file, 'documents');

            $doc = DocumentVerification::create([
                'employer_id' => $employer->id,
                'document_name' => $docName,
                'document_file' => $filePath,
                'status' => 'pending'
            ]);

            // 🔥 MAIL (QUEUED + SAFE)
            try {

                $mailService = new MailService();

                $mailService->send('document_uploaded', [
                    'name' => $employer->company_name,
                    'document_name' => $docName
                ], $employer->email);

                Log::info('Document upload mail queued', [
                    'employer_id' => $employer->id,
                    'email' => $employer->email
                ]);
            } catch (\Exception $mailException) {

                Log::error('Document upload mail failed', [
                    'employer_id' => $employer->id,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Document uploaded successfully',
                'data' => $doc
            ]);
        } catch (\Exception $e) {

            Log::error('Document upload failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Upload failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    //View uploaded documents
    public function getMyDocuments()
    {
        $employer = Auth::guard('employer')->user();

        $docs = DocumentVerification::where('employer_id', $employer->id)->get();

        return response()->json([
            'status' => true,
            'data' => $docs
        ]);
    }

    //    testimonials wrote in recruiter controller - common for both recruiter and employer
}
