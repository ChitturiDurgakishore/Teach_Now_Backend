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
                'experience_years' => $jobSeeker->experience_years ?? '',
                'bio' => $jobSeeker->bio ?? '',

                'skills' => $jobSeeker->skills->pluck('name')->toArray(),
                'educations' => $jobSeeker->educations->toArray(),
                'experiences' => $jobSeeker->experiences->toArray()
            ];

            // ✅ AI CONTENT (ONLY TEXT)
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

            // ✅ FINAL HTML (SIMPLE + STABLE)
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
                h2, h3 {
                    margin-bottom: 5px;
                }
                ul {
                    padding-left: 18px;
                }
                table {
                    border-collapse: collapse;
                }
            </style>
        </head>
        <body>
            {$htmlBody}
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
        // ✅ SKILLS
        $skillsHtml = '';
        foreach ($data['skills'] as $skill) {
            $skillsHtml .= "<li>{$skill}</li>";
        }

        // ✅ EXPERIENCE (FULL TABLE STRUCTURE)
        $expRows = '';
        foreach ($data['experiences'] as $exp) {

            $company = $exp['company_name'] ?? '';
            $role = $exp['job_title'] ?? '';
            $location = $exp['location'] ?? '';
            $start = $exp['start_date'] ?? '';
            $end = $exp['end_date'] ?? 'Present';

            $expRows .= "
        <tr>
            <td style='padding:6px; border-bottom:1px solid #eee;'>
                <strong>{$role}</strong> - {$company}<br>
                <small>{$location} | {$start} - {$end}</small>
            </td>
        </tr>";
        }

        $expHtml = "<tbody>{$expRows}</tbody>";

        // ✅ EDUCATION (FULL TABLE STRUCTURE)
        $eduRows = '';
        foreach ($data['educations'] as $edu) {

            $degree = $edu['degree'] ?? '';
            $institution = $edu['institution'] ?? '';
            $start = $edu['start_year'] ?? '';
            $end = $edu['end_year'] ?? '';

            $eduRows .= "
        <tr>
            <td style='padding:6px; border-bottom:1px solid #eee;'>
                <strong>{$degree}</strong><br>
                {$institution} ({$start} - {$end})
            </td>
        </tr>";
        }

        $eduHtml = "<tbody>{$eduRows}</tbody>";

        // ✅ REPLACE VALUES
        $template = str_replace('{{name}}', $data['name'], $template);
        $template = str_replace('{{email}}', $data['email'], $template);
        $template = str_replace('{{skills}}', $skillsHtml, $template);
        $template = str_replace('{{experience}}', $expHtml, $template);
        $template = str_replace('{{education}}', $eduHtml, $template);
        $template = str_replace('{{photo}}', $data['photo'], $template);
        $template = str_replace('{{phone}}', $data['phone'], $template);
        $template = str_replace('{{location}}', $data['location'], $template);
        // ✅ AI SUMMARY (SAFE TEXT)
        $template = str_replace('{{summary}}', nl2br($aiContent ?? ''), $template);

        return $template;
    }
}
