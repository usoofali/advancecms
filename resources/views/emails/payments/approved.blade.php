@php /** @var \App\Models\Payment $payment */ @endphp
<x-mail::message>
# Payment Approved

Dear {{ $payment->studentInvoice->student->full_name }},

Your payment record has been verified and approved by the Bursary department.

**Payment Details:**
- **Invoice:** {{ $payment->studentInvoice->invoice->title }}
- **Amount Paid:** ₦{{ number_format($payment->amount_paid, 2) }}
- **Reference:** {{ $payment->reference }}
- **Date:** {{ $payment->created_at->format('M d, Y') }}

@if($payment->receipt)
**Receipt Number:** {{ $payment->receipt->receipt_number }}

<x-mail::button :url="route('cms.invoices.receipt.print', $payment->receipt->receipt_number)">
View Official Receipt
</x-mail::button>
@endif

You can also view your payment history and balance on the student portal.

Thanks,<br>
{{ config('app.name') }} Bursary
</x-mail::message>
