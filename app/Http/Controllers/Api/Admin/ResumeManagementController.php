<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Resume;
use App\Models\JobSeekerCV;
use Illuminate\Support\Facades\Storage;

class ResumeManagementController extends Controller
{
    public function getAllResumes(Request $request)
    {
        try {

            $perPage = $request->get('per_page', 10);

            /*
        |--------------------------------------------------------------------------
        | 🔥 RESUMES (UPLOADED FILES)
        |--------------------------------------------------------------------------
        */
            $resumes = Resume::with([
                'jobSeeker.user:id,name,email'
            ])
                ->select('id', 'job_seeker_id', 'file_name', 'file_url', 'created_at')
                ->latest()
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'type' => 'resume',
                        'title' => $item->file_name ?? 'Resume',
                        'file_url' => $item->file_url,
                        'created_at' => $item->created_at,
                        'job_seeker' => [
                            'id' => $item->jobSeeker->id ?? null,
                            'name' => $item->jobSeeker->user->name ?? null,
                            'email' => $item->jobSeeker->user->email ?? null,
                        ]
                    ];
                });

            /*
        |--------------------------------------------------------------------------
        | 🔥 GENERATED RESUMES (CVs)
        |--------------------------------------------------------------------------
        */
            $generatedResumes = JobSeekerCV::with([
                'jobSeeker.user:id,name,email'
            ])
                ->select('id', 'job_seeker_id', 'title', 'pdf_path', 'created_at')
                ->latest()
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'type' => 'generated_resume', // 🔥 renamed
                        'title' => $item->title ?? 'Generated Resume',
                        'file_url' => $item->pdf_path ? asset($item->pdf_path) : null,
                        'created_at' => $item->created_at,
                        'job_seeker' => [
                            'id' => $item->jobSeeker->id ?? null,
                            'name' => $item->jobSeeker->user->name ?? null,
                            'email' => $item->jobSeeker->user->email ?? null,
                        ]
                    ];
                });

            /*
        |--------------------------------------------------------------------------
        | 🔥 MERGE + SORT + PAGINATE (MANUAL)
        |--------------------------------------------------------------------------
        */
            $merged = $resumes
                ->concat($generatedResumes)
                ->sortByDesc('created_at')
                ->values();

            // Manual pagination
            $page = $request->get('page', 1);
            $pagedData = $merged->slice(($page - 1) * $perPage, $perPage)->values();

            return response()->json([
                'status' => true,
                'total' => $merged->count(),
                'current_page' => (int)$page,
                'per_page' => (int)$perPage,
                'last_page' => ceil($merged->count() / $perPage),
                'data' => $pagedData
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch resumes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteResume($id, Request $request)
    {
        try {

            $type = $request->input('type'); // resume | generated_resume

            if (!$type || !in_array($type, ['resume', 'generated_resume'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid type. Must be resume or generated_resume'
                ], 422);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 DELETE NORMAL RESUME
        |--------------------------------------------------------------------------
        */
            if ($type === 'resume') {

                $resume = Resume::find($id);

                if (!$resume) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Resume not found'
                    ], 404);
                }

                // Optional: delete file from storage
                if ($resume->file_url && Storage::exists($resume->file_url)) {
                    Storage::delete($resume->file_url);
                }

                $resume->delete();
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 DELETE GENERATED RESUME (CV)
        |--------------------------------------------------------------------------
        */
            if ($type === 'generated_resume') {

                $cv = JobSeekerCV::find($id);

                if (!$cv) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Generated resume not found'
                    ], 404);
                }

                // Optional: delete PDF
                if ($cv->pdf_path && Storage::exists($cv->pdf_path)) {
                    Storage::delete($cv->pdf_path);
                }

                $cv->delete();
            }

            return response()->json([
                'status' => true,
                'message' => 'Resume deleted successfully'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete resume',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
