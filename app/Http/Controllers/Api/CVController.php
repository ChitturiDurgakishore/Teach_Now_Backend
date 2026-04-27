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
use Spatie\Browsershot\Browsershot;
use App\Models\CVTemplate;
use App\Models\ResumeLimit;
use App\Models\ResumeLimitAdmin;

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
    public function generateBaseCV(Request $request)
    {
        $request->validate([
            'template_id' => 'required|exists:cv_templates,id'
        ]);

        return $this->generateCVLogic(null, $request->template_id);
    }

    /*
    |--------------------------------------------------------------------------
    | 2. JOB SPECIFIC CV
    |--------------------------------------------------------------------------
    */
    public function generateJobCV(Request $request)
    {
        $request->validate([
            'job_id' => 'required|exists:jobs,id',
            'template_id' => 'required|exists:cv_templates,id'
        ]);

        try {

            $job = Job::find($request->job_id);

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            $jobDescription = "
        Job Title: {$job->title}
        Description: {$job->description}
        Skills: {$job->keywords}
        Experience: {$job->experience_required} years
        Location: {$job->location}
        ";

            return $this->generateCVLogic($jobDescription, $request->template_id);
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
    private function generateCVLogic($jobDescription = null, $templateId = null)
    {
        try {

            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            /*
|--------------------------------------------------------------------------
| 🔥 RESUME LIMIT CHECK (ADD HERE)
|--------------------------------------------------------------------------
*/

            $resumeService = app(\App\Services\AIService::class); // since you already put it there

            $limitCheck = $resumeService->checkAndConsume($user->id);

            if (!$limitCheck['status']) {
                return response()->json([
                    'status' => false,
                    'message' => $limitCheck['message'],
                    'limit' => $limitCheck['limit'] ?? null,
                    'used' => $limitCheck['used'] ?? null
                ], 403);
            }

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

            // ✅ DATA
            $data = [
                'profile_photo' => $jobSeeker->profile_photo_url ?? null,
                'name' => $jobSeeker->user->name ?? '',
                'email' => $jobSeeker->user->email ?? '',
                'phone' => $jobSeeker->phone ?? '',
                'location' => $jobSeeker->location ?? '',

                // 🔥 LIMIT CONTENT (IMPORTANT FOR 2 PAGE CV)
                'skills' => $jobSeeker->skills->pluck('name')->take(8)->toArray(),
                'educations' => $jobSeeker->educations->take(3)->toArray(),
                'experiences' => $jobSeeker->experiences->take(4)->toArray(),
            ];

            // ✅ AI CONTENT
            $aiContent = null;
            try {
                $aiContent = $this->ai->generateCV($data, $jobDescription);
            } catch (\Exception $e) {
                $aiContent = null;
            }

            // ✅ TEMPLATE
            $template = \App\Models\CVTemplate::find($templateId);

            if (!$template) {
                return response()->json([
                    'status' => false,
                    'message' => 'Template not found'
                ]);
            }

            // ✅ PARSE TEMPLATE
            $htmlBody = $this->parseTemplate(
                $template->html_template,
                $data,
                $aiContent
            );

            // ✅ FINAL HTML WITH PAGE CONTROL
            $html = "
        <html>
        <head>
            <meta charset='utf-8'>
           <style>
    @page {
        margin: 0; /* Important: Removes default PDF margins */
    }
    body {
        font-family: 'Helvetica', Arial, sans-serif;
        font-size: 12px; /* Slightly smaller for better fit */
        color: #333;
        margin: 0;
        padding: 0;
        line-height: 1.4; /* Tighter line height to save space */
    }
    .page {
        width: 210mm; /* Fixed A4 Width */
        min-height: 297mm; /* Fixed A4 Height */
        padding: 40px; /* Internal breathing room */
        box-sizing: border-box;
        background: #ffffff;
        /* Removed the border as it often triggers a second page break */
    }
    .section {
        margin-bottom: 20px;
        page-break-inside: avoid;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    tr {
        page-break-inside: avoid;
    }
    h2 {
        border-bottom: 1px solid #eee; /* Light line instead of heavy border */
        padding-bottom: 5px;
        margin-bottom: 15px;
        text-transform: uppercase;
        font-size: 16px;
        letter-spacing: 1px;
    }
    p {
        margin: 0 0 10px 0;
        line-height: 1.5;
    }
</style>
        </head>
        <body>

        <div class='page'>
            {$htmlBody}
        </div>

        </body>
        </html>
        ";

            // ✅ PDF
            $pdf = Pdf::loadHTML($html)->setOptions([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true
            ]);

            // 🔥 FORMAT NAME
            $userName = $jobSeeker->user->name ?? 'User';

            // Remove spaces & special chars
            $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $userName);

            // Format date
            $date = now()->format('d-m-Y');

            // Optional: add timestamp to avoid duplicates
            $timestamp = now()->timestamp;

            // Final filename
            $fileName = "{$cleanName}_{$date}_{$timestamp}.pdf";
            $path = "media/cv/{$fileName}";

            Storage::disk('public')->put($path, $pdf->output());

            $pdfPath = "storage/" . $path;

            // ✅ SAVE
            $cv = JobSeekerCV::create([
                'job_seeker_id' => $jobSeeker->id,
                'title' => "{$userName}-{$date}",
                'content' => $aiContent,
                'pdf_path' => $pdfPath
            ]);

            return response()->json([
                'status' => true,
                'message' => 'CV generated successfully',
                'data' => [
                    'cv' => $cv,
                    'pdf_url' => $pdfPath
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


    private function parseTemplate($template, $data, $aiContent = null)
    {
        // 1. SKILLS → 5 PER COLUMN (MATCHES YOUR LOGIC)
        $skills = $data['skills'];
        $chunks = array_chunk($skills, 5);
        $skillsHtml = "<tr>";
        foreach ($chunks as $chunk) {
            $skillsHtml .= "<td width='" . (100 / count($chunks)) . "%' valign='top' style='padding-right:15px;'>
                        <ul style='margin:0; padding-left:15px; list-style-type:square;'>";
            foreach ($chunk as $skill) {
                $formattedSkill = ucwords(strtolower($skill));
                $skillsHtml .= "<li style='margin-bottom:5px;'>{$formattedSkill}</li>";
            }
            $skillsHtml .= "</ul></td>";
        }
        $skillsHtml .= "</tr>";

        // 2. EXPERIENCE (CLEAN BLOCKS FOR COLUMN LAYOUT)
        $expHtml = '';
        foreach ($data['experiences'] as $exp) {
            $end = (!empty($exp['end_date'])) ? $exp['end_date'] : 'Present';

            $expHtml .= "
        <div style='margin-bottom: 20px;'>
            <strong style='font-size: 15px; display: block; color: #1a1a1a;'>{$exp['job_title']}</strong>
            <span style='color: #64748b; font-size: 11px; display: block; margin-bottom: 5px;'>
                {$exp['company_name']} | {$exp['location']} | {$exp['start_date']} - {$end}
            </span>
            <p style='margin: 0; font-size: 12px; color: #444;'>Built impactful educational experiences and fostered student growth.</p>
        </div>";
        }

        // 3. EDUCATION (CLEAN BLOCKS)
        $eduHtml = '';
        foreach ($data['educations'] as $edu) {
            $eduHtml .= "
        <div style='margin-bottom: 15px;'>
            <strong style='font-size: 15px; display: block; color: #1a1a1a;'>{$edu['degree']}</strong>
            <span style='color: #64748b; font-size: 11px; display: block;'>
                {$edu['institution']} ({$edu['start_year']} - {$edu['end_year']})
            </span>
        </div>";
        }

        // 4. ACHIEVEMENTS (IF APPLICABLE)
        // You can handle achievements similar to skills but as a single list
        $achievementsHtml = "<ul style='margin:0; padding-left:15px;'>
                            <li style='margin-bottom:5px;'>Achieved an outstanding 95% aggregate in B.Tech.</li>
                            <li style='margin-bottom:5px;'>Successfully guided numerous students as an English Teacher[cite: 11].</li>
                         </ul>";

        // 5. REPLACE VARIABLES
        $replacements = [
            '{{profile_photo}}' => $data['profile_photo'] ?? '',
            '{{name}}'          => $data['name'],
            '{{title}}'         => $data['title'] ?? 'Professional',
            '{{email}}'         => $data['email'],
            '{{phone}}'         => $data['phone'],
            '{{location}}'      => $data['location'],
            '{{summary}}'       => nl2br($aiContent ?? ''),
            '{{skills}}'        => $skillsHtml,
            '{{experience}}'    => $expHtml,
            '{{education}}'     => $eduHtml,
            '{{achievements}}'  => $achievementsHtml
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    public function getActiveTemplates()
    {
        $baseUrl = rtrim(config('cv.base_url'), '/');

        $templates = CVTemplate::where('is_active', true)
            ->get()
            ->map(function ($template) use ($baseUrl) {

                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'preview_image' => $template->preview_image
                        ? $baseUrl . '/' . $template->preview_image
                        : null
                ];
            });
        //data for sending limit and user usage
        $user = auth()->user();
        $month = now()->format('Y-m');
        $limit = ResumeLimitAdmin::value('limit') ?? 5;
        $usage = ResumeLimit::where('user_id', $user->id)
            ->where('month', $month)
            ->first();
        $used = $usage->count ?? 0;
        return response()->json([
            'status' => true,
            'data' => $templates,
            'resume_limit' => [
                'limit' => $limit,
                'used' => $used,
                'remaining' => max(0, $limit - $used)
            ]
        ]);
    }
}
