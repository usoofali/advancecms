<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\StudentInvoice;
use App\Services\StudentInvoiceService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('My Invoices')] class extends Component {
    public function mount(): void
    {
        Gate::authorize('invoices.view_personal');
    }

    public $selectedInvoice;

    public $viewingReceipt;

    public float $paymentAmount = 0;

    public string $paymentMethod = 'bank_transfer';

    public string $paymentReference = '';

    public ?int $generatingInvoiceId = null;

    public ?int $editingPaymentId = null;

    public ?int $deletingPaymentId = null;

    public ?int $selectedInvoiceId = null;

    public float $payingAmount = 0;

    public string $search = '';

    public string $filterStatus = '';

    public string $filterSession = '';

    public function student()
    {
        return Auth::user()->student;
    }

    public function materializedInvoices()
    {
        return StudentInvoice::where('student_id', $this->student()?->id)
            ->with(['invoice.items', 'invoice.academicSession', 'payments.receipt'])
            ->when($this->search, function ($query) {
                $query->whereHas('invoice', function ($q) {
                    $q->where('title', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterStatus, function ($query) {
                $query->where('status', $this->filterStatus);
            })
            ->when($this->filterSession, function ($query) {
                $query->whereHas('invoice', function ($q) {
                    $q->where('academic_session_id', $this->filterSession);
                });
            })
            ->latest()
            ->get();
    }

    public function sessions()
    {
        return \App\Models\AcademicSession::active()->latest()->get();
    }

    public function availableInvoices()
    {
        $student = $this->student();
        if (!$student) {
            return collect();
        }

        $service = app(StudentInvoiceService::class);

        return $service->getAvailableInvoices($student);
    }

    public function selectInvoice(StudentInvoice $invoice)
    {
        $this->editingPaymentId = null;
        $this->selectedInvoice = $invoice;
        $this->paymentAmount = $invoice->balance;
        $this->paymentMethod = 'bank_transfer';
        $this->paymentReference = '';

        $this->js('$flux.modal("record-payment").show()');
    }

    public function editPayment(Payment $payment)
    {
        if ($payment->status !== 'pending') {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Only pending payments can be edited.',
            ]);

            return;
        }

        $this->editingPaymentId = $payment->id;
        $this->selectedInvoice = $payment->studentInvoice;
        $this->paymentAmount = $payment->amount_paid;
        $this->paymentMethod = $payment->payment_method;
        $this->paymentReference = $payment->reference;

        $this->js('$flux.modal("record-payment").show()');
    }

    public function confirmGenerate($id)
    {
        $this->generatingInvoiceId = $id;
        $this->js('$flux.modal("confirm-generation").show()');
    }

    public function generate()
    {
        if (!$this->generatingInvoiceId) {
            return;
        }

        $invoice = Invoice::find($this->generatingInvoiceId);
        $student = $this->student();

        if ($student && $invoice) {
            $service = app(StudentInvoiceService::class);
            $service->materializeInvoice($student, $invoice);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Invoice generated successfully.',
            ]);
        }

        $this->generatingInvoiceId = null;
        $this->js('$flux.modal("confirm-generation").close()');
    }

    public function submitPayment()
    {
        $maxAmount = $this->selectedInvoice ? $this->selectedInvoice->balance : 0;

        $this->validate([
            'paymentAmount' => ['required', 'numeric', 'min:1', 'max:' . $maxAmount],
            'paymentReference' => 'required|string|max:255',
            'paymentMethod' => 'required|string',
        ]);

        $data = [
            'institution_id' => $this->selectedInvoice->institution_id,
            'amount_paid' => $this->paymentAmount,
            'payment_method' => $this->paymentMethod,
            'payment_type' => 'manual',
            'reference' => $this->paymentReference,
            'status' => 'pending',
        ];

        if ($this->editingPaymentId) {
            $payment = Payment::find($this->editingPaymentId);
            $payment->update($data);
            $message = 'Payment updated successfully.';
        } else {
            $this->selectedInvoice->payments()->create($data);
            $message = 'Payment recorded and pending verification.';
        }

        $this->js('$flux.modal("record-payment").close()');
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $message,
        ]);

        $this->selectedInvoice = null;
        $this->editingPaymentId = null;
    }

    public function confirmDeletePayment($id)
    {
        $this->deletingPaymentId = $id;
        $this->js('$flux.modal("confirm-payment-deletion").show()');
    }

    public function deletePayment()
    {
        $payment = Payment::find($this->deletingPaymentId);
        if ($payment && $payment->status === 'pending') {
            $payment->delete();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Payment deleted successfully.',
            ]);
        }
        $this->deletingPaymentId = null;
        $this->js('$flux.modal("confirm-payment-deletion").close()');
    }

    public function selectInvoicePaymentAmount(StudentInvoice $invoice)
    {
        $this->selectedInvoiceId = $invoice->id;
        $this->payingAmount = $invoice->balance;
        $this->js('$flux.modal("online-payment-amount").show()');
    }

    public function payOnline()
    {
        $invoice = StudentInvoice::find($this->selectedInvoiceId);

        if (!$invoice)
            return;

        // 1. Basic validation
        if ($invoice->balance <= 0) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'This invoice is already fully paid.',
            ]);
            $this->js('$flux.modal("online-payment-amount").close()');
            return;
        }

        if ($this->payingAmount < 1000) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Minimum online payment amount is ₦1,000.',
            ]);
            return;
        }

        if ($this->payingAmount > $invoice->balance) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Payment amount cannot exceed the balance of ₦' . number_format($invoice->balance, 2),
            ]);
            return;
        }

        // 2. Check for missing config to prevent silent failures
        if (!config('services.paystack.secret_key')) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Paystack gateway is not configured. Please contact the administrator.',
            ]);
            return;
        }

        // 3. Prevent duplicate pending automated payments by cleaning them up
        $invoice->payments()
            ->where('status', 'pending')
            ->where('payment_type', 'automated')
            ->where('payment_method', 'paystack')
            ->delete();

        // 4. Create the payment record
        $payment = $invoice->payments()->create([
            'institution_id' => $invoice->institution_id,
            'amount_paid' => $this->payingAmount,
            'payment_method' => 'paystack',
            'payment_type' => 'automated',
            'reference' => 'PYS-' . strtoupper(\Illuminate\Support\Str::random(12)),
            'status' => 'pending',
        ]);

        try {
            $paystackService = app(\App\Services\PaystackService::class);
            $result = $paystackService->initializeTransaction($payment);

            if ($result && isset($result['checkout_url'])) {
                $payment->update(['gateway_order_no' => $result['gateway_order_no'] ?? $result['gateway_reference'] ?? null]);

                return redirect()->away($result['checkout_url']);
            }

            // If we get here, initialization failed returned null or missing data
            throw new \Exception('Failed to get checkout URL from Paystack.');

        } catch (\Exception $e) {
            // 5. Cleanup the "ghost" record on failure
            $payment->delete();

            \Illuminate\Support\Facades\Log::error('Paystack Checkout Error: ' . $e->getMessage());

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Could not initialize Paystack transaction: ' . $e->getMessage(),
            ]);
        }
    }

    public function viewReceipt(StudentInvoice $invoice)
    {
        // For simplicity, we show the latest success receipt for this invoice
        $payment = $invoice->payments()->where('status', 'success')->latest()->first();
        if ($payment && $payment->receipt) {
            $this->viewingReceipt = $payment->receipt;
            $this->js('$flux.modal("view-receipt").show()');
        } else {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'No receipt found for this invoice.',
            ]);
        }
    }
};
?>

