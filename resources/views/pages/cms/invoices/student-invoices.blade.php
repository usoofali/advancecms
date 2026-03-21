<?php

use App\Models\Invoice;
use App\Models\StudentInvoice;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] #[Title('Manage Student Invoices')] class extends Component
{
    use WithPagination;

    public Invoice $invoice;

    public string $search = '';

    public string $statusFilter = 'all';
    
    public string $levelFilter = 'all';

    public ?int $actionId = null;

    public string $actionType = ''; // 'cancel' or 'delete'

    public bool $isGenerating = false;

    public string $generationLevelTarget = 'all';

    public function mount(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function studentInvoices()
    {
        $sessionYear = (int) explode('/', $this->invoice->academicSession->name)[0];

        return $this->invoice->studentInvoices()
            ->with(['student', 'applicant'])
            ->when($this->search, function ($q) {
                $q->where(function ($qq) {
                    $qq->whereHas('student', function ($sq) {
                        $sq->where('first_name', 'like', '%'.$this->search.'%')
                            ->orWhere('last_name', 'like', '%'.$this->search.'%')
                            ->orWhere('matric_number', 'like', '%'.$this->search.'%');
                    })->orWhereHas('applicant', function ($aq) {
                        $aq->where('full_name', 'like', '%'.$this->search.'%')
                            ->orWhere('application_number', 'like', '%'.$this->search.'%');
                    });
                });
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->levelFilter !== 'all', function ($q) use ($sessionYear) {
                $q->where(function ($qq) use ($sessionYear) {
                    $qq->whereHas('student', function ($sq) use ($sessionYear) {
                        $level = (int) $this->levelFilter;
                        $sq->whereRaw('admission_year = ? - ((? - entry_level) / 100)', [$sessionYear, $level]);
                    })->orWhere(function ($aqq) {
                        // For applicants, we might only support '100' level filtering since they aren't students yet
                        if ($this->levelFilter == '100') {
                            $aqq->whereNotNull('applicant_id');
                        } else {
                            $aqq->whereRaw('1 = 0'); // Impossible match for other levels
                        }
                    });
                });
            })
            ->latest()
            ->paginate(20);
    }

    public function getSummary()
    {
        $sessionYear = (int) explode('/', $this->invoice->academicSession->name)[0];

        $query = $this->invoice->studentInvoices()
            ->when($this->search, function ($q) {
                $q->where(function ($qq) {
                    $qq->whereHas('student', function ($sq) {
                        $sq->where('first_name', 'like', '%'.$this->search.'%')
                            ->orWhere('last_name', 'like', '%'.$this->search.'%')
                            ->orWhere('matric_number', 'like', '%'.$this->search.'%');
                    })->orWhereHas('applicant', function ($aq) {
                        $aq->where('full_name', 'like', '%'.$this->search.'%')
                            ->orWhere('application_number', 'like', '%'.$this->search.'%');
                    });
                });
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->levelFilter !== 'all', function ($q) use ($sessionYear) {
                $q->where(function ($qq) use ($sessionYear) {
                    $qq->whereHas('student', function ($sq) use ($sessionYear) {
                        $level = (int) $this->levelFilter;
                        $sq->whereRaw('admission_year = ? - ((? - entry_level) / 100)', [$sessionYear, $level]);
                    })->orWhere(function ($aqq) {
                        if ($this->levelFilter == '100') {
                            $aqq->whereNotNull('applicant_id');
                        } else {
                            $aqq->whereRaw('1 = 0');
                        }
                    });
                });
            });

        return [
            'total_invoiced' => $query->sum('total_amount'),
            'total_paid' => $query->sum('amount_paid'),
            'total_balance' => $query->sum('balance'),
            'count' => $query->count(),
        ];
    }

    public function confirmAction($type, $id)
    {
        $this->actionType = $type;
        $this->actionId = $id;
        $this->js('$flux.modal("confirm-action").show()');
    }

    public function executeAction()
    {
        if (! $this->actionId) {
            return;
        }

        $studentInvoice = StudentInvoice::find($this->actionId);

        if ($studentInvoice) {
            if ($this->actionType === 'delete') {
                if ($studentInvoice->amount_paid > 0) {
                    $this->dispatch('notify', [
                        'type' => 'error',
                        'message' => 'Cannot delete an invoice with recorded payments.',
                    ]);
                } else {
                    $studentInvoice->delete();
                    $this->dispatch('notify', [
                        'type' => 'success',
                        'message' => 'Student invoice deleted successfully.',
                    ]);
                }
            } elseif ($this->actionType === 'cancel') {
                $studentInvoice->update(['status' => 'cancelled']);
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => 'Student invoice cancelled successfully.',
                ]);
            }
        }

        $this->actionId = null;
        $this->actionType = '';
        $this->js('$flux.modal("confirm-action").close()');
    }

    public function exportCsv()
    {
        $sessionYear = (int) explode('/', $this->invoice->academicSession->name)[0];
        
        $invoices = $this->invoice->studentInvoices()
            ->with(['student', 'student.program', 'applicant', 'applicant.program'])
            ->when($this->search, function ($q) {
                $q->where(function ($qq) {
                    $qq->whereHas('student', function ($sq) {
                        $sq->where('first_name', 'like', '%'.$this->search.'%')
                            ->orWhere('last_name', 'like', '%'.$this->search.'%')
                            ->orWhere('matric_number', 'like', '%'.$this->search.'%');
                    })->orWhereHas('applicant', function ($aq) {
                        $aq->where('full_name', 'like', '%'.$this->search.'%')
                            ->orWhere('application_number', 'like', '%'.$this->search.'%');
                    });
                });
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->levelFilter !== 'all', function ($q) use ($sessionYear) {
                $q->where(function ($qq) use ($sessionYear) {
                    $qq->whereHas('student', function ($sq) use ($sessionYear) {
                        $level = (int) $this->levelFilter;
                        $sq->whereRaw('admission_year = ? - ((? - entry_level) / 100)', [$sessionYear, $level]);
                    })->orWhere(function ($aqq) {
                        if ($this->levelFilter == '100') {
                            $aqq->whereNotNull('applicant_id');
                        } else {
                            $aqq->whereRaw('1 = 0');
                        }
                    });
                });
            })
            ->get();

        $filename = "invoices_" . Str::slug($this->invoice->title) . "_" . now()->format('YmdHis') . ".csv";
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($invoices) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Student Name', 'Matric Number', 'Program', 'Level', 'Invoiced (NGN)', 'Paid (NGN)', 'Balance (NGN)', 'Status']);

            foreach ($invoices as $row) {
                fputcsv($file, [
                    $row->student ? $row->student->full_name : $row->applicant->full_name,
                    $row->student ? $row->student->matric_number : $row->applicant->application_number,
                    $row->student ? $row->student->program?->name : $row->applicant->program?->name,
                    $row->student ? $row->student->currentLevel($this->invoice->academicSession) : '100 (Applicant)',
                    $row->total_amount,
                    $row->amount_paid,
                    $row->balance,
                    ucfirst($row->status),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function openGenerationModal()
    {
        $this->generationLevelTarget = 'all';
        $this->js('$flux.modal("force-generation").show()');
    }

    public function generateInvoices(\App\Services\StudentInvoiceService $service)
    {
        $this->isGenerating = true;

        try {
            $count = $service->forceGenerate($this->invoice, $this->generationLevelTarget);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Successfully generated {$count} new invoices for eligible students.",
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'An error occurred during generation: ' . $e->getMessage(),
            ]);
        }

        $this->isGenerating = false;
        $this->js('$flux.modal("force-generation").close()');
        $this->resetPage(); // Refresh list to show new students
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <flux:button icon="chevron-left" variant="ghost" :href="route('cms.invoices.index')" wire:navigate />
            <div>
                <flux:heading size="xl">Invoices for: {{ $invoice->title }}</flux:heading>
                <flux:subheading>Manage individual student records for this template.</flux:subheading>
            </div>
        </div>
        
        <div class="flex items-center gap-2">
            <flux:button variant="subtle" icon="bolt" wire:click="openGenerationModal">
                Force Generate
            </flux:button>
            <flux:button icon="document-text" variant="ghost" wire:click="exportCsv">
                Export CSV
            </flux:button>
            <flux:button icon="printer" variant="primary" 
                @click.prevent="window.open('{{ route('cms.invoices.print-report', ['invoice' => $invoice->id]) }}' + '?search=' + encodeURIComponent($wire.search) + '&status=' + $wire.statusFilter + '&level=' + $wire.levelFilter + '&institution_id={{ $invoice->institution_id }}', '_blank')"
            >
                Print Filtered Report
            </flux:button>
        </div>
    </div>

    @php $summary = $this->getSummary(); @endphp
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <flux:card class="space-y-1">
            <flux:text size="sm" class="text-zinc-500 uppercase font-bold tracking-tight">Total Students</flux:text>
            <flux:heading size="xl">{{ number_format($summary['count']) }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm" class="text-zinc-500 uppercase font-bold tracking-tight">Total Invoiced</flux:text>
            <flux:heading size="xl">₦{{ number_format($summary['total_invoiced'], 2) }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1 border-l-4 border-green-500">
            <flux:text size="sm" class="text-zinc-500 uppercase font-bold tracking-tight">Total Paid</flux:text>
            <flux:heading size="xl" class="text-green-600">₦{{ number_format($summary['total_paid'], 2) }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1 border-l-4 border-orange-500">
            <flux:text size="sm" class="text-zinc-500 uppercase font-bold tracking-tight">Outstanding Balance</flux:text>
            <flux:heading size="xl" class="text-orange-600">₦{{ number_format($summary['total_balance'], 2) }}</flux:heading>
        </flux:card>
    </div>
    <div class="flex items-center gap-4">
        <div class="flex-1">
            <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="Search student name or matric..." clearable />
        </div>
        
        <flux:select wire:model.live="levelFilter" :placeholder="__('All Levels')" class="max-w-[150px]">
            <flux:select.option value="all">All Levels</flux:select.option>
            <flux:select.option value="100">100 Level</flux:select.option>
            <flux:select.option value="200">200 Level</flux:select.option>
            <flux:select.option value="300">300 Level</flux:select.option>
            <flux:select.option value="400">400 Level</flux:select.option>
            <flux:select.option value="500">500 Level</flux:select.option>
        </flux:select>
        
        <flux:select wire:model.live="statusFilter" :placeholder="__('Filter Status')" class="max-w-[150px]">
            <flux:select.option value="all">All Statuses</flux:select.option>
            <flux:select.option value="pending">Pending</flux:select.option>
            <flux:select.option value="partial">Partial</flux:select.option>
            <flux:select.option value="paid">Paid</flux:select.option>
            <flux:select.option value="cancelled">Cancelled</flux:select.option>
        </flux:select>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-zinc-200 dark:border-zinc-700">
                    <th class="py-3 px-4 font-semibold text-zinc-900 dark:text-zinc-100 uppercase text-xs tracking-wider">Student</th>
                    <th class="py-3 px-4 font-semibold text-zinc-900 dark:text-zinc-100 uppercase text-xs tracking-wider text-right">Invoiced</th>
                    <th class="py-3 px-4 font-semibold text-zinc-900 dark:text-zinc-100 uppercase text-xs tracking-wider text-right">Paid</th>
                    <th class="py-3 px-4 font-semibold text-zinc-900 dark:text-zinc-100 uppercase text-xs tracking-wider">Status</th>
                    <th class="py-3 px-4"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @php $paginated = $this->studentInvoices(); @endphp
                @forelse ($paginated as $item)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                        <td class="py-4 px-4">
                            <flux:text weight="medium" class="text-zinc-900 dark:text-white">
                                {{ $item->student ? $item->student->full_name : $item->applicant->full_name }}
                            </flux:text>
                            <flux:text size="sm" class="text-zinc-500">
                                {{ $item->student ? $item->student->matric_number : $item->applicant->application_number }}
                                @if(!$item->student)
                                    <span class="ml-1 text-[10px] px-1 bg-amber-100 text-amber-700 rounded">{{ __('Applicant') }}</span>
                                @endif
                            </flux:text>
                        </td>
                        <td class="py-4 px-4 text-right">₦{{ number_format($item->total_amount, 2) }}</td>
                        <td class="py-4 px-4 text-right">₦{{ number_format($item->amount_paid, 2) }}</td>
                        <td class="py-4 px-4">
                            <flux:badge 
                                :variant="$item->status === 'paid' ? 'success' : ($item->status === 'cancelled' ? 'danger' : 'warning')"
                                size="sm"
                            >
                                {{ ucfirst($item->status) }}
                            </flux:badge>
                        </td>
                        <td class="py-4 px-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button variant="ghost" size="sm" icon="printer"
                                    href="{{ route('cms.invoices.print', $item->id) }}" target="_blank">
                                    Print
                                </flux:button>

                                @if($item->status !== 'cancelled' && $item->status !== 'paid')
                                    <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="confirmAction('cancel', {{ $item->id }})">Cancel</flux:button>
                                @endif
                                
                                @if($item->amount_paid <= 0)
                                    <flux:button variant="ghost" size="sm" icon="trash" variant="danger" wire:click="confirmAction('delete', {{ $item->id }})">Delete</flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center text-zinc-500">No student invoices found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="py-4">
        {{ $paginated->links() }}
    </div>

    <flux:modal name="confirm-action" class="min-w-[400px]">
        <form wire:submit="executeAction" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $actionType === 'delete' ? 'Delete Student Invoice?' : 'Cancel Student Invoice?' }}</flux:heading>
                <flux:subheading>
                    {{ $actionType === 'delete' 
                        ? 'This will permanently remove the record. You can only delete invoices with no payments.' 
                        : 'This will mark the invoice as cancelled. The student will no longer be able to pay for it.' }}
                </flux:subheading>
            </div>

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">Oops, back</flux:button>
                </flux:modal.close>
                <flux:button type="submit" :variant="$actionType === 'delete' ? 'danger' : 'primary'">
                    {{ $actionType === 'delete' ? 'Confirm Delete' : 'Confirm Cancel' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="force-generation" class="min-w-[400px]">
        <form wire:submit="generateInvoices" class="space-y-6">
            <div>
                <flux:heading size="lg">Force Generate Invoices</flux:heading>
                <flux:subheading>
                    This will automatically generate a billing record for all eligible students in the target department/program who don't already have one.
                </flux:subheading>
            </div>

            <flux:field>
                <flux:label>Target Level</flux:label>
                <flux:select wire:model="generationLevelTarget">
                    <flux:select.option value="all">All Eligible Levels</flux:select.option>
                    <flux:select.option value="100">100 Level</flux:select.option>
                    <flux:select.option value="200">200 Level</flux:select.option>
                    <flux:select.option value="300">300 Level</flux:select.option>
                    <flux:select.option value="400">400 Level</flux:select.option>
                    <flux:select.option value="500">500 Level</flux:select.option>
                    <flux:select.option value="600">600 Level</flux:select.option>
                </flux:select>
                <flux:error name="generationLevelTarget" />
            </flux:field>

            <div class="p-4 bg-orange-50 text-orange-700 text-sm rounded-lg border border-orange-200">
                <strong>Note:</strong> Depending on the number of students, this action may take a few moments to complete.
            </div>

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost" :disabled="$isGenerating">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="generateInvoices">Confirm Generation</span>
                    <span wire:loading wire:target="generateInvoices" class="flex items-center gap-2">
                        <flux:icon.arrow-path class="size-4 animate-spin" />
                        Generating...
                    </span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
