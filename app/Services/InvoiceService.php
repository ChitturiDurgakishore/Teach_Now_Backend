<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\EmailTemplate;
use App\Models\HomepageCompanyLogo;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Jobs\SendEmailJob;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class InvoiceService
{
    public function generate($order, $subscription)
    {
        // 🔒 prevent duplicate
        $existing = Invoice::where('order_id', $order->id)->first();
        if ($existing) {
            return $existing;
        }

        // ✅ create first (TEMP invoice number)
        $invoice = Invoice::create([
            'invoice_number' => 'TEMP',
            'employer_id' => $order->employer_id,
            'order_id' => $order->id,
            'subscription_id' => $subscription->id,
            'amount' => $order->amount,
            'currency' => $order->currency,
            'invoice_date' => now()
        ]);

        // ✅ generate clean invoice number using ID
        $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad($invoice->id, 6, '0', STR_PAD_LEFT);

        $invoice->update([
            'invoice_number' => $invoiceNumber
        ]);

        // 🔥 generate pdf + email
        $this->generatePdfAndSend($invoice);

        return $invoice;
    }

    /**
     * 🔥 Generate PDF + Send Email
     */
    public function generatePdfAndSend($invoice)
    {
        $invoice->load(['order.plan', 'employer']);

        $settings = InvoiceSetting::first();
        $plan = $invoice->order->plan;
        $employer = $invoice->employer;

        // ✅ Logo
        $logo = \App\Models\HomepageCompanyLogo::latest()->first();
        $logoPath = $logo && $logo->logo
            ? public_path('storage/' . $logo->logo)
            : null;

        /*
    |--------------------------------------------------------------------------
    | 🔥 HARDCODED PDF HTML (DOMPDF SAFE)
    |--------------------------------------------------------------------------
    */

        $html = '
    <div style="font-family: DejaVu Sans, sans-serif; font-size: 12px; color:#333;">

        <!-- HEADER -->
        <table width="100%" cellpadding="5">
            <tr>
                <td width="50%">
                    ' . ($logoPath ? '<img src="' . $logoPath . '" height="50">' : '') . '
                </td>
                <td width="50%" align="right">
                    <h2 style="margin:0;">INVOICE</h2>
                    <p style="margin:0;"><strong>' . $invoice->invoice_number . '</strong></p>
                    <p style="margin:0;">' . $invoice->invoice_date . '</p>
                </td>
            </tr>
        </table>

        <hr>

        <!-- COMPANY -->
        <p><strong>' . ($settings->company_name ?? '') . '</strong></p>
        <p>' . ($settings->address ?? '') . '</p>

        <hr>

        <!-- BILL TO -->
        <p><strong>Bill To:</strong></p>
        <p>
            ' . ($employer->company_name ?? '') . '<br>
            ' . ($employer->email ?? '') . '
        </p>

        <br>

        <!-- PLAN DETAILS -->
        <table width="100%" border="1" cellspacing="0" cellpadding="8">
            <tr style="background:#f2f2f2;">
                <th align="left">Description</th>
                <th align="right">Amount</th>
            </tr>
            <tr>
                <td>' . ($plan->name ?? '') . '</td>
                <td align="right">₹ ' . number_format($invoice->amount, 2) . '</td>
            </tr>
            <tr>
                <td align="right"><strong>Total</strong></td>
                <td align="right"><strong>₹ ' . number_format($invoice->amount, 2) . '</strong></td>
            </tr>
        </table>

        <br>

        <!-- PAYMENT INFO -->
        <p><strong>Status:</strong> Paid</p>
        <p><strong>Transaction ID:</strong> ' . ($invoice->order->razorpay_payment_id ?? '-') . '</p>

        <br><br>

        <hr>

        <!-- FOOTER -->
        <p style="font-size:10px; color:#777;">
            ' . ($settings->footer ?? 'Thank you for choosing us!') . '
        </p>

    </div>
    ';

        /*
    |--------------------------------------------------------------------------
    | 🔥 PDF GENERATION
    |--------------------------------------------------------------------------
    */

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);

        $fileName = 'invoice_' . $invoice->invoice_number . '.pdf';
        $filePath = 'invoices/' . $fileName;

        \Storage::disk('public')->put($filePath, $pdf->output());

        $invoice->update([
            'pdf_path' => 'storage/' . $filePath
        ]);

        /*
    |--------------------------------------------------------------------------
    | 📧 EMAIL
    |--------------------------------------------------------------------------
    */

        $pdfContent = base64_encode($pdf->output());

        \App\Jobs\SendEmailJob::dispatch(
            $employer->email,
            'Invoice - ' . $invoice->invoice_number,
            $html,
            [
                [
                    'content' => $pdfContent,
                    'name' => $fileName,
                    'mime' => 'application/pdf'
                ]
            ]
        );
    }
}
