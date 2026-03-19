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

    public function __construct($toEmail, $subject, $body)
    {
        $this->toEmail = $toEmail;
        $this->subject = $subject;
        $this->body = $body;
    }

    public function handle()
    {
        Mail::raw($this->body, function ($message) {
            $message->to($this->toEmail)
                    ->subject($this->subject);
        });
    }
}