<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">My Invoices</flux:heading>
            <flux:subheading>View and pay your school fees and other obligations.</flux:subheading>
        </div>
    </div>

    @if(!$this->student())
        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-xl flex items-center gap-3 text-yellow-700">
            <flux:icon.exclamation-triangle class="size-5" />
            <div class="text-sm font-semibold tracking-tight">
                Your account is not correctly linked to a student record. Please contact the administrator.
            </div>
        </div>
    @else
        <div class="space-y-6">
            <!-- Active Invoices Section -->
            <section class="space-y-4">
                <div class="flex flex-col md:flex-row md:items-end gap-4">
                    <div class="flex-1">
                        <flux:heading size="lg">Current Invoices</flux:heading>
                        <flux:text size="sm" class="text-zinc-500">Filter and manage your academic financial records.
                        </flux:text>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center gap-3">
                        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search invoices..."
                            icon="magnifying-glass" class="w-full sm:w-64" />

                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <flux:select wire:model.live="filterStatus" class="w-full sm:w-36">
                                <flux:select.option value="">All Statuses</flux:select.option>
                                <flux:select.option value="paid">Paid</flux:select.option>
                                <flux:select.option value="partial">Partial</flux:select.option>
                                <flux:select.option value="pending">Pending</flux:select.option>
                                <flux:select.option value="cancelled">Cancelled</flux:select.option>
                            </flux:select>

                            <flux:select wire:model.live="filterSession" class="w-full sm:w-44">
                                <flux:select.option value="">All Sessions</flux:select.option>
                                @foreach($this->sessions() as $session)
                                    <flux:select.option value="{{ $session->id }}">{{ $session->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6">
                    @forelse ($this->materializedInvoices() as $studentInvoice)
                            <flux:card
                                class="!p-0 overflow-hidden border-zinc-200 dark:border-zinc-800 shadow-sm transition-all hover:shadow-md">
                                <!-- Invoice Header Summary -->
                                <!-- Invoice Header Summary -->
                                <div class="p-5 md:p-6 space-y-6">
                                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                                        <div class="space-y-1">
                                            <flux:text weight="semibold" class="text-lg md:text-xl text-zinc-900 dark:text-white leading-tight">
                                                {{ $studentInvoice->invoice->title }}
                                            </flux:text>
                                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-zinc-500">
                                                <flux:text size="xs" class="flex items-center gap-1.5 whitespace-nowrap">
                                                    <flux:icon.identification class="size-3.5" />
                                                    #INV-{{ $studentInvoice->id }}
                                                </flux:text>
                                                <flux:text size="xs" class="flex items-center gap-1.5 whitespace-nowrap">
                                                    <flux:icon.calendar class="size-3.5" />
                                                    Due: {{ $studentInvoice->invoice?->due_date?->format('M d, Y') ?? 'N/A' }}
                                                </flux:text>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-3">
                                            <flux:badge
                                                :variant="$studentInvoice->status === 'paid' ? 'success' : ($studentInvoice->status === 'cancelled' ? 'danger' : ($studentInvoice->status === 'unpaid' ? 'warning' : 'neutral'))"
                                                size="sm" class="px-3 py-1">
                                                {{ ucfirst($studentInvoice->status) }}
                                            </flux:badge>
                                        </div>
                                    </div>

                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 pt-4 border-t border-zinc-100 dark:border-zinc-800/50">
                                        <div class="space-y-1">
                                            <flux:text weight="bold" class="text-2xl md:text-3xl text-zinc-900 dark:text-white">
                                                ₦{{ number_format($studentInvoice->total_amount, 2) }}
                                            </flux:text>
                                            @if($studentInvoice->balance > 0)
                                                <flux:text size="xs" class="text-zinc-500 flex items-center gap-1.5">
                                                    <span class="inline-block size-2 rounded-full bg-red-400 animate-pulse"></span>
                                                    Balance: <span class="font-bold text-zinc-700 dark:text-zinc-300">₦{{ number_format($studentInvoice->balance, 2) }}</span>
                                                </flux:text>
                                            @else
                                                <flux:text size="xs" class="text-success-600 flex items-center gap-1 font-medium">
                                                    <flux:icon.check-circle variant="micro" class="size-4" />
                                                    Fully Paid
                                                </flux:text>
                                            @endif
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                            @if($studentInvoice->status !== 'paid' && $studentInvoice->status !== 'cancelled')
                                                <flux:button variant="subtle" size="sm" icon="printer"
                                                    href="{{ route('cms.invoices.print', $studentInvoice->id) }}" target="_blank">
                                                    {{ __('Print') }}
                                                </flux:button>
                                                <flux:button variant="primary" size="sm"
                                                    wire:click="selectInvoicePaymentAmount({{ $studentInvoice->id }})"
                                                    icon="credit-card">
                                                    {{ __('Pay Online') }}
                                                </flux:button>
                                                <flux:button variant="ghost" size="sm"
                                                    wire:click="selectInvoice({{ $studentInvoice->id }})"
                                                    icon="document-plus">
                                                    {{ __('Manual') }}
                                                </flux:button>
                                            @elseif($studentInvoice->status === 'paid')
                                                <flux:button variant="subtle" size="sm" icon="printer"
                                                    href="{{ route('cms.invoices.print', $studentInvoice->id) }}" target="_blank">
                                                    {{ __('Print Invoice') }}
                                                </flux:button>
                                                @php
                                                    $latestPayment = $studentInvoice->payments->where('status', 'success')->sortByDesc('created_at')->first();
                                                @endphp
                                                @if($latestPayment && $latestPayment->receipt)
                                                    <flux:button variant="primary" size="sm" icon="printer"
                                                        href="{{ route('cms.invoices.receipt.print', $latestPayment->receipt->receipt_number) }}"
                                                        target="_blank">
                                                        {{ __('Latest Receipt') }}
                                                    </flux:button>
                                                @endif
                                            @else
                                                <flux:button variant="subtle" size="sm" icon="printer"
                                                    href="{{ route('cms.invoices.print', $studentInvoice->id) }}" target="_blank">
                                                    {{ __('Print Invoice') }}
                                                </flux:button>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Payment History Segment -->
                                @if($studentInvoice->payments->isNotEmpty())
                                    <div
                                        class="border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/40 p-2 md:p-4">
                                        <div class="px-2 pb-2">
                                            <flux:text size="xs" weight="semibold" class="uppercase tracking-wider text-zinc-400">
                                                Payment History</flux:text>
                                        </div>
                                        <div class="space-y-2">
                                            @foreach($studentInvoice->payments as $payment)
                                                            <div class="group py-4 px-4 bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200/60 dark:border-zinc-700/60 shadow-sm transition-all hover:border-zinc-300 dark:hover:border-zinc-600">
                                                                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                                                                    <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-6">
                                                                        <div class="flex items-center gap-3">
                                                                            <flux:badge size="sm"
                                                                                :color="$payment->status === 'success' ? 'green' : ($payment->status === 'failed' ? 'red' : 'neutral')"
                                                                                class="w-20 justify-center">
                                                                                {{ ucfirst($payment->status) }}
                                                                            </flux:badge>
                                                                            <flux:text weight="semibold" class="text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                                                                                ₦{{ number_format($payment->amount_paid, 2) }}
                                                                            </flux:text>
                                                                        </div>

                                                                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-500">
                                                                            <span class="flex items-center gap-1.5">
                                                                                <flux:icon.credit-card class="size-3.5 opacity-70" />
                                                                                {{ str_replace('_', ' ', ucfirst($payment->payment_method)) }}
                                                                            </span>
                                                                            <span class="flex items-center gap-1.5">
                                                                                <flux:icon.hashtag class="size-3.5 opacity-70" />
                                                                                {{ $payment->reference }}
                                                                            </span>
                                                                            <span class="flex items-center gap-1.5">
                                                                                <flux:icon.clock class="size-3.5 opacity-70" />
                                                                                {{ $payment->created_at->format('M d, H:i') }}
                                                                            </span>
                                                                        </div>
                                                                    </div>

                                                                    <div class="flex items-center gap-2 pt-3 lg:pt-0 border-t lg:border-t-0 border-zinc-100 dark:border-zinc-800 lg:opacity-0 group-hover:opacity-100 transition-opacity">
                                                                        @if($payment->status === 'pending')
                                                                            <flux:button variant="ghost" size="xs" icon="pencil"
                                                                                wire:click="editPayment({{ $payment->id }})">Edit</flux:button>
                                                                            <flux:button variant="ghost" size="xs" icon="trash" color="red"
                                                                                wire:click="confirmDeletePayment({{ $payment->id }})">Delete</flux:button>
                                                                        @elseif($payment->status === 'success' && $payment->receipt)
                                                                            <flux:button variant="subtle" size="xs" icon="printer"
                                                                                href="{{ route('cms.invoices.receipt.print', $payment->receipt->receipt_number) }}"
                                                                                target="_blank">Receipt</flux:button>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </flux:card>
                    @empty
                        <flux:card class="py-8 text-center">
                            <flux:text class="text-zinc-500">You have no active invoices at the moment.</flux:text>
                        </flux:card>
                    @endforelse
                </div>
            </section>

            <!-- Available Templates Section -->
            @php $available = $this->availableInvoices(); @endphp
            @if($available->isNotEmpty())
                <section class="space-y-4">
                    <flux:heading size="lg">Available for Generation</flux:heading>
                    <flux:subheading>The following fees are applicable to you but have not been generated yet.</flux:subheading>

                    <div class="grid grid-cols-1 gap-4">
                        @foreach ($available as $invoice)
                            <flux:card class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-dashed">
                                <div class="space-y-0.5">
                                    <flux:text weight="medium">{{ $invoice->title }}</flux:text>
                                    <flux:text size="sm" class="text-zinc-500">{{ $invoice->academicSession?->name }}</flux:text>
                                </div>

                                <div class="flex items-center gap-4 sm:gap-6 justify-between sm:justify-end">
                                    <flux:text weight="semibold" class="text-lg">₦{{ number_format($invoice->total_amount, 2) }}
                                    </flux:text>
                                    <flux:button variant="subtle" size="sm" icon="plus"
                                        wire:click="confirmGenerate({{ $invoice->id }})">
                                        Generate Invoice
                                    </flux:button>
                                </div>
                            </flux:card>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <!-- Payment Modal -->
        <flux:modal name="record-payment" class="w-full max-w-lg">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $editingPaymentId ? 'Edit Payment Record' : 'Record Manual Payment' }}
                    </flux:heading>
                    <flux:subheading>{{ $editingPaymentId ? 'Update the details of your submitted payment.' : 'Record
                        details of your bank transfer or deposit.' }}</flux:subheading>
                </div>

                @if($selectedInvoice)
                    <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg space-y-1">
                        <flux:text size="sm" class="text-zinc-500">Invoice: {{ $selectedInvoice->invoice->title }}</flux:text>
                        <flux:text size="sm" class="text-zinc-500">Total: ₦{{ number_format($selectedInvoice->total_amount, 2)
                            }}</flux:text>
                        <flux:text size="sm" weight="medium">Balance Due: ₦{{ number_format($selectedInvoice->balance, 2) }}
                        </flux:text>
                    </div>
                @endif

                <form wire:submit="submitPayment" class="space-y-6">
                    <flux:field>
                        <flux:label>Amount to Pay</flux:label>
                        <flux:input type="number" wire:model="paymentAmount" prefix="₦" step="0.01"
                            max="{{ $selectedInvoice ? $selectedInvoice->balance : '' }}" />
                        <flux:error name="paymentAmount" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Payment Method</flux:label>
                        <flux:select wire:model="paymentMethod">
                            <flux:select.option value="bank_transfer">Bank Transfer / Deposit</flux:select.option>
                            <flux:select.option value="pos">POS Terminal</flux:select.option>
                            <flux:select.option value="cash">Cash (at Bursary)</flux:select.option>
                            <flux:select.option value="paystack">Paystack / Fintech</flux:select.option>
                        </flux:select>
                        <flux:error name="paymentMethod" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Payment Reference / Evidence</flux:label>
                        <flux:input wire:model="paymentReference" placeholder="e.g. Bank Teller No, Transfer Ref" />
                        <flux:error name="paymentReference" />
                    </flux:field>

                    <div class="flex items-center justify-end gap-3">
                        <flux:modal.close>
                            <flux:button variant="ghost">Cancel</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" variant="primary">{{ $editingPaymentId ? 'Update' : 'Submit' }} Payment
                        </flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>

        <!-- Receipt Modal -->
        <flux:modal name="view-receipt" class="w-full max-w-xl">
            <div class="space-y-8 p-4">
                <div class="text-center space-y-2">
                    <flux:heading size="xl">{{ Auth::user()->institution->name ?? 'School Receipt' }}</flux:heading>
                    <flux:subheading>OFFICIAL PAYMENT RECEIPT</flux:subheading>
                </div>

                @if($viewingReceipt)
                    <div class="grid grid-cols-2 gap-8 border-y border-zinc-100 dark:border-zinc-800 py-6">
                        <div class="space-y-4">
                            <div>
                                <flux:text size="xs" class="text-zinc-500 uppercase tracking-wider">Student Name</flux:text>
                                <flux:text weight="medium">{{ $viewingReceipt->student->full_name }}</flux:text>
                            </div>
                            <div>
                                <flux:text size="xs" class="text-zinc-500 uppercase tracking-wider">Matric Number</flux:text>
                                <flux:text weight="medium">{{ $viewingReceipt->student->matric_number }}</flux:text>
                            </div>
                        </div>
                        <div class="text-right space-y-4">
                            <div>
                                <flux:text size="xs" class="text-zinc-500 uppercase tracking-wider">Receipt No</flux:text>
                                <flux:text weight="medium" class="text-blue-600">{{ $viewingReceipt->receipt_number }}
                                </flux:text>
                            </div>
                            <div>
                                <flux:text size="xs" class="text-zinc-500 uppercase tracking-wider">Date Issued</flux:text>
                                <flux:text weight="medium">{{ $viewingReceipt->issued_at?->format('M d, Y') ?? 'N/A' }}
                                </flux:text>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between py-2 border-b border-zinc-100 dark:border-zinc-800">
                            <flux:text>Description</flux:text>
                            <flux:text>Amount</flux:text>
                        </div>
                        <div class="flex items-center justify-between">
                            <flux:text weight="medium">{{ $viewingReceipt->payment->studentInvoice->invoice->title }}
                            </flux:text>
                            <flux:text weight="semibold">₦{{ number_format($viewingReceipt->amount, 2) }}</flux:text>
                        </div>
                    </div>

                    <div class="pt-8 text-center space-y-4">
                        <flux:text size="sm" class="text-zinc-500 italic">This is a computer-generated receipt, no signature is
                            required.</flux:text>
                        <div class="flex justify-center gap-4 no-print">
                            <flux:button icon="printer" variant="subtle"
                                href="{{ route('cms.invoices.receipt.print', $viewingReceipt->receipt_number) }}"
                                target="_blank">Print Official Receipt</flux:button>
                            <flux:modal.close>
                                <flux:button variant="ghost">Close</flux:button>
                            </flux:modal.close>
                        </div>
                    </div>
                @endif
            </div>
        </flux:modal>

        <!-- Generation Confirmation Modal -->
        <flux:modal name="confirm-generation" class="w-full max-w-md">
            <form wire:submit="generate" class="space-y-6">
                <div>
                    <flux:heading size="lg">Generate This Invoice?</flux:heading>
                    <flux:subheading>This will create a personalized record of this fee for you to pay. This action is
                        usually required for registration.</flux:subheading>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Confirm & Generate</flux:button>
                </div>
            </form>
        </flux:modal>

        <!-- Payment Deletion Confirmation Modal -->
        <flux:modal name="confirm-payment-deletion" class="w-full max-w-md">
            <form wire:submit="deletePayment" class="space-y-6">
                <div>
                    <flux:heading size="lg">Delete Payment Record?</flux:heading>
                    <flux:subheading>Are you sure you want to delete this payment record? This action cannot be undone
                        unless you re-submit it.</flux:subheading>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger">Confirm Delete</flux:button>
                </div>
            </form>
        </flux:modal>
        <flux:modal name="online-payment-amount" class="w-full max-w-md">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Pay Online via Paystack</flux:heading>
                    <flux:subheading>Enter the amount you wish to pay. Minimum ₦1,000.</flux:subheading>
                </div>

                <flux:input label="Amount to Pay (₦)" type="number" wire:model="payingAmount" min="1000" step="0.01" />

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:button variant="ghost" x-on:click="$flux.modal('online-payment-amount').close()">Cancel
                    </flux:button>
                    <flux:button variant="primary" wire:click="payOnline" wire:loading.attr="disabled">Proceed to Paystack
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>