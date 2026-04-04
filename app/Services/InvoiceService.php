<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Str;

class InvoiceService
{
    public function generate($order, $subscription)
    {
        // 🔒 prevent duplicate
        if (Invoice::where('order_id', $order->id)->exists()) {
            return;
        }

        $invoiceNumber = $this->generateInvoiceNumber();

        return Invoice::create([
            'invoice_number' => $invoiceNumber,
            'employer_id' => $order->employer_id,
            'order_id' => $order->id,
            'subscription_id' => $subscription->id,
            'amount' => $order->amount,
            'currency' => $order->currency,
            'invoice_date' => now()
        ]);
    }

    private function generateInvoiceNumber()
    {
        $count = Invoice::count() + 1;

        return 'INV-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
