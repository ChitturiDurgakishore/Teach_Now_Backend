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
