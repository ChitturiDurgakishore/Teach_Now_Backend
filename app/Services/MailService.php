<?php
namespace App\Services;

use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Mail;

class MailService
{
    public function send($slug, $data, $toEmail)
    {
        $template = EmailTemplate::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$template) return;

        $subject = $template->subject;
        $body = $template->body;

        // Replace variables
        foreach ($data as $key => $value) {
            $body = str_replace('{' . $key . '}', $value, $body);
        }

        Mail::raw($body, function ($message) use ($toEmail, $subject) {
            $message->to($toEmail)
                    ->subject($subject);
        });
    }
}
