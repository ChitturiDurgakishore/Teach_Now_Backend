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

            // 🔥 Pagination controls
            $resumePerPage = $request->get('resume_per_page', 10);
            $cvPerPage = $request->get('cv_per_page', 10);

            $resumePage = $request->get('resume_page', 1);
            $cvPage = $request->get('cv_page', 1);

            $search = $request->get('search');

            /*
        |--------------------------------------------------------------------------
        | 🔥 RESUMES (UPLOADS) + SEARCH
        |--------------------------------------------------------------------------
        */

            $resumeQuery = Resume::with([
                'jobSeeker.user:id,name,email'
            ]);

            if ($search) {
                $resumeQuery->where(function ($q) use ($search) {
                    $q->where('file_name', 'like', "%$search%")
                        ->orWhere('name', 'like', "%$search%")
                        ->orWhereHas('jobSeeker.user', function ($q2) use ($search) {
                            $q2->where('name', 'like', "%$search%")
                                ->orWhere('email', 'like', "%$search%");
                        });
                });
            }

            $resumes = $resumeQuery
                ->latest()
                ->paginate($resumePerPage, ['*'], 'resume_page', $resumePage);

            $resumesData = collect($resumes->items())->map(function ($item) {
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
        | 🔥 GENERATED RESUMES (CV) + SEARCH
        |--------------------------------------------------------------------------
        */

            $generatedQuery = JobSeekerCV::with([
                'jobSeeker.user:id,name,email'
            ]);

            if ($search) {
                $generatedQuery->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%$search%")
                        ->orWhereHas('jobSeeker.user', function ($q2) use ($search) {
                            $q2->where('name', 'like', "%$search%")
                                ->orWhere('email', 'like', "%$search%");
                        });
                });
            }

            $generated = $generatedQuery
                ->latest()
                ->paginate($cvPerPage, ['*'], 'cv_page', $cvPage);

            $generatedData = collect($generated->items())->map(function ($item) {
                return [
                    'id' => $item->id,
                    'type' => 'generated_resume',
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
        | ✅ FINAL RESPONSE
        |--------------------------------------------------------------------------
        */

            return response()->json([
                'status' => true,

                'resumes' => [
                    'total' => $resumes->total(),
                    'current_page' => $resumes->currentPage(),
                    'last_page' => $resumes->lastPage(),
                    'per_page' => $resumes->perPage(),
                    'next_page_url' => $resumes->nextPageUrl(),
                    'prev_page_url' => $resumes->previousPageUrl(),
                    'has_more_pages' => $resumes->hasMorePages(),
                    'data' => $resumesData
                ],

                'generated_resumes' => [
                    'total' => $generated->total(),
                    'current_page' => $generated->currentPage(),
                    'last_page' => $generated->lastPage(),
                    'per_page' => $generated->perPage(),
                    'next_page_url' => $generated->nextPageUrl(),
                    'prev_page_url' => $generated->previousPageUrl(),
                    'has_more_pages' => $generated->hasMorePages(),
                    'data' => $generatedData
                ]

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
