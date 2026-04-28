<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $toEmail;
    protected $subject;
    protected $body;
    protected $attachments;

    /**
     * Create a new job instance.
     */
    public function __construct($toEmail, $subject, $body, $attachments = [])
    {
        $this->toEmail = $toEmail;
        $this->subject = $subject;
        $this->body = $body;
        $this->attachments = $attachments;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        Mail::html($this->body, function ($message) {

            $message->to($this->toEmail)
                ->subject($this->subject)->setBody($this->body, 'text/html');

            // ✅ Attachments (PDF invoice etc.)
            if (!empty($this->attachments)) {
                foreach ($this->attachments as $file) {
                    $message->attachData(
                        base64_decode($file['content']),
                        $file['name'],
                        ['mime' => $file['mime']]
                    );
                }
            }
        });
    }
}
