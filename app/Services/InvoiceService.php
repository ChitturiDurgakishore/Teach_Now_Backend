<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\EmailTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;

class InvoiceService
{
    public function generate($order, $subscription)
    {
        // 🔒 prevent duplicate
        $existing = Invoice::where('order_id', $order->id)->first();

        if ($existing) {
            return $existing;
        }

        $invoice = Invoice::create([
            'invoice_number' => $this->generateInvoiceNumber(),
            'employer_id' => $order->employer_id,
            'order_id' => $order->id,
            'subscription_id' => $subscription->id,
            'amount' => $order->amount,
            'currency' => $order->currency,
            'invoice_date' => now()
        ]);

        // 🔥 AFTER CREATE → GENERATE PDF + SEND EMAIL
        $this->generatePdfAndSend($invoice);

        return $invoice;
    }

    private function generateInvoiceNumber()
    {
        $count = Invoice::count() + 1;

        return 'INV-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 🔥 Generate PDF from DB template + Send Email
     */
    public function generatePdfAndSend($invoice)
    {
        $invoice->load(['order.plan', 'employer']);

        $settings = InvoiceSetting::first();
        $template = EmailTemplate::where('name', 'invoice_template')->first();

        if (!$template) {
            throw new \Exception('Invoice template not found');
        }

        $plan = $invoice->order->plan;
        $employer = $invoice->employer;

        // 🔥 TEMPLATE PARSING
        $html = $template->body;

        $replacements = [
            '{{company_name}}' => $settings->company_name ?? '',
            '{{company_address}}' => $settings->address ?? '',
            '{{footer}}' => $settings->footer ?? '',

            '{{invoice_number}}' => $invoice->invoice_number,
            '{{invoice_date}}' => $invoice->invoice_date,

            '{{employer_name}}' => $employer->company_name ?? '',
            '{{employer_email}}' => $employer->email ?? '',

            '{{plan_name}}' => $plan->name ?? '',
            '{{amount}}' => $invoice->amount,
            '{{currency}}' => $invoice->currency,
        ];

        foreach ($replacements as $key => $value) {
            $html = str_replace($key, $value, $html);
        }

        // 🔥 GENERATE PDF
        $pdf = Pdf::loadHTML($html);

        $fileName = 'invoice_' . $invoice->invoice_number . '.pdf';

        // 🔥 SEND EMAIL
        Mail::send([], [], function ($message) use ($employer, $pdf, $fileName, $template) {
            $message->to($employer->email)
                ->subject($template->subject ?? 'Invoice')
                ->attachData($pdf->output(), $fileName);
        });
    }
}
