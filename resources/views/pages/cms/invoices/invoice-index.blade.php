<?php

use App\Models\Invoice;
use App\Models\StudentInvoice;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Invoices')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';
    public ?int $institutionFilter = null;
    public ?int $departmentFilter = null;

    public ?int $deletingId = null;

    public function invoices()
    {
        $user = auth()->user();
        
        return Invoice::query()
            ->when($this->search, fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($user->hasRole('Super Admin') && $this->institutionFilter, fn ($q) => $q->where('institution_id', $this->institutionFilter))
            ->when(!$user->hasRole('Super Admin'), fn ($q) => $q->where('institution_id', $user->institution_id))
            ->when($this->departmentFilter, fn ($q) => $q->where('department_id', $this->departmentFilter))
            ->with(['academicSession', 'items', 'institution', 'department'])
            ->withCount('items')
            ->latest()
            ->paginate(10);
    }

    public function getStats(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('Super Admin');

        $query = StudentInvoice::query()
            ->when($isSuperAdmin && $this->institutionFilter, fn ($q) => $q->where('institution_id', $this->institutionFilter))
            ->when(! $isSuperAdmin, fn ($q) => $q->where('institution_id', $user->institution_id))
            ->when($this->departmentFilter, fn ($q) => $q->whereHas('invoice', fn ($iq) => $iq->where('department_id', $this->departmentFilter)));

        $totalInvoiced = (float) (clone $query)->sum('total_amount');
        $totalPaid = (float) (clone $query)->sum('amount_paid');

        return [
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'outstanding' => $totalInvoiced - $totalPaid,
        ];
    }

    public function confirmDelete($id)
    {
        $this->deletingId = $id;
        $this->js('$flux.modal("delete-invoice").show()');
    }

    public function delete()
    {
        if ($this->deletingId) {
            $invoice = Invoice::find($this->deletingId);
            if ($invoice) {
                // Check if student invoices exist
                if ($invoice->studentInvoices()->exists()) {
                    $this->dispatch('notify', [
                        'type' => 'error',
                        'message' => 'Cannot delete template that has already been issued to students.',
                    ]);
                } else {
                    $invoice->items()->delete();
                    $invoice->delete();
                    $this->dispatch('notify', [
                        'type' => 'success',
                        'message' => 'Invoice template deleted successfully.',
                    ]);
                }
            }
            $this->deletingId = null;
            $this->js('$flux.modal("delete-invoice").close()');
        }
    }
    public function updatedInstitutionFilter()
    {
        $this->departmentFilter = null;
        $this->resetPage();
    }

    public function updatedDepartmentFilter()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Invoices</flux:heading>
            <flux:subheading>Manage school fee templates and assignments.</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" :href="route('cms.invoices.create')" wire:navigate>
            New Invoice
        </flux:button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @php $stats = $this->getStats(); @endphp
        <flux:card class="space-y-1">
            <flux:text size="sm" class="text-zinc-500 uppercase tracking-wider">Total Invoiced</flux:text>
            <flux:heading size="xl">₦{{ number_format($stats['total_invoiced'], 2) }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm" class="text-zinc-500 uppercase tracking-wider">Total Paid</flux:text>
            <flux:heading size="xl" class="text-green-600">₦{{ number_format($stats['total_paid'], 2) }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm" class="text-zinc-500 uppercase tracking-wider">Outstanding</flux:text>
            <flux:heading size="xl" class="text-red-600">₦{{ number_format($stats['outstanding'], 2) }}</flux:heading>
        </flux:card>
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <div class="w-full md:w-64">
            <flux:input wire:model.live="search" icon="magnifying-glass" :label="__('Search Invoices')"
                placeholder="Search invoices..." clearable />
        </div>

        @if(auth()->user()->hasRole('Super Admin'))
        <div class="w-full md:w-64">
            <flux:select wire:model.live="institutionFilter" :label="__('Institution')">
                <flux:select.option value="">All Institutions</flux:select.option>
                @foreach(App\Models\Institution::all() as $institution)
                <flux:select.option value="{{ $institution->id }}">{{ $institution->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        @endif

        <div class="w-full md:w-64">
            <flux:select wire:model.live="departmentFilter" :label="__('Department')">
                <flux:select.option value="">All Departments</flux:select.option>
                @php
                $depts = App\Models\Department::query()
                ->when(!auth()->user()->hasRole('Super Admin'), fn($q) => $q->where('institution_id',
                auth()->user()->institution_id))
                ->when(auth()->user()->hasRole('Super Admin') && $this->institutionFilter, fn($q) =>
                $q->where('institution_id', $this->institutionFilter))
                ->get();
                @endphp
                @foreach($depts as $dept)
                <flux:select.option value="{{ $dept->id }}">{{ $dept->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-full md:w-48">
            <flux:select wire:model.live="statusFilter" :label="__('Status')">
                <flux:select.option value="all">All Statuses</flux:select.option>
                <flux:select.option value="published">Published</flux:select.option>
                <flux:select.option value="draft">Draft</flux:select.option>
                <flux:select.option value="archived">Archived</flux:select.option>
            </flux:select>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-zinc-200 dark:border-zinc-700">
                    <th
                        class="py-3 px-4 font-semibold text-zinc-900 dark:text-zinc-100 uppercase text-xs tracking-wider">
                        Title / Session</th>
                    @if(auth()->user()->hasRole('Super Admin'))
                    <th
                        class="py-3 px-4 font-semibold text-zinc-900 dark:text-zinc-100 uppercase text-xs tracking-wider">
                        Institution</th>
                    @endif
                    <th
                        class="py-3 px-4 font-semibold text-zinc-900 dark:text-zinc-100 uppercase text-xs tracking-wider text-center">
                        Items</th>
                    <th
                        class="py-3 px-4 font-semibold text-zinc-900 dark:text-zinc-100 uppercase text-xs tracking-wider text-center">
                        Amount</th>
                    <th
                        class="py-3 px-4 font-semibold text-zinc-900 dark:text-zinc-100 uppercase text-xs tracking-wider">
                        Target</th>
                    <th
                        class="py-3 px-4 font-semibold text-zinc-900 dark:text-zinc-100 uppercase text-xs tracking-wider">
                        Status</th>
                    <th class="py-3 px-4"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @php $paginatedInvoices = $this->invoices(); @endphp
                @if($paginatedInvoices->count() > 0)
                @foreach ($paginatedInvoices as $invoice)
                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                    <td class="py-4 px-4">
                        <flux:text weight="medium" class="text-zinc-900 dark:text-white">{{ $invoice->title }}
                        </flux:text>
                        <flux:text size="sm" class="text-zinc-500">{{ $invoice->academicSession?->name }}</flux:text>
                        @if($invoice->account_number)
                        <div class="mt-1 flex items-center gap-2">
                            <flux:badge size="xs" variant="neutral" icon="credit-card" class="text-[9px]">
                                {{ $invoice->bank_name }}: {{ $invoice->account_number }}
                            </flux:badge>
                        </div>
                        @endif
                    </td>
                    @if(auth()->user()->hasRole('Super Admin'))
                    <td class="py-4 px-4">
                        <flux:text size="sm">{{ $invoice->institution?->acronym ?? $invoice->institution?->name }}
                        </flux:text>
                    </td>
                    @endif
                    <td class="py-4 px-4 text-center">
                        <flux:badge size="sm" inset="top bottom">{{ $invoice->items_count }} items</flux:badge>
                    </td>
                    <td class="py-4 px-4 text-center">
                        <flux:text weight="semibold">₦{{ number_format($invoice->total_amount, 2) }}</flux:text>
                    </td>
                    <td class="py-4 px-4">
                        <flux:text size="sm">
                            @if($invoice->target_type === 'dept')
                            Dept: {{ $invoice->department?->name ?? 'N/A' }}
                            @elseif($invoice->target_type === 'program')
                            Prog: {{ $invoice->program?->name ?? 'N/A' }}
                            @elseif($invoice->target_type === 'level')
                            Level: {{ $invoice->level }}
                            @else
                            Global
                            @endif
                        </flux:text>
                    </td>
                    <td class="py-4 px-4">
                        <flux:badge
                            :variant="$invoice->status === 'published' ? 'success' : ($invoice->status === 'draft' ? 'neutral' : 'warning')"
                            size="sm">
                            {{ ucfirst($invoice->status) }}
                        </flux:badge>
                    </td>
                    <td class="py-4 px-4 text-right">
                        <flux:dropdown>
                            <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />

                            <flux:menu>
                                <flux:menu.item icon="pencil-square" :href="route('cms.invoices.edit', $invoice->id)"
                                    wire:navigate>Edit Template</flux:menu.item>
                                <flux:menu.item icon="users" :href="route('cms.invoices.students', $invoice->id)"
                                    wire:navigate>Manage Student Invoices</flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item variant="danger" icon="trash"
                                    wire:click="confirmDelete({{ $invoice->id }})">
                                    Delete
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </td>
                </tr>
                @endforeach
                @else
                <tr>
                    <td colspan="6" class="py-12 text-center">
                        <flux:text class="text-zinc-500">No invoices found matching your criteria.</flux:text>
                    </td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="py-4 text-zinc-500 italic text-sm">
        {{ $paginatedInvoices->links() }}
    </div>

    <flux:modal name="delete-invoice" class="min-w-[400px]">
        <form wire:submit="delete" class="space-y-6">
            <div>
                <flux:heading size="lg">Delete Invoice Template?</flux:heading>
                <flux:subheading>This action cannot be undone. You can only delete templates that haven't been issued to
                    students yet.</flux:subheading>
            </div>

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">Delete Template</flux:button>
            </div>
        </form>
    </flux:modal>
</div>