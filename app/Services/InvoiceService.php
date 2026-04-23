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

        // ✅ create invoice
        $invoice = Invoice::create([
            'invoice_number' => 'TEMP',
            'employer_id' => $order->employer_id,
            'order_id' => $order->id,
            'subscription_id' => $subscription->id,
            'amount' => $order->amount,
            'currency' => $order->currency,
            'invoice_date' => now()
        ]);

        // ✅ generate invoice number
        $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad($invoice->id, 6, '0', STR_PAD_LEFT);

        $invoice->update([
            'invoice_number' => $invoiceNumber
        ]);

        // 🔥 generate PDF + email
        $this->generatePdfAndSend($invoice);

        return $invoice;
    }

    public function generatePdfAndSend($invoice)
    {
        $invoice->load(['order.plan', 'employer']);

        $settings = InvoiceSetting::first();
        $plan = $invoice->order->plan;
        $employer = $invoice->employer;

        // ✅ Logo
        $logo = HomepageCompanyLogo::latest()->first();

        $logoPath = $logo && $logo->logo
            ? public_path('storage/' . $logo->logo) // for PDF
            : null;

        $logoUrl = $logo && $logo->logo
            ? asset('storage/' . $logo->logo) // for Email
            : '';

        /*
        |--------------------------------------------------------------------------
        | 🔥 PDF HTML (HARDCODED)
        |--------------------------------------------------------------------------
        */

        $html = '
        <div style="font-family: DejaVu Sans, sans-serif; font-size: 12px; color:#333;">

            <table width="100%" cellpadding="5">
                <tr>
                    <td width="50%">
                        ' . ($logoPath ? '<img src="' . $logoPath . '" height="50">' : '') . '
                    </td>
                    <td width="50%" align="right">
                        <h2 style="margin:0;">INVOICE</h2>
                        <p style="margin:0;"><strong>' . $invoice->invoice_number . '</strong></p>
                        <p style="margin:0;">' . $invoice->invoice_date->format('d M Y') . '</p>
                    </td>
                </tr>
            </table>

            <hr>

            <p><strong>' . ($settings->company_name ?? '') . '</strong></p>
            <p>' . ($settings->address ?? '') . '</p>

            <hr>

            <p><strong>Bill To:</strong></p>
            <p>
                ' . ($employer->company_name ?? '') . '<br>
                ' . ($employer->email ?? '') . '
            </p>

            <br>

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

            <p><strong>Status:</strong> Paid</p>
            <p><strong>Transaction ID:</strong> ' . ($invoice->order->razorpay_payment_id ?? '-') . '</p>

            <br><br>

            <hr>

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

        $pdf = Pdf::loadHTML($html);

        $fileName = 'invoice_' . $invoice->invoice_number . '.pdf';
        $filePath = 'invoices/' . $fileName;

        Storage::disk('public')->put($filePath, $pdf->output());

        $invoice->update([
            'pdf_path' => 'storage/' . $filePath
        ]);

        /*
        |--------------------------------------------------------------------------
        | 📧 EMAIL (DB TEMPLATE)
        |--------------------------------------------------------------------------
        */

        $pdfContent = base64_encode($pdf->output());

        $template = EmailTemplate::where('slug', 'invoice_template')
            ->where('is_active', true)
            ->first();

        if (!$template) {
            Log::error('Invoice email template not found');
            return;
        }

        $emailHtml = $template->body;

        $emailHtml = str_replace([
            '{logo}',
            '{invoice_number}',
            '{invoice_date}',
            '{company_name}',
            '{company_address}',
            '{employer_name}',
            '{employer_email}',
            '{plan_name}',
            '{amount}',
            '{currency}',
        ], [
            $logoUrl,
            $invoice->invoice_number,
            $invoice->invoice_date->format('d M Y'),
            $settings->company_name ?? '',
            $settings->address ?? '',
            $employer->company_name ?? '',
            $employer->email ?? '',
            $plan->name ?? '',
            number_format($invoice->amount, 2),
            $invoice->currency,
        ], $emailHtml);

        SendEmailJob::dispatch(
            $employer->email,
            'Invoice - ' . $invoice->invoice_number,
            $emailHtml,
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
