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
use Illuminate\Support\Facades\Log;

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

            Log::info('🚀 CV Generation Started', [
                'template_id' => $templateId,
                'job_description' => $jobDescription
            ]);

            /*
        |--------------------------------------------------------------------------
        | 🔐 AUTH CHECK
        |--------------------------------------------------------------------------
        */
            $user = auth()->user();

            if (!$user) {
                Log::error('❌ User not authenticated');
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            Log::info('✅ User Authenticated', ['user_id' => $user->id]);

            /*
        |--------------------------------------------------------------------------
        | 🔥 LIMIT CHECK
        |--------------------------------------------------------------------------
        */
            $resumeService = app(\App\Services\AIService::class);
            $limitCheck = $resumeService->checkAndConsume($user->id);

            Log::info('📊 Limit Check Result', $limitCheck ?? []);

            if (!$limitCheck['status']) {
                Log::warning('⚠️ Limit exceeded');
                return response()->json([
                    'status' => false,
                    'message' => $limitCheck['message'],
                    'limit' => $limitCheck['limit'] ?? null,
                    'used' => $limitCheck['used'] ?? null
                ], 403);
            }

            /*
        |--------------------------------------------------------------------------
        | 👤 FETCH PROFILE
        |--------------------------------------------------------------------------
        */
            $jobSeeker = JobSeeker::with([
                'educations',
                'experiences',
                'skills',
                'user'
            ])->where('user_id', $user->id)->first();

            if (!$jobSeeker) {
                Log::error('❌ JobSeeker not found');
                return response()->json([
                    'status' => false,
                    'message' => 'Profile not found'
                ], 404);
            }

            Log::info('✅ JobSeeker Loaded', [
                'skills_count' => $jobSeeker->skills->count(),
                'edu_count' => $jobSeeker->educations->count(),
                'exp_count' => $jobSeeker->experiences->count()
            ]);

            /*
        |--------------------------------------------------------------------------
        | 📦 DATA PREP
        |--------------------------------------------------------------------------
        */
            $profile_photo = env('MEDIA_URL') . '/' . ($jobSeeker->profile_photo ?? 'defaults/user.png');

            $data = [
                'profile_photo' => $profile_photo,
                'name' => $jobSeeker->user->name ?? '',
                'email' => $jobSeeker->user->email ?? '',
                'phone' => $jobSeeker->phone ?? '',
                'location' => $jobSeeker->location ?? '',
                'skills' => $jobSeeker->skills->pluck('name')->take(8)->toArray(),
                'educations' => $jobSeeker->educations->take(3)->toArray(),
                'experiences' => $jobSeeker->experiences->take(4)->toArray(),
            ];

            Log::info('📦 Prepared Data', $data);

            /*
        |--------------------------------------------------------------------------
        | 🤖 AI GENERATION
        |--------------------------------------------------------------------------
        */
            $aiContent = null;

            try {
                $aiContent = $this->ai->generateCV($data, $jobDescription);
                Log::info('🤖 AI Response', [
                    'length' => strlen($aiContent ?? ''),
                    'preview' => substr($aiContent ?? '', 0, 200)
                ]);
            } catch (\Exception $e) {
                Log::error('❌ AI Failed', [
                    'error' => $e->getMessage()
                ]);
                $aiContent = null;
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 FALLBACK
        |--------------------------------------------------------------------------
        */
            if (!empty($aiContent)) {
                $data['summary'] = $aiContent;
                Log::info('✅ Using AI summary');
            } else {
                $data['summary'] = $jobSeeker->bio
                    ?? "Dedicated professional with strong skills in " . implode(', ', array_slice($data['skills'], 0, 3)) . ".";
                Log::warning('⚠️ Using fallback summary');
            }

            /*
        |--------------------------------------------------------------------------
        | 📄 TEMPLATE
        |--------------------------------------------------------------------------
        */
            $template = \App\Models\CVTemplate::find($templateId);

            if (!$template) {
                Log::error('❌ Template not found', ['template_id' => $templateId]);
                return response()->json([
                    'status' => false,
                    'message' => 'Template not found'
                ]);
            }

            Log::info('✅ Template Loaded');

            /*
        |--------------------------------------------------------------------------
        | 🧩 PARSE TEMPLATE
        |--------------------------------------------------------------------------
        */
            $htmlBody = $this->parseTemplate(
                $template->html_template,
                $data,
                $aiContent
            );

            Log::info('🧩 HTML Parsed', [
                'length' => strlen($htmlBody)
            ]);

            /*
        |--------------------------------------------------------------------------
        | 🌐 FINAL HTML
        |--------------------------------------------------------------------------
        */
            $html = "
        <html>
        <head>
            <meta charset='utf-8'>
        </head>
        <body>
            {$htmlBody}
        </body>
        </html>
        ";

            Log::info('🌐 Final HTML Ready');

            /*
        |--------------------------------------------------------------------------
        | 🧾 PDF GENERATION
        |--------------------------------------------------------------------------
        */
            try {
                $pdf = Pdf::loadHTML($html)->setOptions([
                    'isRemoteEnabled' => true,
                    'isHtml5ParserEnabled' => true,
                    'chroot' => public_path(),
                ]);

                Log::info('✅ PDF Generated');
            } catch (\Exception $e) {
                Log::error('❌ PDF Generation Failed', [
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }

            /*
        |--------------------------------------------------------------------------
        | 💾 SAVE FILE
        |--------------------------------------------------------------------------
        */
            $userName = $jobSeeker->user->name ?? 'User';
            $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $userName);
            $date = now()->format('d-m-Y');
            $timestamp = now()->timestamp;

            $fileName = "{$cleanName}_{$date}_{$timestamp}.pdf";
            $path = "media/cv/{$fileName}";

            try {
                Storage::disk('public')->put($path, $pdf->output());
                Log::info('💾 PDF Stored', ['path' => $path]);
            } catch (\Exception $e) {
                Log::error('❌ Storage Failed', [
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }

            $pdfPath = "storage/" . $path;

            /*
        |--------------------------------------------------------------------------
        | 🗄 SAVE DB
        |--------------------------------------------------------------------------
        */
            $cv = JobSeekerCV::create([
                'job_seeker_id' => $jobSeeker->id,
                'title' => "{$userName}-{$date}",
                'content' => $data['summary'],
                'pdf_path' => $pdfPath
            ]);

            Log::info('🗄 CV Saved', ['cv_id' => $cv->id]);

            /*
        |--------------------------------------------------------------------------
        | ✅ SUCCESS
        |--------------------------------------------------------------------------
        */
            return response()->json([
                'status' => true,
                'message' => 'CV generated successfully',
                'data' => [
                    'cv' => $cv,
                    'pdf_url' => $pdfPath
                ]
            ]);
        } catch (\Exception $e) {

            Log::error('🔥 CV Generation FAILED', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'CV generation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }




private function parseTemplate($template, $data, $aiContent = null)
{
    Log::info('🧩 parseTemplate START');

    /*
    |--------------------------------------------------------------------------
    | 📦 INPUT CHECK
    |--------------------------------------------------------------------------
    */
    Log::info('📦 Incoming Data', [
        'has_template' => !empty($template),
        'skills_count' => count($data['skills'] ?? []),
        'exp_count' => count($data['experiences'] ?? []),
        'edu_count' => count($data['educations'] ?? []),
        'has_summary' => !empty($data['summary']),
    ]);

    /*
    |--------------------------------------------------------------------------
    | 🧠 SKILLS
    |--------------------------------------------------------------------------
    */
    $skills = $data['skills'] ?? [];

    if (empty($skills)) {
        Log::warning('⚠️ No skills found');
    }

    $chunks = array_chunk($skills, 5);

    Log::info('🧠 Skills chunked', [
        'chunks_count' => count($chunks)
    ]);

    $skillsHtml = "<tr>";

    foreach ($chunks as $index => $chunk) {

        Log::info("➡️ Processing skill chunk {$index}", $chunk);

        $skillsHtml .= "<td width='" . (100 / max(count($chunks),1)) . "%' valign='top' style='padding-right:15px;'>
                        <ul style='margin:0; padding-left:15px; list-style-type:square;'>";

        foreach ($chunk as $skill) {
            $formattedSkill = ucwords(strtolower($skill));
            $skillsHtml .= "<li style='margin-bottom:5px;'>{$formattedSkill}</li>";
        }

        $skillsHtml .= "</ul></td>";
    }

    $skillsHtml .= "</tr>";

    Log::info('✅ Skills HTML generated', [
        'length' => strlen($skillsHtml)
    ]);

    /*
    |--------------------------------------------------------------------------
    | 💼 EXPERIENCE
    |--------------------------------------------------------------------------
    */
    $expHtml = '';

    foreach (($data['experiences'] ?? []) as $i => $exp) {

        Log::info("💼 Experience {$i}", $exp);

        $end = (!empty($exp['end_date'])) ? $exp['end_date'] : 'Present';

        $expHtml .= "
        <div style='margin-bottom: 20px;'>
            <div style='font-weight: 800; font-size: 13px; color: #1a1a1a;'>{$exp['job_title']}</div>
            <div style='font-size: 10.5px; color: #555; margin-top: 2px;'>{$exp['company_name']} | {$exp['location']}</div>
            <div style='font-size: 10px; color: #888; font-weight: 600; margin-bottom: 6px;'>
                {$exp['start_date']} — {$end}
            </div>
            <div style='font-size: 11px; color: #444; line-height: 1.4;'>
                {$exp['description'] ?? 'Worked on key responsibilities.'}
            </div>
        </div>";
    }

    Log::info('✅ Experience HTML generated', [
        'length' => strlen($expHtml)
    ]);

    /*
    |--------------------------------------------------------------------------
    | 🎓 EDUCATION
    |--------------------------------------------------------------------------
    */
    $eduHtml = '';

    foreach (($data['educations'] ?? []) as $i => $edu) {

        Log::info("🎓 Education {$i}", $edu);

        $eduHtml .= "
        <div style='margin-bottom: 15px;'>
            <div style='font-weight: 800; font-size: 12px; color: #1a1a1a;'>{$edu['degree']}</div>
            <div style='font-size: 10.5px; color: #555; margin-top: 2px;'>{$edu['institution']}</div>
            <div style='font-size: 10px; color: #888;'>
                ({$edu['start_year']} — {$edu['end_year']})
            </div>
        </div>";
    }

    Log::info('✅ Education HTML generated', [
        'length' => strlen($eduHtml)
    ]);

    /*
    |--------------------------------------------------------------------------
    | 🏆 ACHIEVEMENTS (STATIC / OPTIONAL)
    |--------------------------------------------------------------------------
    */
    $achievementsHtml = "
        <ul style='margin:0; padding-left:15px; color: #444; font-size: 10.5px;'>
            <li>Strong academic performance</li>
            <li>Effective teaching and mentoring</li>
        </ul>";

    /*
    |--------------------------------------------------------------------------
    | 🔄 REPLACEMENTS
    |--------------------------------------------------------------------------
    */
    $replacements = [
        '{{profile_photo}}' => $data['profile_photo'] ?? '',
        '{{name}}' => $data['name'] ?? '',
        '{{title}}' => $data['title'] ?? 'Professional',
        '{{email}}' => "<div style='word-break: break-all;'>{$data['email']}</div>",
        '{{phone}}' => "<div>{$data['phone']}</div>",
        '{{location}}' => $data['location'] ?? '',
        '{{summary}}' => nl2br($data['summary'] ?? ''),
        '{{skills}}' => $skillsHtml,
        '{{experience}}' => $expHtml,
        '{{education}}' => $eduHtml,
        '{{achievements}}' => $achievementsHtml
    ];

    Log::info('🔄 Replacements prepared', [
        'keys' => array_keys($replacements)
    ]);

    /*
    |--------------------------------------------------------------------------
    | 🧾 FINAL HTML
    |--------------------------------------------------------------------------
    */
    $finalHtml = str_replace(
        array_keys($replacements),
        array_values($replacements),
        $template
    );

    Log::info('🧾 Final HTML built', [
        'length' => strlen($finalHtml),
        'preview' => substr($finalHtml, 0, 300)
    ]);

    Log::info('🧩 parseTemplate END');

    return $finalHtml;
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
