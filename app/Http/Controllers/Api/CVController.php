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
                body {
                    font-family: Arial, sans-serif;
                    font-size: 14px;
                    color: #333;
                }

                .page {
                    border: 2px solid #2c3e50;
                    padding: 25px;
                    margin-bottom: 20px;
                }

                .section {
                    margin-bottom: 25px;
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
                    border-bottom: 2px solid #2c3e50;
                    padding-bottom: 5px;
                    margin-bottom: 10px;
                }

                p {
                    line-height: 1.8;
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

            $fileName = time() . '_cv.pdf';
            $path = "media/cv/{$fileName}";

            Storage::put($path, $pdf->output());

            $pdfPath = "storage/" . $path;

            // ✅ SAVE
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
        // ✅ SKILLS → 5 PER COLUMN
        $skills = $data['skills'];
        $chunks = array_chunk($skills, 5);

        $skillsHtml = "<tr>";
        foreach ($chunks as $chunk) {

            $skillsHtml .= "<td width='" . (100 / count($chunks)) . "%' valign='top' style='padding-right:15px;'>
                            <ul style='margin:0; padding-left:15px; list-style-type:square;'>";

            foreach ($chunk as $skill) {
                $formattedSkill = ucwords(strtolower($skill)); // 🔥 FIX
                $skillsHtml .= "<li>{$formattedSkill}</li>";
            }

            $skillsHtml .= "</ul></td>";
        }
        $skillsHtml .= "</tr>";


        // ✅ EXPERIENCE (STRICT PRESENT FIX)
        $expRows = '';
        foreach ($data['experiences'] as $exp) {

            $end = (!empty($exp['end_date']))
                ? $exp['end_date']
                : 'Present';

            $expRows .= "
        <tr>
            <td style='padding:10px 0; border-bottom:1px solid #e2e8f0;'>
                <strong>{$exp['job_title']}</strong> - {$exp['company_name']}<br>
                <span style='color:#64748b; font-size:10px;'>
                    {$exp['location']} | {$exp['start_date']} - {$end}
                </span>
            </td>
        </tr>";
        }

        $expHtml = "<tbody>{$expRows}</tbody>";


        // ✅ EDUCATION
        $eduRows = '';
        foreach ($data['educations'] as $edu) {

            $eduRows .= "
        <tr>
            <td style='padding:10px 0; border-bottom:1px solid #e2e8f0;'>
                <strong>{$edu['degree']}</strong><br>
                <span style='color:#64748b; font-size:10px;'>
                    {$edu['institution']} ({$edu['start_year']} - {$edu['end_year']})
                </span>
            </td>
        </tr>";
        }

        $eduHtml = "<tbody>{$eduRows}</tbody>";


        // ✅ REPLACE VARIABLES
        $template = str_replace('{{name}}', $data['name'], $template);
        $template = str_replace('{{email}}', $data['email'], $template);
        $template = str_replace('{{phone}}', $data['phone'], $template);
        $template = str_replace('{{location}}', $data['location'], $template);
        $template = str_replace('{{skills}}', $skillsHtml, $template);
        $template = str_replace('{{experience}}', $expHtml, $template);
        $template = str_replace('{{education}}', $eduHtml, $template);

        // ✅ SUMMARY (SAFE FORMAT)
        $template = str_replace('{{summary}}', nl2br($aiContent ?? ''), $template);

        return $template;
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

        return response()->json([
            'status' => true,
            'data' => $templates
        ]);
    }
}
