<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobSeeker;
use App\Models\JobSeekerCV;
use App\Models\Job;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\AIService;

class CVController extends Controller
{
    protected $ai;

    public function __construct(AIService $ai)
    {
        $this->ai = $ai;
    }

    /*
    |--------------------------------------------------------------------------
    | 1. BASE CV (PROFILE ONLY)
    |--------------------------------------------------------------------------
    */
    public function generateBaseCV()
    {
        return $this->generateCVLogic();
    }

    /*
    |--------------------------------------------------------------------------
    | 2. JOB SPECIFIC CV
    |--------------------------------------------------------------------------
    */
    public function generateJobCV(Request $request)
    {
        $request->validate([
            'job_id' => 'required|exists:jobs,id'
        ]);

        try {

            $job = Job::find($request->job_id);

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            // 🔥 Build job description
            $jobDescription = "
            Job Title: {$job->title}
            Description: {$job->description}
            Skills: {$job->keywords}
            Experience: {$job->experience_required} years
            Location: {$job->location}
            ";

            return $this->generateCVLogic($jobDescription);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'CV generation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | COMMON LOGIC
    |--------------------------------------------------------------------------
    */
    private function generateCVLogic($jobDescription = null)
    {
        try {

            $user = auth()->user();

            $jobSeeker = JobSeeker::with([
                'educations',
                'experiences',
                'skills',
                'user'
            ])->where('user_id', $user->id)->first();

            if (!$jobSeeker) {
                return response()->json([
                    'status' => false,
                    'message' => 'Profile not found'
                ], 404);
            }

            // 🔥 Prepare data
            $data = [
                'name' => $jobSeeker->user->name ?? '',
                'email' => $jobSeeker->user->email ?? '',
                'skills' => $jobSeeker->skills->pluck('name'),
                'educations' => $jobSeeker->educations->toArray(),
                'experiences' => $jobSeeker->experiences->toArray()
            ];

            // 🔥 AI GENERATED CONTENT
            $aiContent = $this->ai->generateCV($data, $jobDescription);

            // 🔥 Use AI HTML OR fallback
            if ($aiContent) {
                $html = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; }
                        h1, h2 { color: #333; }
                        ul { padding-left: 20px; }
                    </style>
                </head>
                <body>
                    {$aiContent}
                </body>
                </html>
                ";
            } else {
                // fallback (no AI key case)
                $html = view('cv.template', ['cv' => $data])->render();
            }

            // 🔥 Generate PDF
            $pdf = Pdf::loadHTML($html);

            $fileName = time() . '_cv.pdf';
            $path = "media/cv/{$fileName}";

            // IMPORTANT → store in public disk
            Storage::put( $path, $pdf->output());

            // 🔥 ONLY RELATIVE PATH (BEST PRACTICE)
            $pdfPath = "storage/" . $path;

            // 🔥 Save DB
            $cv = JobSeekerCV::create([
                'job_seeker_id' => $jobSeeker->id,
                'title' => $jobDescription ? 'Job Specific CV' : 'Base CV',
                'content' => $aiContent,
                'pdf_path' => $pdfPath
            ]);

            return response()->json([
                'status' => true,
                'message' => 'CV generated successfully',
                'data' => [
                    'cv' => $cv,
                    'pdf_url' => $pdfPath // ✅ clean
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'CV generation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
