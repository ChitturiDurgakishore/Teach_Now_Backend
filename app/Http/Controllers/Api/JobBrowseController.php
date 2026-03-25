<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobSeeker;
use App\Models\Resume;
use Illuminate\Support\Facades\Auth;
use App\Models\BookmarkedJob;
use App\Models\Employer;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use App\Models\JobQuestion;
use App\Models\JobAnswer;
use App\Services\MailService;
use Illuminate\Support\Facades\Log;

class JobBrowseController extends Controller
{

    //Open route for job seekers to browse approved and open jobs
    public function browseJobs()
    {
        try {

            $jobs = Job::where('status', 'approved')
                ->where('job_status', 'open')
                ->with(['employer:id,company_name,company_logo'])
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

    //Open route for job seekers to view details of a specific job

    public function viewJob($slug)
    {
        try {

            // 🔥 1. Get job directly by slug
            $job = Job::with([
                'employer:id,company_name,company_logo,slug,city'
            ])->where('slug', $slug)
                ->where('status', 'approved')
                ->where('job_status', 'open')
                ->first();

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            // 🔥 2. Questions
            $questions = JobQuestion::where('job_id', $job->id)->get();

            // 🔥 Prepare keywords for matching
            $title = strtolower($job->title);
            $slugFormatted = strtolower($job->slug);
            $titleWords = array_filter(explode(' ', $title));

            // 🔥 3. Similar Jobs (SMART MATCH)
            $similarJobs = Job::with([
                'employer:id,company_name,company_logo,slug,city'
            ])->where('id', '!=', $job->id)
                ->where('status', 'approved')
                ->where('job_status', 'open')
                ->where(function ($query) use ($title, $slugFormatted, $titleWords) {

                    // ✅ Full title match
                    $query->where('title', 'LIKE', "%$title%")

                        // ✅ Slug match
                        ->orWhere('slug', 'LIKE', "%$slugFormatted%")

                        // ✅ Remove space match (hinditeacher type)
                        ->orWhereRaw("REPLACE(LOWER(title), ' ', '') LIKE ?", [
                            str_replace(' ', '', $title)
                        ]);

                    // ✅ Word-based matching (at least 1 strong word)
                    foreach ($titleWords as $word) {
                        if (strlen($word) > 3) { // avoid small words like "a", "to"
                            $query->orWhere('title', 'LIKE', "%$word%")
                                ->orWhere('keywords', 'LIKE', "%$word%");
                        }
                    }
                })
                ->orderByRaw("
                CASE
                    WHEN title LIKE ? THEN 1
                    WHEN slug LIKE ? THEN 2
                    ELSE 3
                END
            ", [
                    "%$title%",
                    "%$slugFormatted%"
                ])
                ->limit(5)
                ->get();

            return response()->json([
                'status' => true,
                'data' => [
                    'job' => $job,
                    'questions' => $questions,
                    'similar_jobs' => $similarJobs
                ]
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch job',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Job Application

    public function applyJob(Request $request, $jobId)
    {
        try {

            $request->validate([
                'answers' => 'nullable|array',
                'answers.*.question_id' => 'required|exists:job_questions,id',
                'answers.*.candidate_answer' => 'required'
            ]);

            $user = Auth::user();

            $jobSeeker = JobSeeker::where('user_id', $user->id)->first();

            if (!$jobSeeker) {
                return response()->json([
                    'status' => false,
                    'message' => 'Profile not found'
                ], 404);
            }

            $job = Job::where('id', $jobId)
                ->where('status', 'approved')
                ->where('job_status', 'open')
                ->first();

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not available'
                ], 404);
            }

            $existing = JobApplication::where('job_id', $jobId)
                ->where('job_seeker_id', $jobSeeker->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => false,
                    'message' => 'Already applied for this job'
                ], 409);
            }

            // 🔥 Resume is OPTIONAL now
            $resume = Resume::where('job_seeker_id', $jobSeeker->id)
                ->where('is_default', true)
                ->first();

            // 🔹 Create Application
            $application = JobApplication::create([
                'job_id' => $jobId,
                'job_seeker_id' => $jobSeeker->id,
                'resume_id' => $resume ? $resume->id : null, // ✅ FIX
                'status' => 'applied'
            ]);

            // 🔹 Save Answers
            if ($request->has('answers')) {
                foreach ($request->answers as $ans) {
                    JobAnswer::create([
                        'job_question_id' => $ans['question_id'],
                        'job_id' => $jobId,
                        'job_seeker_id' => $jobSeeker->id,
                        'candidate_answer' => $ans['candidate_answer']
                    ]);
                }
            }

            // 🔥 MAIL (QUEUE + SAFE)
            try {

                $mailService = new MailService();

                $mailService->send('job_applied', [
                    'name' => $user->name,
                    'job_title' => $job->title
                ], $user->email);

                Log::info('Job applied mail queued', [
                    'job_id' => $jobId,
                    'job_seeker_id' => $jobSeeker->id
                ]);
            } catch (\Exception $mailException) {

                Log::error('Job applied mail failed', [
                    'job_id' => $jobId,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Job applied successfully',
                'data' => $application
            ], 201);
        } catch (\Exception $e) {

            Log::error('Job application failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Application failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    //Get applied Jobs

    public function getAppliedJobs()
    {
        try {

            $user = Auth::user();

            $jobSeeker = JobSeeker::where('user_id', $user->id)->first();

            $applications = JobApplication::where('job_seeker_id', $jobSeeker->id)
                ->with('job')
                ->latest()
                ->get();

            return response()->json([
                'status' => true,
                'total' => $applications->count(),
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

    //withdraw application
    public function withdrawApplication($id)
    {
        try {

            $user = Auth::user();

            $jobSeeker = JobSeeker::where('user_id', $user->id)->first();

            $application = JobApplication::where('id', $id)
                ->where('job_seeker_id', $jobSeeker->id)
                ->first();

            if (!$application) {
                return response()->json([
                    'status' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            $application->delete();

            return response()->json([
                'status' => true,
                'message' => 'Application withdrawn successfully'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //shortlisted jobs

    public function getShortlistedJobs()
    {
        try {

            $user = Auth::user();

            $jobSeeker = JobSeeker::where('user_id', $user->id)->first();

            $shortlisted = JobApplication::where('job_seeker_id', $jobSeeker->id)
                ->where('status', 'shortlisted')
                ->with('job')
                ->get();

            return response()->json([
                'status' => true,
                'total' => $shortlisted->count(),
                'data' => $shortlisted
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch shortlisted jobs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Bookmarking jobs
    public function bookmarkJob($jobId)
    {
        try {

            $user = Auth::user();

            $jobSeeker = JobSeeker::where('user_id', $user->id)->first();

            if (!$jobSeeker) {
                return response()->json([
                    'status' => false,
                    'message' => 'Profile not found'
                ], 404);
            }

            $existing = BookmarkedJob::where('job_id', $jobId)
                ->where('job_seeker_id', $jobSeeker->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job already bookmarked'
                ], 409);
            }

            $bookmark = BookmarkedJob::create([
                'job_id' => $jobId,
                'job_seeker_id' => $jobSeeker->id
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Job bookmarked successfully',
                'data' => $bookmark
            ], 201);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Bookmark failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Remove bookmark

    public function removeBookmark($jobId)
    {
        try {

            $user = Auth::user();

            $jobSeeker = JobSeeker::where('user_id', $user->id)->first();

            $bookmark = BookmarkedJob::where('job_id', $jobId)
                ->where('job_seeker_id', $jobSeeker->id)
                ->first();

            if (!$bookmark) {
                return response()->json([
                    'status' => false,
                    'message' => 'Bookmark not found'
                ], 404);
            }

            $bookmark->delete();

            return response()->json([
                'status' => true,
                'message' => 'Bookmark removed successfully'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get bookmarked jobs
    public function getBookmarkedJobs()
    {
        try {

            $user = Auth::user();

            $jobSeeker = JobSeeker::where('user_id', $user->id)->first();

            $bookmarks = BookmarkedJob::where('job_seeker_id', $jobSeeker->id)
                ->with('job')
                ->latest()
                ->get();

            return response()->json([
                'status' => true,
                'total' => $bookmarks->count(),
                'data' => $bookmarks
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch bookmarks',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
