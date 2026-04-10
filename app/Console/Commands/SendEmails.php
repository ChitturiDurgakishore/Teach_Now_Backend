<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CornEmailTemplate;
use App\Models\Job;
use App\Models\JobSeeker;
use Illuminate\Support\Facades\Mail;
use App\Models\EmailSetting;

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
        // 🔥 STEP 1: GET SETTINGS
        $setting = EmailSetting::where('type', 'weekly')
            ->where('is_active', true)
            ->first();

        if (!$setting) return;

        $now = now();
        $scheduled = \Carbon\Carbon::parse($setting->time);

        // 🔥 STEP 2: CHECK DAY
        if (strtolower($now->format('l')) !== $setting->day) {
            return;
        }

        // 🔥 STEP 3: CHECK TIME (ALLOW 5 MIN WINDOW)
        if (
            $now->format('H') != $scheduled->format('H') ||
            abs($now->minute - $scheduled->minute) > 5
        ) {
            return;
        }

        // 🔥 STEP 4: PREVENT DUPLICATE (ONCE PER DAY)
        if (
            $setting->last_sent_at &&
            \Carbon\Carbon::parse($setting->last_sent_at)->isToday()
        ) {
            return;
        }

        // 🔥 STEP 5: GET TEMPLATE
        $template = CornEmailTemplate::where('type', 'weekly')
            ->where('is_active', true)
            ->first();

        if (!$template) return;

        // 🔥 STEP 6: GET USERS
        $users = JobSeeker::with('user')
            ->whereHas('user', function ($q) {
                $q->whereNotNull('email');
            })
            ->get();

        // 🔥 STEP 7: LOOP USERS
        foreach ($users as $user) {

            if (!$user->user) continue;

            $skills = $user->skills ?? '';
            $location = $user->location ?? '';

            $skillsArray = array_filter(array_map('trim', explode(',', $skills)));

            // 🔥 STEP 8: PERSONALIZED JOBS
            $jobs = Job::with('employer')
                ->where('created_at', '>=', now()->subDays(7))
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

            // 🔥 STEP 9: GENERATE JOB HTML
            $jobsHtml = $this->generateJobsHtml($jobs);

            // 🔥 STEP 10: REPLACE TEMPLATE VARIABLES
            $html = $template->html_template;

            $html = str_replace('{{name}}', $user->user->name ?? 'User', $html);
            $html = str_replace('{{jobs}}', $jobsHtml, $html);
            $html = str_replace('{{date}}', now()->format('d M Y'), $html);

            // 🔥 STEP 11: SEND EMAIL
            Mail::html($html, function ($message) use ($user, $template) {
                $message->to($user->user->email)
                    ->subject($template->subject);
            });
        }

        // 🔥 STEP 12: UPDATE LAST SENT TIME
        $setting->update([
            'last_sent_at' => now()
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ✅ JOB HTML GENERATOR
    |--------------------------------------------------------------------------
    */
    private function generateJobsHtml($jobs)
    {
        $html = '';

        // Pulling both from config to ensure the server handles it correctly
        $mediaBaseUrl = config('app.media_url');
        $apiBaseUrl = config('app.url'); // Usually your main domain

        foreach ($jobs as $job) {

            // 🛠 FIX: Make the Job URL dynamic too
            $jobUrl = rtrim($apiBaseUrl, '/') . "/api/open/jobs/" . $job->slug;

            $companyName = $job->employer->company_name ?? 'Company';

            // Handling the Logo Path
            $logoPath = $job->employer->company_logo ?? '';

            // Ensure there's a slash between base and path if needed
            $companyLogo = $logoPath ? rtrim($mediaBaseUrl, '/') . '/' . ltrim($logoPath, '/') : '';

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
                        <strong style='font-size:15px; color:#1e293b;'>{$job->title}</strong><br>
                        <span style='font-size:12px; color:#475569;'>{$companyName}</span><br>
                        <span style='font-size:12px; color:#64748b;'>{$job->location}</span><br>
                        <span style='font-size:12px;'>
                            Salary: " . number_format($job->salary_min) . " - " . number_format($job->salary_max) . "
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
