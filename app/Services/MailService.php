<?php

namespace App\Services;

use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Mail;
use App\Jobs\SendEmailJob;
use Exception;
use Illuminate\Database\QueryException;


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

        foreach ($data as $key => $value) {
            $body = str_replace('{' . $key . '}', $value, $body);
        }

        SendEmailJob::dispatch($toEmail, $subject, $body);
    }
}
