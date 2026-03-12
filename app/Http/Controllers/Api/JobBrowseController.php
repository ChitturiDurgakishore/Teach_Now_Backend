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

class JobBrowseController extends Controller
{

    //Open route for job seekers to browse approved and open jobs
    public function browseJobs()
    {
        try {

            $jobs = Job::where('status', 'approved')
                ->where('job_status', 'open')
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

    //Open route for job seekers to view details of a specific job

    public function viewJob($id)
    {
        try {

            $job = Job::where('id', $id)
                ->where('status', 'approved')
                ->first();

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

    // Job Application
    public function applyJob($jobId)
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

            $resume = Resume::where('job_seeker_id', $jobSeeker->id)
                ->where('is_default', true)
                ->first();

            if (!$resume) {
                return response()->json([
                    'status' => false,
                    'message' => 'Default resume not found'
                ], 404);
            }

            $application = JobApplication::create([
                'job_id' => $jobId,
                'job_seeker_id' => $jobSeeker->id,
                'resume_id' => $resume->id,
                'status' => 'applied'
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Job applied successfully',
                'data' => $application
            ], 201);
        } catch (\Exception $e) {

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
