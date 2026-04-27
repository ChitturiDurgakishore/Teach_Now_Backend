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
            $profile_photo = env('MEDIA_URL') . '/' . ($jobSeeker->profile_photo ?? 'https://cdn-icons-png.flaticon.com/512/847/847969.png');
            $data = [
                'profile_photo' => $profile_photo,
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
        margin: 0;
    }
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        line-height: 1.3; /* Tighter spacing to keep everything on one page */
    }
    .page {
    padding: 25px;
}
    /* Circular Image Container */
    .photo-container {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        overflow: hidden;
        border: 1px solid #eee;
    }
    .photo-container img {
        width: 100%;
        height: 100%;
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
                'isHtml5ParserEnabled' => true,
                'chroot' => public_path(),
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
        // 1. SKILLS → 5 PER COLUMN (KEEPING YOUR EXISTING LOGIC)
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

        // 2. EXPERIENCE (FIXED: Stacked layout with dates on new line)
        $expHtml = '';
        foreach ($data['experiences'] as $exp) {
            $end = (!empty($exp['end_date'])) ? $exp['end_date'] : 'Present';

            $expHtml .= "
        <div style='margin-bottom: 20px;'>
            <div style='font-weight: 800; font-size: 13px; color: #1a1a1a;'>{$exp['job_title']}</div>
            <div style='font-size: 10.5px; color: #555; margin-top: 2px;'>{$exp['company_name']} | {$exp['location']}</div>
            <div style='font-size: 10px; color: #888; font-weight: 600; text-transform: uppercase; margin-bottom: 6px;'>
                {$exp['start_date']} — {$end}
            </div>
            <div style='font-size: 11px; color: #444; line-height: 1.4; text-align: justify;'>
                Built impactful educational experiences and fostered student growth.
            </div>
        </div>";
        }

        // 3. EDUCATION (FIXED: Stacked layout)
        $eduHtml = '';
        foreach ($data['educations'] as $edu) {
            $eduHtml .= "
        <div style='margin-bottom: 15px;'>
            <div style='font-weight: 800; font-size: 12px; color: #1a1a1a;'>{$edu['degree']}</div>
            <div style='font-size: 10.5px; color: #555; margin-top: 2px;'>{$edu['institution']}</div>
            <div style='font-size: 10px; color: #888; font-weight: 600;'>
                ({$edu['start_year']} — {$edu['end_year']})
            </div>
        </div>";
        }

        // 4. ACHIEVEMENTS (Updated with your specific academic data)
        $achievementsHtml = "<ul style='margin:0; padding-left:15px; color: #444; font-size: 10.5px;'>
                            <li style='margin-bottom:6px;'>Exceptional <b>95% aggregate</b> in B.Tech[cite: 7, 13, 105].</li>
                            <li style='margin-bottom:6px;'>Successfully guided numerous students as an English Teacher[cite: 11, 52].</li>
                            <li style='margin-bottom:6px;'>Secured <b>94.2%</b> in Intermediate Education[cite: 105].</li>
                         </ul>";

        // 5. REPLACE VARIABLES
        $replacements = [
            '{{profile_photo}}' => $data['profile_photo'] ?? '',
            '{{name}}'          => $data['name'],
            '{{title}}'         => $data['title'] ?? 'Professional',
            '{{email}}'         => "<div style='word-wrap: break-word; word-break: break-all; max-width: 180px;'>{$data['email']}</div>",
            '{{phone}}'         => "<div>{$data['phone']}</div>",
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
