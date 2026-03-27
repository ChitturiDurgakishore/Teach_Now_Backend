<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EmailTemplate;
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

        // 🔥 RECOMMENDATION EMAIL
        $this->sendRecommendationEmails();

        $this->info('Emails processed successfully');
    }

    /*
    |--------------------------------------------------------------------------
    | ✅ WEEKLY EMAIL
    |--------------------------------------------------------------------------
    */
    private function sendWeeklyEmails()
    {
        $template = EmailTemplate::where('type', 'weekly')
            ->where('is_active', true)
            ->first();

        if (!$template) return;

        // last 7 days jobs
        $jobs = Job::where('created_at', '>=', now()->subDays(7))
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->where('status', 'approved')
            ->where('job_status', 'open')
            ->limit(10)
            ->get();

        $jobsHtml = $this->generateJobsHtml($jobs);

        $users = JobSeeker::whereNotNull('email')->get();

        foreach ($users as $user) {

            $html = $template->html_template;

            $html = str_replace('{{name}}', $user->name, $html);
            $html = str_replace('{{jobs}}', $jobsHtml, $html);
            $html = str_replace('{{date}}', now()->format('d M Y'), $html);

            Mail::html($html, function ($message) use ($user, $template) {
                $message->to($user->email)
                    ->subject($template->subject);
            });
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ✅ RECOMMENDATION EMAIL
    |--------------------------------------------------------------------------
    */
    private function sendRecommendationEmails()
    {
        $template = EmailTemplate::where('type', 'recommendation')
            ->where('is_active', true)
            ->first();

        if (!$template) return;

        $users = JobSeeker::whereNotNull('email')->get();

        foreach ($users as $user) {

            $jobs = Job::where('is_active', true)
                ->where('expires_at', '>', now())
                ->where('status', 'approved')
                ->where('job_status', 'open')
                ->where(function ($q) use ($user) {
                    $q->where('keywords', 'LIKE', "%{$user->skills}%")
                        ->orWhere('location', 'LIKE', "%{$user->location}%");
                })
                ->limit(10)
                ->get();

            if ($jobs->isEmpty()) continue;

            $jobsHtml = $this->generateJobsHtml($jobs);

            $html = $template->html_template;

            $html = str_replace('{{name}}', $user->name, $html);
            $html = str_replace('{{jobs}}', $jobsHtml, $html);
            $html = str_replace('{{date}}', now()->format('d M Y'), $html);

            Mail::html($html, function ($message) use ($user, $template) {
                $message->to($user->email)
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

        foreach ($jobs as $job) {

            $html .= "
            <div style='margin-bottom:15px; padding:10px; border:1px solid #eee;'>
                <strong>{$job->title}</strong><br>
                {$job->location}<br>
                Salary: {$job->salary_min} - {$job->salary_max}
            </div>";
        }

        return $html ?: "<p>No jobs found</p>";
    }
}
