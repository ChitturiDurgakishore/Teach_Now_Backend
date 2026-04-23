<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\EmailTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use App\Jobs\SendEmailJob;
use Illuminate\Support\Facades\Storage;


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
        $plan = $invoice->order->plan;
        $employer = $invoice->employer;

        // 🔥 Get template
        $template = EmailTemplate::where('slug', 'invoice_template')
            ->where('is_active', true)
            ->first();

        if (!$template) {
            throw new \Exception('Invoice template not found');
        }

        // 🔥 Prepare data
        $data = [
            'company_name' => $settings->company_name ?? '',
            'company_address' => $settings->address ?? '',
            'footer' => $settings->footer ?? '',

            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date,

            'employer_name' => $employer->company_name ?? '',
            'employer_email' => $employer->email ?? '',

            'plan_name' => $plan->name ?? '',
            'amount' => $invoice->amount,
            'currency' => $invoice->currency,
        ];

        // 🔥 Replace variables
        $html = $template->body;

        foreach ($data as $key => $value) {
            $html = str_replace('{' . $key . '}', $value, $html);
        }

        /*
    |--------------------------------------------------------------------------
    | 🔥 CHECK IF PDF ALREADY EXISTS
    |--------------------------------------------------------------------------
    */

        if ($invoice->pdf_path && Storage::exists($invoice->pdf_path)) {

            $pdfContent = base64_encode(Storage::get($invoice->pdf_path));
        } else {

            /*
        |--------------------------------------------------------------------------
        | 🔥 GENERATE PDF
        |--------------------------------------------------------------------------
        */

            $pdf = Pdf::loadHTML($html);

            $fileName = 'invoice_' . $invoice->invoice_number . '.pdf';
            $filePath = 'invoices/' . $fileName;

            // 🔥 Store PDF
            Storage::disk('public')->put($filePath, $pdf->output());

            // 🔥 Save path in DB
            $invoice->update([
                'pdf_path' => 'storage/' . $filePath
            ]);

            $pdfContent = base64_encode($pdf->output());
        }

        /*
    |--------------------------------------------------------------------------
    | 🔥 SEND MAIL (QUEUE)
    |--------------------------------------------------------------------------
    */

        SendEmailJob::dispatch(
            $employer->email,
            $template->subject,
            $html,
            [
                [
                    'content' => $pdfContent,
                    'name' => 'invoice_' . $invoice->invoice_number . '.pdf',
                    'mime' => 'application/pdf'
                ]
            ]
        );
    }
}
