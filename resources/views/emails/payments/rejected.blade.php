@php /** @var \App\Models\Payment $payment */ @endphp
<x-mail::message>
# Payment Rejected

Dear {{ $payment->studentInvoice->student->full_name }},

We regret to inform you that your payment record has been rejected by the Bursary department.

**Payment Details:**
- **Invoice:** {{ $payment->studentInvoice->invoice->title }}
- **Amount:** ₦{{ number_format($payment->amount_paid, 2) }}
- **Reference:** {{ $payment->reference }}
- **Date Submitted:** {{ $payment->created_at->format('M d, Y') }}

**Common reasons for rejection include:**
- Incorrect payment reference
- Non-matching amount between record and bank statement
- Unclear transfer/deposit details

Please log in to the student portal to edit your payment record with the correct details or submit a new one.

<x-mail::button :url="route('cms.students.portal-invoices')">
View My Invoices
</x-mail::button>

If you believe this is an error, please contact the Bursary department with your proof of payment.

Thanks,<br>
{{ config('app.name') }} Bursary
</x-mail::message>
