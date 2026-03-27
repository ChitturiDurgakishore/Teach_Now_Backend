<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CornEmailTemplate;
use App\Models\Job;
use App\Models\JobSeeker;
use Illuminate\Support\Facades\Mail;

class SendEmails extends Command
{
    protected $signature = 'emails:send';
    protected $description = 'Send weekly and recommendation emails';

    public function handle()
    {
        // 🔥 WEEKLY EMAIL
        $this->sendWeeklyEmails();



        $this->info('Emails processed successfully');
    }

    /*
    |--------------------------------------------------------------------------
    | ✅ WEEKLY EMAIL
    |--------------------------------------------------------------------------
    */
    private function sendWeeklyEmails()
    {
        $template = CornEmailTemplate::where('type', 'weekly')
            ->where('is_active', true)
            ->first();

        if (!$template) return;

        $users = JobSeeker::with('user')
            ->whereHas('user', function ($q) {
                $q->whereNotNull('email');
            })
            ->get();

        foreach ($users as $user) {

            if (!$user->user) continue;

            $skills = $user->skills ?? '';
            $location = $user->location ?? '';

            $skillsArray = array_filter(array_map('trim', explode(',', $skills)));

            $jobs = Job::where('created_at', '>=', now()->subDays(7))
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->where('status', 'approved')
                ->where('job_status', 'open')
                ->where(function ($q) use ($skillsArray, $location) {

                    foreach ($skillsArray as $skill) {
                        $q->orWhere('keywords', 'LIKE', "%{$skill}%");
                    }

                    if ($location) {
                        $q->orWhere('location', 'LIKE', "%{$location}%");
                    }
                })
                ->limit(10)
                ->get();

            if ($jobs->isEmpty()) continue;

            $jobsHtml = $this->generateJobsHtml($jobs);

            $html = $template->html_template;

            $html = str_replace('{{name}}', $user->user->name, $html);
            $html = str_replace('{{jobs}}', $jobsHtml, $html);
            $html = str_replace('{{date}}', now()->format('d M Y'), $html);

            Mail::html($html, function ($message) use ($user, $template) {
                $message->to($user->user->email)
                    ->subject($template->subject);
            });
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ✅ JOB HTML GENERATOR
    |--------------------------------------------------------------------------
    */
    private function generateJobsHtml($jobs)
    {
        $html = '';

        $baseUrl = "http://teachnowbackend.jobsvedika.in:8080/";

        foreach ($jobs as $job) {

            $jobUrl = "https://yourfrontend.com/open/jobs/" . $job->slug;

            $companyName = $job->employer->company_name ?? 'Company';

            $logoPath = $job->employer->company_logo ?? '';
            $companyLogo = $logoPath ? $baseUrl . $logoPath : '';

            $html .= "
        <div style='margin-bottom:18px; padding:15px; border:1px solid #e2e8f0; border-radius:8px;'>

            <table width='100%' cellpadding='0' cellspacing='0'>
                <tr>

                    <td width='60' valign='top'>
                        " . ($companyLogo ? "
                        <img src='{$companyLogo}'
                             alt='{$companyName}'
                             style='width:50px; height:50px; object-fit:contain; border-radius:6px;' />
                        " : "") . "
                    </td>

                    <td valign='top' style='padding-left:10px;'>

                        <strong style='font-size:15px; color:#1e293b;'>
                            {$job->title}
                        </strong><br>

                        <span style='font-size:12px; color:#475569;'>
                            {$companyName}
                        </span><br>

                        <span style='font-size:12px; color:#64748b;'>
                            {$job->location}
                        </span><br>

                        <span style='font-size:12px;'>
                            Salary: {$job->salary_min} - {$job->salary_max}
                        </span>

                    </td>
                </tr>
            </table>

            <div style='margin-top:10px;'>
                <a href='{$jobUrl}'
                   style='display:inline-block; padding:8px 14px; background:#4f46e5; color:#ffffff; text-decoration:none; font-size:12px; border-radius:5px;'>
                   View Details
                </a>
            </div>

        </div>";
        }

        return $html ?: "<p>No jobs found</p>";
    }
}
