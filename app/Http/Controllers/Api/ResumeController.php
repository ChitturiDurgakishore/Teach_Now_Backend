<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Resume;
use App\Models\JobSeeker;
use Illuminate\Support\Facades\Auth;


class ResumeController extends Controller
{
    public function uploadResume(Request $request)
    {
        try {

            $request->validate([
                'file_name' => 'required|string',
                'file_url' => 'required|string'
            ]);

            $user = Auth::user();

            $jobSeeker = JobSeeker::where('user_id', $user->id)->first();

            if (!$jobSeeker) {
                return response()->json([
                    'status' => false,
                    'message' => 'Profile not found'
                ], 404);
            }

            $resume = Resume::create([
                'job_seeker_id' => $jobSeeker->id,
                'file_name' => $request->file_name,
                'file_url' => $request->file_url
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Resume uploaded successfully',
                'data' => $resume
            ], 201);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Upload failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get all resumes for the authenticated job seeker

    public function getResumes()
    {
        try {

            $user = Auth::user();

            $jobSeeker = JobSeeker::where('user_id', $user->id)->first();

            $resumes = Resume::where('job_seeker_id', $jobSeeker->id)->get();

            return response()->json([
                'status' => true,
                'total' => $resumes->count(),
                'data' => $resumes
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch resumes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Default resume for job applications
    public function setDefaultResume($id)
    {
        try {

            $resume = Resume::find($id);

            if (!$resume) {
                return response()->json([
                    'status' => false,
                    'message' => 'Resume not found'
                ], 404);
            }

            Resume::where('job_seeker_id', $resume->job_seeker_id)
                ->update(['is_default' => false]);

            $resume->update([
                'is_default' => true
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Default resume updated'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Delete a resume

    public function deleteResume($id)
    {
        try {

            $resume = Resume::find($id);

            if (!$resume) {
                return response()->json([
                    'status' => false,
                    'message' => 'Resume not found'
                ], 404);
            }

            $resume->delete();

            return response()->json([
                'status' => true,
                'message' => 'Resume deleted successfully'
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
