<?php

use App\Mail\PaymentApproved;
use App\Mail\PaymentRejected;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Verify Payments')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $statusFilter = 'pending';

    public ?int $institutionFilter = null;

    public ?int $approvingPaymentId = null;

    public ?int $rejectingPaymentId = null;

    public ?int $cancellingPaymentId = null;

    public function mount(): void
    {
        Gate::authorize('payments.view');
    }

    /**
     * Payments the current user may list and act on (institution scope).
     */
    private function paymentQueryForCurrentUser(): \Illuminate\Database\Eloquent\Builder
    {
        $user = auth()->user();
        $query = Payment::query();

        if ($user->hasRole('Super Admin')) {
            if ($this->institutionFilter) {
                $query->where('institution_id', $this->institutionFilter);
            }
        } else {
            $query->where('institution_id', $user->institution_id);
        }

        return $query;
    }

    public function payments()
    {
        return $this->paymentQueryForCurrentUser()
            ->when($this->statusFilter !== 'all', fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('reference', 'like', '%' . $this->search . '%')
                        ->orWhereHas('studentInvoice.student', function ($subQuery) {
                            $subQuery->where('first_name', 'like', '%' . $this->search . '%')
                                ->orWhere('last_name', 'like', '%' . $this->search . '%')
                                ->orWhere('matric_number', 'like', '%' . $this->search . '%');
                        })
                        ->orWhereHas('studentInvoice.applicant', function ($subQuery) {
                            $subQuery->where('full_name', 'like', '%' . $this->search . '%')
                                ->orWhere('application_number', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->with(['studentInvoice.student', 'studentInvoice.applicant', 'studentInvoice.invoice', 'receipt'])
            ->latest()
            ->paginate(20);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedInstitutionFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function confirmApprove($id)
    {
        $this->approvingPaymentId = $id;
        $this->js('$flux.modal("confirm-approval").show()');
    }

    public function approve()
    {
        Gate::authorize('payments.verify');

        $payment = $this->paymentQueryForCurrentUser()
            ->whereKey($this->approvingPaymentId)
            ->where('status', 'pending')
            ->first();

        if (!$payment) {
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
            'receipt_number' => 'REC-' . strtoupper(Str::random(10)), // Simple unique ref
            'issued_at' => now(),
        ]);

        // Reload payment with relationships for the email
        $payment->load(['studentInvoice.student', 'studentInvoice.invoice', 'receipt']);

        // Send Email Notification
        if ($payment->studentInvoice?->student?->email) {
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
        Gate::authorize('payments.verify');

        $payment = $this->paymentQueryForCurrentUser()
            ->whereKey($this->rejectingPaymentId)
            ->where('status', 'pending')
            ->first();

        if (!$payment) {
            return;
        }

        $payment->update(['status' => 'failed']);

        // Reload payment with relationships for the email
        $payment->load(['studentInvoice.student', 'studentInvoice.invoice']);

        // Send Email Notification
        if ($payment->studentInvoice?->student?->email) {
            Mail::to($payment->studentInvoice->student->email)->send(new PaymentRejected($payment));
        }

        $this->rejectingPaymentId = null;
        $this->js('$flux.modal("confirm-rejection").close()');

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Payment rejected.',
        ]);
    }

    public function confirmCancel($id): void
    {
        $this->cancellingPaymentId = $id;
        $this->js('$flux.modal("confirm-cancellation").show()');
    }

    public function cancelPayment(): void
    {
        Gate::authorize('payments.verify');

        $payment = $this->paymentQueryForCurrentUser()
            ->whereKey($this->cancellingPaymentId)
            ->where('status', 'success')
            ->first();

        if (! $payment) {
            return;
        }

        DB::transaction(function () use ($payment) {
            $payment->update(['status' => 'pending']);

            if ($payment->receipt) {
                $payment->receipt->delete();
            }

            $studentInvoice = $payment->studentInvoice;
            if ($studentInvoice) {
                $successfulPaymentsTotal = (float) $studentInvoice->payments()
                    ->where('status', 'success')
                    ->sum('amount_paid');

                $newBalance = max(0, (float) $studentInvoice->total_amount - $successfulPaymentsTotal);

                if ($successfulPaymentsTotal >= (float) $studentInvoice->total_amount && (float) $studentInvoice->total_amount > 0) {
                    $invoiceStatus = 'paid';
                } elseif ($successfulPaymentsTotal > 0) {
                    $invoiceStatus = 'partial';
                } else {
                    $invoiceStatus = 'pending';
                }

                $studentInvoice->update([
                    'amount_paid' => $successfulPaymentsTotal,
                    'balance' => $newBalance,
                    'status' => $invoiceStatus,
                ]);
            }

            ActivityLogger::log(
                action: 'cancelled',
                module: 'Payments',
                description: "Cancelled payment {$payment->reference} and reverted status from success to pending",
                subject: $payment,
                subjectLabel: $payment->reference,
                properties: [
                    'payment_id' => $payment->id,
                    'reference' => $payment->reference,
                    'amount' => $payment->amount_paid,
                    'student_invoice_id' => $payment->student_invoice_id,
                    'previous_status' => 'success',
                    'new_status' => 'pending',
                ]
            );
        });

        $this->cancellingPaymentId = null;
        $this->js('$flux.modal("confirm-cancellation").close()');

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Payment cancelled and reverted to pending successfully.',
        ]);
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <flux:heading size="xl">Verify Payments</flux:heading>
            <flux:subheading>Approve or reject manual payment records from students.</flux:subheading>
        </div>
    </div>
    <div class="flex flex-col sm:flex-row sm:items-end gap-4 w-full md:w-auto">
        @if(auth()->user()->hasRole('Super Admin'))
            <div class="w-full sm:w-64">
                <flux:select wire:model.live="institutionFilter" :label="__('Institution')">
                    <flux:select.option value="">{{ __('All Institutions') }}</flux:select.option>
                    @foreach(\App\Models\Institution::query()->where('status', 'active')->orderBy('name')->get() as $institution)
                        <flux:select.option value="{{ $institution->id }}">{{ $institution->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
        <div class="w-full sm:w-64">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="{{ __('Search by name, matric, ref...') }}" label="{{ __('Search') }}" />
        </div>
        <div class="w-full sm:w-64">
            <flux:select wire:model.live="statusFilter" :label="__('Verification Status')">
                <flux:select.option value="all">All Payments</flux:select.option>
                <flux:select.option value="pending">Pending Verification</flux:select.option>
                <flux:select.option value="success">Success / Completed</flux:select.option>
                <flux:select.option value="failed">Rejected / Failed</flux:select.option>
            </flux:select>
        </div>
    </div>
    @php $paginatedPayments = $this->payments(); @endphp
    <flux:card>
        <div class="relative overflow-x-auto">
            <flux:table :paginate="$paginatedPayments">
                <flux:table.columns>
                    <flux:table.column>Student</flux:table.column>
                    <flux:table.column>Invoice / Ref</flux:table.column>
                    <flux:table.column class="text-right">Amount</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column class="text-right"></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($paginatedPayments as $payment)
                        <flux:table.row :key="$payment->id">
                            <flux:table.cell>
                                <flux:text weight="medium" class="text-zinc-900 dark:text-white">
                                    {{ $payment->studentInvoice?->student?->full_name ?? $payment->studentInvoice?->applicant?->full_name }}
                                </flux:text>
                                <flux:text size="sm" class="text-zinc-500">
                                    {{ $payment->studentInvoice?->student?->matric_number ?? $payment->studentInvoice?->applicant?->application_number }}
                                </flux:text>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:text size="sm">{{ $payment->studentInvoice?->invoice?->title }}</flux:text>
                                <flux:text size="xs" class="text-zinc-500">Ref: {{ $payment->reference }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell class="text-right font-semibold">
                                ₦{{ number_format($payment->amount_paid, 2) }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge
                                    :variant="$payment->status === 'success' ? 'success' : ($payment->status === 'pending' ? 'warning' : 'danger')"
                                    size="sm">
                                    {{ ucfirst($payment->status) }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:text size="sm">{{ $payment->created_at?->format('M d, Y') }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell class="text-right">
                                @if($payment->status === 'pending' && auth()->user()->can('payments.verify'))
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                        <flux:menu>
                                            <flux:menu.item icon="check" wire:click="confirmApprove({{ $payment->id }})">
                                                Approve
                                            </flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item icon="x-mark" variant="danger" wire:click="confirmReject({{ $payment->id }})">
                                                Reject
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                @elseif($payment->status === 'success' && ($payment->receipt || auth()->user()->can('payments.verify')))
                                    @if($payment->receipt && auth()->user()->can('payments.verify'))
                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                            <flux:menu>
                                                <flux:menu.item icon="printer" href="{{ route('cms.invoices.receipt.print', $payment->receipt->receipt_number) }}" target="_blank">
                                                    Print Receipt
                                                </flux:menu.item>
                                                <flux:menu.separator />
                                                <flux:menu.item icon="x-circle" variant="danger" wire:click="confirmCancel({{ $payment->id }})">
                                                    Cancel
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    @elseif($payment->receipt)
                                        <flux:button variant="ghost" size="sm" icon="printer"
                                            href="{{ route('cms.invoices.receipt.print', $payment->receipt->receipt_number) }}"
                                            target="_blank" class="text-blue-600 hover:text-blue-700">Print Receipt</flux:button>
                                    @elseif(auth()->user()->can('payments.verify'))
                                        <flux:button variant="subtle" size="sm" wire:click="confirmCancel({{ $payment->id }})"
                                            class="text-amber-600 hover:text-amber-700">Cancel</flux:button>
                                    @endif
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="py-12 text-center text-zinc-500">No payments found.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:card>

    <!-- Approval Confirmation Modal -->
    <flux:modal name="confirm-approval" class="min-w-[400px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Approve Payment?</flux:heading>
                <flux:subheading>Are you sure you want to approve this payment? This will update the student's balance
                    and generate a receipt.</flux:subheading>
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
                <flux:subheading>Are you sure you want to reject this payment record? This action will mark it as
                    failed.</flux:subheading>
            </div>

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="reject">Confirm Rejection</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Cancellation Confirmation Modal -->
    <flux:modal name="confirm-cancellation" class="min-w-[400px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Cancel Payment?</flux:heading>
                <flux:subheading>Are you sure you want to cancel this payment? It will be reverted from success to pending, the student's invoice will be updated, and this action will be recorded in the activity log.</flux:subheading>
            </div>

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">Close</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="cancelPayment">Confirm Cancellation</flux:button>
            </div>
        </div>
    </flux:modal>
</div>