<?php

namespace App\Services;

use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Mail;
use App\Jobs\SendEmailJob;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class MailService
{
    public function send($slug, $data, $toEmail)
    {
        $template = EmailTemplate::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            Log::warning("Email template not found: {$slug}");
            return;
        }

        $subject = $template->subject;
        $body = $template->body;

        // Replace dynamic variables
        foreach ($data as $key => $value) {
            $body = str_replace('{' . $key . '}', $value, $body);
        }

        // ✅ IMPORTANT FIX (you missed this earlier)
        $body = str_replace('{app_url}', config('app.url'), $body);

        // Optional: decode if DB stores escaped HTML
        $body = html_entity_decode($body);

        // Dispatch job
        SendEmailJob::dispatch($toEmail, $subject, $body);

        Log::info("📧 Email dispatched", [
            'to' => $toEmail,
            'slug' => $slug
        ]);
    }
}
