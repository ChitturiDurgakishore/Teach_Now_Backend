<?php

namespace App\Http\Controllers\Api;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\AIService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobSeeker;
use App\Models\JobSeekerCV;
use Illuminate\Support\Facades\Storage;

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
            'job_description' => 'required|string'
        ]);

        return $this->generateCVLogic($request->job_description);
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

            $data = [
                'name' => $jobSeeker->user->name ?? '',
                'email' => $jobSeeker->user->email ?? '',
                'skills' => $jobSeeker->skills->pluck('name'),
                'educations' => $jobSeeker->educations->toArray(),
                'experiences' => $jobSeeker->experiences->toArray()
            ];

            // 🔥 AI GENERATED TEXT
            $aiContent = $this->ai->generateCV($data, $jobDescription);

            // 🔥 Render HTML
            $html = view('cv.template', ['cv' => $data])->render();

            // 🔥 Generate PDF
            $pdf = Pdf::loadHTML($html);

            $fileName = time() . '_cv.pdf';
            $path = "public/media/cv/{$fileName}";
            Storage::put($path, $pdf->output());

            $pdfUrl = str_replace('public/', 'storage/', $path);

            // 🔥 Save DB
            $cv = JobSeekerCV::create([
                'job_seeker_id' => $jobSeeker->id,
                'title' => $jobDescription ? 'Job Specific CV' : 'Base CV',
                'content' => $aiContent,
                'pdf_path' => $pdfUrl
            ]);

            return response()->json([
                'status' => true,
                'message' => 'CV generated successfully',
                'data' => [
                    'cv' => $cv,
                    'pdf_url' => asset($pdfUrl)
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
