<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Resume;
use App\Models\JobSeeker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ResumeController extends Controller
{

    // Upload function for resume upload

    public function uploadFile($file, $folder)
    {
        $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

        $path = $file->storeAs("public/media/$folder", $filename);

        return str_replace('public/', 'storage/', $path);
    }


    public function uploadResume(Request $request)
    {
        try {

            $request->validate([
                'file' => 'required|file|mimes:pdf,doc,docx|max:2048' // 2MB max
            ]);

            $user = Auth::user();

            $jobSeeker = JobSeeker::where('user_id', $user->id)->first();

            if (!$jobSeeker) {
                return response()->json([
                    'status' => false,
                    'message' => 'Profile not found'
                ], 404);
            }

            // Upload file using your common function
            $filePath = $this->uploadFile($request->file('file'), 'resumes');

            $resume = Resume::create([
                'job_seeker_id' => $jobSeeker->id,
                'file_name' => $request->file('file')->getClientOriginalName(),
                'file_url' => $filePath
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

            // 🔥 delete file from storage
            if ($resume->file_url) {
                Storage::delete(str_replace('storage/', 'public/', $resume->file_url));
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
