<?php

use App\Mail\PaymentApproved;
use App\Mail\PaymentRejected;
use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Verify Payments')] class extends Component
{
    use WithPagination;

    public string $statusFilter = 'pending';

    public ?int $approvingPaymentId = null;

    public ?int $rejectingPaymentId = null;

    public function payments()
    {
        return Payment::query()
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->with(['studentInvoice.student', 'studentInvoice.invoice', 'receipt'])
            ->latest()
            ->paginate(20);
    }

    public function confirmApprove($id)
    {
        $this->approvingPaymentId = $id;
        $this->js('$flux.modal("confirm-approval").show()');
    }

    public function approve()
    {
        $payment = Payment::find($this->approvingPaymentId);
        if (! $payment || $payment->status !== 'pending') {
            return;
        }

        $payment->update(['status' => 'success']);

        // Update Student Invoice balance
        $studentInvoice = $payment->studentInvoice;
        $studentInvoice->update([
            'amount_paid' => $studentInvoice->amount_paid + $payment->amount_paid,
            'balance' => $studentInvoice->total_amount - ($studentInvoice->amount_paid + $payment->amount_paid),
        ]);

        // Check if fully paid
        if ($studentInvoice->balance <= 0) {
            $studentInvoice->update(['status' => 'paid']);
        } elseif ($studentInvoice->amount_paid > 0) {
            $studentInvoice->update(['status' => 'partial']);
        }

        // Generate Receipt
        $receipt = Receipt::create([
            'institution_id' => $payment->institution_id,
            'payment_id' => $payment->id,
            'receipt_number' => 'REC-'.strtoupper(Str::random(10)), // Simple unique ref
            'issued_at' => now(),
        ]);

        // Reload payment with relationships for the email
        $payment->load(['studentInvoice.student', 'studentInvoice.invoice', 'receipt']);

        // Send Email Notification
        if ($payment->studentInvoice->student->email) {
            Mail::to($payment->studentInvoice->student->email)->send(new PaymentApproved($payment));
        }

        $this->approvingPaymentId = null;
        $this->js('$flux.modal("confirm-approval").close()');

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Payment approved and receipt generated successfully.',
        ]);
    }

    public function confirmReject($id)
    {
        $this->rejectingPaymentId = $id;
        $this->js('$flux.modal("confirm-rejection").show()');
    }

    public function reject()
    {
        $payment = Payment::find($this->rejectingPaymentId);
        if (! $payment || $payment->status !== 'pending') {
            return;
        }

        $payment->update(['status' => 'failed']);

        // Reload payment with relationships for the email
        $payment->load(['studentInvoice.student', 'studentInvoice.invoice']);

        // Send Email Notification
        if ($payment->studentInvoice->student->email) {
            Mail::to($payment->studentInvoice->student->email)->send(new PaymentRejected($payment));
        }

        $this->rejectingPaymentId = null;
        $this->js('$flux.modal("confirm-rejection").close()');

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Payment rejected.',
        ]);
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Verify Payments</flux:heading>
            <flux:subheading>Approve or reject manual payment records from students.</flux:subheading>
        </div>

        <flux:select wire:model.live="statusFilter" :label="__('Verification Status')" class="max-w-xs">
            <flux:select.option value="all">All Payments</flux:select.option>
            <flux:select.option value="pending">Pending Verification</flux:select.option>
            <flux:select.option value="success">Success / Completed</flux:select.option>
            <flux:select.option value="failed">Rejected / Failed</flux:select.option>
        </flux:select>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-zinc-200 dark:border-zinc-700">
                    <th class="py-3 px-4 font-semibold text-zinc-900 dark:text-zinc-100 uppercase text-xs tracking-wider">Student</th>
                    <th class="py-3 px-4 font-semibold text-zinc-900 dark:text-zinc-100 uppercase text-xs tracking-wider">Invoice / Ref</th>
                    <th class="py-3 px-4 font-semibold text-zinc-900 dark:text-zinc-100 uppercase text-xs tracking-wider text-right">Amount</th>
                    <th class="py-3 px-4 font-semibold text-zinc-900 dark:text-zinc-100 uppercase text-xs tracking-wider">Status</th>
                    <th class="py-3 px-4 font-semibold text-zinc-900 dark:text-zinc-100 uppercase text-xs tracking-wider">Date</th>
                    <th class="py-3 px-4"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @php $paginatedPayments = $this->payments(); @endphp
                @if($paginatedPayments->count() > 0)
                    @foreach ($paginatedPayments as $payment)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <td class="py-4 px-4">
                                <flux:text weight="medium" class="text-zinc-900 dark:text-white">{{ $payment->studentInvoice?->student?->full_name }}</flux:text>
                                <flux:text size="sm" class="text-zinc-500">{{ $payment->studentInvoice?->student?->matric_number }}</flux:text>
                            </td>
                            <td class="py-4 px-4">
                                <flux:text size="sm">{{ $payment->studentInvoice?->invoice?->title }}</flux:text>
                                <flux:text size="xs" class="text-zinc-500">Ref: {{ $payment->reference }}</flux:text>
                            </td>
                            <td class="py-4 px-4 text-right font-semibold">
                                ₦{{ number_format($payment->amount_paid, 2) }}
                            </td>
                            <td class="py-4 px-4">
                                <flux:badge 
                                    :variant="$payment->status === 'success' ? 'success' : ($payment->status === 'pending' ? 'warning' : 'danger')"
                                    size="sm"
                                >
                                    {{ ucfirst($payment->status) }}
                                </flux:badge>
                            </td>
                            <td class="py-4 px-4">
                                <flux:text size="sm">{{ $payment->created_at?->format('M d, Y') }}</flux:text>
                            </td>
                            <td class="py-4 px-4 text-right">
                                @if($payment->status === 'pending')
                                    <div class="flex items-center justify-end gap-2">
                                        <flux:button variant="subtle" size="sm" wire:click="confirmApprove({{ $payment->id }})" class="text-green-600 hover:text-green-700">Approve</flux:button>
                                        <flux:button variant="ghost" size="sm" wire:click="confirmReject({{ $payment->id }})" class="text-red-600 hover:text-red-700">Reject</flux:button>
                                    </div>
                                @elseif($payment->status === 'success' && $payment->receipt)
                                    <div class="flex items-center justify-end">
                                        <flux:button variant="ghost" size="sm" icon="printer" href="{{ route('cms.invoices.receipt.print', $payment->receipt->receipt_number) }}" target="_blank" class="text-blue-600 hover:text-blue-700">Print Receipt</flux:button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="6" class="py-12 text-center text-zinc-500">No payments found.</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="py-4">
        {{ $paginatedPayments->links() }}
    </div>

    <!-- Approval Confirmation Modal -->
    <flux:modal name="confirm-approval" class="min-w-[400px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Approve Payment?</flux:heading>
                <flux:subheading>Are you sure you want to approve this payment? This will update the student's balance and generate a receipt.</flux:subheading>
            </div>

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="approve">Confirm Approval</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Rejection Confirmation Modal -->
    <flux:modal name="confirm-rejection" class="min-w-[400px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Reject Payment?</flux:heading>
                <flux:subheading>Are you sure you want to reject this payment record? This action will mark it as failed.</flux:subheading>
            </div>

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="reject">Confirm Rejection</flux:button>
            </div>
        </div>
    </flux:modal>
</div>