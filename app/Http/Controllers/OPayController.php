<?php

namespace App\Http\Controllers;

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\OPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OPayController extends Controller
{
    public function __construct(protected OPayService $opayService) {}

    /**
     * Handle webhook notification from OPay.
     */
    public function handleCallback(Request $request)
    {
        $payloadJson = $request->getContent();
        $signature = $request->header('sha512');

        if (! $this->opayService->verifyCallback($payloadJson, $signature)) {
            Log::warning('OPay Webhook: Invalid signature received.');

            return response()->json(['code' => '400', 'message' => 'Invalid signature'], 400);
        }

        $data = $request->json()->all();
        $reference = $data['payload']['reference'] ?? null;
        $status = $data['payload']['status'] ?? null;

        if (! $reference) {
            return response()->json(['code' => '400', 'message' => 'Reference missing'], 400);
        }

        if (str_starts_with($reference, 'APP-')) {
            // First try to match by application_number (original), then fall back to gateway_reference (retry)
            $applicant = Applicant::where('application_number', $reference)->first()
                ?? Applicant::where('gateway_reference', $reference)->first();

            if (! $applicant) {
                Log::error("OPay Webhook: Applicant not found for reference {$reference}");

                return response()->json(['code' => '404', 'message' => 'Applicant not found'], 404);
            }

            if ($applicant->payment_status === 'paid') {
                return response()->json(['code' => '00000', 'message' => 'SUCCESS']);
            }

            if ($status === 'SUCCESS') {
                $this->processSuccessfulApplicationPayment($applicant, $data['payload']);
            }

            return response()->json(['code' => '00000', 'message' => 'SUCCESS']);
        }

        // Existing Student Payment Logic
        $payment = Payment::where('reference', $reference)->first();

        if (! $payment) {
            Log::error("OPay Webhook: Payment not found for reference {$reference}");

            return response()->json(['code' => '404', 'message' => 'Payment not found'], 404);
        }

        if ($payment->status === 'success') {
            return response()->json(['code' => '00000', 'message' => 'SUCCESS']);
        }

        if ($status === 'SUCCESS') {
            $this->processSuccessfulPayment($payment, $data['payload']);
        } elseif ($status === 'FAIL') {
            $payment->update(['status' => 'failed']);
        }

        return response()->json(['code' => '00000', 'message' => 'SUCCESS']);
    }

    /**
     * Handle applicant redirect back to the app.
     */
    public function handleApplicantReturn(Request $request)
    {
        $reference = $request->query('reference');

        if ($reference) {
            $applicant = Applicant::where('application_number', $reference)->first();

            if ($applicant && $applicant->payment_status === 'pending') {
                $statusData = $this->opayService->queryStatus($reference);
                if ($statusData && $statusData['status'] === 'SUCCESS') {
                    $this->processSuccessfulApplicationPayment($applicant, $statusData);
                }
            }

            // Redirect to applicant portal using the application number
            return redirect()->route('applicant.portal', ['application_number' => $reference])->with('notify', [
                'type' => ($applicant && $applicant->payment_status === 'paid') ? 'success' : 'warning',
                'message' => ($applicant && $applicant->payment_status === 'paid')
                    ? 'Your application fee was processed successfully.'
                    : 'Payment is still being processed or failed. Please check back later.',
            ]);
        }

        return redirect()->route('apply')->with('notify', ['type' => 'error', 'message' => 'Invalid return request.']);
    }

    /**
     * Handle applicant cancellation from OPay cashier.
     */
    public function handleApplicantCancel(Request $request)
    {
        return redirect()->route('apply')->with('notify', [
            'type' => 'info',
            'message' => 'Payment session cancelled. You can try again when ready.',
        ]);
    }

    /**
     * Handle student redirect back to the app.
     */
    public function handleReturn(Request $request)
    {
        $reference = $request->query('reference');

        if ($reference) {
            $payment = Payment::where('reference', $reference)->first();

            if ($payment && $payment->status === 'pending') {
                // Manually query status to be sure if webhook hasn't hit yet
                $statusData = $this->opayService->queryStatus($reference);
                if ($statusData && $statusData['status'] === 'SUCCESS') {
                    $this->processSuccessfulPayment($payment, $statusData);
                }
            }
        }

        $type = ($payment && $payment->status === 'success') ? 'success' : 'warning';
        $message = ($payment && $payment->status === 'success')
            ? 'Your payment was processed successfully.'
            : 'Payment is still being processed or failed. Please check your history.';

        return redirect()->route('cms.students.portal-invoices')->with('notify', [
            'type' => $type,
            'message' => $message,
        ]);
    }

    /**
     * Handle student cancellation from OPay cashier.
     */
    public function handleCancel(Request $request)
    {
        $reference = $request->query('reference');

        if ($reference) {
            $payment = Payment::where('reference', $reference)->where('status', 'pending')->first();
            if ($payment) {
                $payment->delete();
            }
        }

        return redirect()->route('cms.students.portal-invoices')->with('notify', [
            'type' => 'info',
            'message' => 'Payment session cancelled.',
        ]);
    }

    /**
     * Common logic for processing a successful payment.
     */
    protected function processSuccessfulPayment(Payment $payment, array $gatewayData)
    {
        $payment->update([
            'status' => 'success',
            'gateway_order_no' => $gatewayData['orderNo'] ?? $payment->gateway_order_no,
            'metadata' => array_merge($payment->metadata ?? [], ['gateway_payload' => $gatewayData]),
        ]);

        $studentInvoice = $payment->studentInvoice;
        $newAmountPaid = $studentInvoice->amount_paid + $payment->amount_paid;
        $newBalance = $studentInvoice->total_amount - $newAmountPaid;

        $studentInvoice->update([
            'amount_paid' => $newAmountPaid,
            'balance' => max(0, $newBalance),
        ]);

        if ($studentInvoice->balance <= 0) {
            $studentInvoice->update(['status' => 'paid']);
        } elseif ($studentInvoice->amount_paid > 0) {
            $studentInvoice->update(['status' => 'partial']);
        }

        // Generate Receipt if not exists
        if (! $payment->receipt) {
            Receipt::create([
                'institution_id' => $payment->institution_id,
                'payment_id' => $payment->id,
                'receipt_number' => 'REC-'.strtoupper(Str::random(10)),
                'issued_at' => now(),
            ]);
        }

        // Enrollment is handled manually by authorized staff (Admission Officer / Admin).
        // See: application-show.blade.php > enrollApplicant()
        if ($studentInvoice->applicant_id) {
            $applicant = $studentInvoice->applicant;
            if ($applicant && $applicant->admission_status === 'admitted' && ! $applicant->enrolled_at) {
                $pct = $studentInvoice->total_amount > 0
                    ? round(($studentInvoice->amount_paid / $studentInvoice->total_amount) * 100, 1)
                    : 0;
                Log::info("Applicant {$applicant->application_number} has paid {$pct}% of admission fee. Awaiting manual enrollment by staff.");
            }
        }
    }

    /**
     * Common logic for processing a successful applicant form payment.
     */
    protected function processSuccessfulApplicationPayment(Applicant $applicant, array $gatewayData)
    {
        $applicant->update([
            'payment_status' => 'paid',
        ]);

        // Create Payment record
        $payment = Payment::create([
            'institution_id' => $applicant->institution_id,
            'applicant_id' => $applicant->id,
            'amount_paid' => $applicant->applicationForm->amount,
            'payment_method' => 'opay',
            'payment_type' => 'automated',
            'reference' => $applicant->application_number,
            'gateway_order_no' => $gatewayData['orderNo'] ?? null,
            'metadata' => ['gateway_payload' => $gatewayData],
            'status' => 'success',
        ]);

        // Generate Receipt
        Receipt::create([
            'institution_id' => $applicant->institution_id,
            'payment_id' => $payment->id,
            'receipt_number' => 'APP-REC-'.strtoupper(Str::random(10)),
            'issued_at' => now(),
        ]);
    }
}
