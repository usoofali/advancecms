<?php

use App\Models\Institution;
use App\Models\PayrollCategory;
use App\Models\PayrollComponent;
use App\Models\PayrollRun;
use App\Models\PayrollStaffProfile;
use App\Models\Staff;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Payroll Overview')] class extends Component
{
    use WithPagination;

    #[Url]
    public ?int $institution_id = null;

    public bool $showDeleteModal = false;

    public ?int $deleteRunId = null;

    public string $deleteRunTitle = '';

    public function mount(): void
    {
        Gate::authorize('payroll.view');

        $user = auth()->user();
        if (! $user->hasRole('Super Admin')) {
            $this->institution_id = $user->institution_id;
        } elseif (! $this->institution_id) {
            $this->institution_id = PayrollRun::latest('id')->value('institution_id')
                ?? Institution::where('status', 'active')->value('id')
                ?? Institution::value('id');
        }
    }

    public function updatedInstitutionId(): void
    {
        $this->resetPage();
    }

    private function resolveInstitutionId(): ?int
    {
        $user = auth()->user();

        return $user->hasRole('Super Admin')
            ? ($this->institution_id ?: PayrollRun::latest('id')->value('institution_id') ?? Institution::where('status', 'active')->value('id') ?? Institution::value('id'))
            : $user->institution_id;
    }

    public function confirmDeleteRun(int $runId): void
    {
        $instId = $this->resolveInstitutionId();
        $run = PayrollRun::where('institution_id', $instId)->findOrFail($runId);

        if ($run->status === 'paid') {
            return;
        }

        if ($run->status === 'approved') {
            Gate::authorize('payroll.approve');
        } else {
            Gate::authorize('payroll.create_run');
        }

        $this->deleteRunId = $run->id;
        $this->deleteRunTitle = $run->title;
        $this->showDeleteModal = true;
    }

    public function deleteRun(): void
    {
        $instId = $this->resolveInstitutionId();
        $run = PayrollRun::where('institution_id', $instId)->findOrFail($this->deleteRunId);

        if ($run->status === 'paid') {
            $this->showDeleteModal = false;

            return;
        }

        if ($run->status === 'approved') {
            Gate::authorize('payroll.approve');
        } else {
            Gate::authorize('payroll.create_run');
        }

        $title = $run->title;
        ActivityLogger::log(
            action: 'deleted',
            module: 'Payroll',
            description: "Deleted payroll run: {$title}",
            subject: $run,
            institutionId: $instId
        );

        $run->delete();
        $this->showDeleteModal = false;
        $this->deleteRunId = null;
        $this->deleteRunTitle = '';
        $this->dispatch('notify', ['type' => 'success', 'message' => __('Payroll run deleted.')]);
    }

    public function with(): array
    {
        $user = auth()->user();
        $instId = $this->resolveInstitutionId();

        $latestRun = $instId
            ? PayrollRun::withCount('items')
                ->withSum('items as computed_gross', 'gross_pay')
                ->withSum('items as computed_deductions', 'total_deductions')
                ->withSum('items as computed_net', 'net_pay')
                ->where('institution_id', $instId)
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->orderByDesc('id')
                ->first()
            : null;

        $recentRuns = $instId
            ? PayrollRun::with(['preparer', 'approver'])
                ->withCount('items')
                ->withSum('items as computed_gross', 'gross_pay')
                ->withSum('items as computed_deductions', 'total_deductions')
                ->withSum('items as computed_net', 'net_pay')
                ->where('institution_id', $instId)
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->orderByDesc('id')
                ->paginate(10)
            : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);

        $activeStaffCount = $instId
            ? Staff::where('institution_id', $instId)->where('status', 'active')->count()
            : 0;

        $configuredProfilesCount = $instId
            ? PayrollStaffProfile::where('institution_id', $instId)->where('status', 'active')->count()
            : 0;

        $categoriesCount = $instId
            ? PayrollCategory::where('institution_id', $instId)->where('status', 'active')->count()
            : 0;

        $componentsCount = $instId
            ? PayrollComponent::where('institution_id', $instId)->where('is_active', true)->count()
            : 0;

        return [
            'institutions' => $user->hasRole('Super Admin') ? Institution::orderBy('name')->get() : collect(),
            'latestRun' => $latestRun,
            'recentRuns' => $recentRuns,
            'activeStaffCount' => $activeStaffCount,
            'configuredProfilesCount' => $configuredProfilesCount,
            'categoriesCount' => $categoriesCount,
            'componentsCount' => $componentsCount,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Payroll Overview') }}</flux:heading>
            <flux:subheading>{{ __('Manage staff salaries, contact-hour pay, allowances, deductions, and monthly runs') }}</flux:subheading>
        </div>

        @if(auth()->user()->hasRole('Super Admin'))
            <div class="flex items-center">
                <flux:select wire:model.live="institution_id" class="w-full sm:w-60" :placeholder="__('Select Institution...')">
                    @foreach($institutions as $inst)
                        <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Latest Net Payout') }}</span>
                <flux:icon.banknotes class="size-5 text-emerald-500" />
            </div>
            <div class="text-2xl font-black text-zinc-900 dark:text-white mt-2">
                ₦{{ number_format((float) ($latestRun?->computed_net ?? $latestRun?->total_net ?? 0), 2) }}
            </div>
            <div class="text-xs text-zinc-500 mt-1">
                {{ $latestRun ? $latestRun->title : __('No payroll run yet') }}
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Latest Gross Pay') }}</span>
                <flux:icon.chart-bar class="size-5 text-indigo-500" />
            </div>
            <div class="text-2xl font-black text-zinc-900 dark:text-white mt-2">
                ₦{{ number_format((float) ($latestRun?->computed_gross ?? $latestRun?->total_gross ?? 0), 2) }}
            </div>
            <div class="text-xs text-zinc-500 mt-1">
                {{ __('Deductions:') }} ₦{{ number_format((float) ($latestRun?->computed_deductions ?? $latestRun?->total_deductions ?? 0), 2) }}
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Active Staff') }}</span>
                <flux:icon.users class="size-5 text-sky-500" />
            </div>
            <div class="text-2xl font-black text-zinc-900 dark:text-white mt-2">
                {{ number_format($activeStaffCount) }}
            </div>
            <div class="text-xs text-zinc-500 mt-1">
                {{ $configuredProfilesCount }} {{ __('with custom/assigned pay profile') }}
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Active Rules') }}</span>
                <flux:icon.adjustments-horizontal class="size-5 text-amber-500" />
            </div>
            <div class="text-2xl font-black text-zinc-900 dark:text-white mt-2">
                {{ $categoriesCount }} / {{ $componentsCount }}
            </div>
            <div class="text-xs text-zinc-500 mt-1">
                {{ __('Categories / Allowances & Deductions') }}
            </div>
        </div>
    </div>

    {{-- Quick Setup Checklist (shown when institution has no categories yet) --}}
    @if($categoriesCount === 0)
        <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50/70 dark:bg-amber-950/30 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-start gap-3">
                <flux:icon.sparkles class="size-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                <div>
                    <h3 class="text-sm font-bold text-amber-900 dark:text-amber-200">{{ __('Quick Payroll Setup') }}</h3>
                    <p class="text-xs text-amber-700 dark:text-amber-300 mt-0.5">
                        {{ __('Create your staff categories (e.g., Permanent Staff & Casual Lecturers) to start generating monthly payroll runs.') }}
                    </p>
                </div>
            </div>
            @can('payroll.manage')
                <flux:button size="sm" variant="primary" :href="route('cms.payroll.settings', ['institution_id' => $institution_id])" wire:navigate class="shrink-0">
                    {{ __('Setup Categories') }}
                </flux:button>
            @endcan
        </div>
    @endif

    {{-- Recent Payroll Runs --}}
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Recent Monthly Runs') }}</flux:heading>
            <flux:button size="xs" variant="ghost" :href="route('cms.payroll.runs', ['institution_id' => $institution_id])" wire:navigate>
                {{ __('Manage Runs') }}
            </flux:button>
        </div>

        <flux:table :paginate="$recentRuns">
            <flux:table.columns>
                <flux:table.column>{{ __('Payroll Period') }}</flux:table.column>
                <flux:table.column class="text-center">{{ __('Staff') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Gross Pay') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Deductions') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Net Payout') }}</flux:table.column>
                <flux:table.column class="text-center">{{ __('Status') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($recentRuns as $run)
                    <flux:table.row wire:key="overview-run-{{ $run->id }}">
                        <flux:table.cell>
                            <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $run->title }}</div>
                            <div class="text-xs text-zinc-500">{{ __('Prepared by:') }} {{ $run->preparer?->name ?? __('System') }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="text-center font-medium">{{ $run->items_count }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono text-xs">
                            ₦{{ number_format((float) ($run->computed_gross ?? $run->total_gross), 2) }}
                        </flux:table.cell>
                        <flux:table.cell class="text-right font-mono text-xs text-red-600 dark:text-red-400">
                            ₦{{ number_format((float) ($run->computed_deductions ?? $run->total_deductions), 2) }}
                        </flux:table.cell>
                        <flux:table.cell class="text-right font-bold text-emerald-600 dark:text-emerald-400">
                            ₦{{ number_format((float) ($run->computed_net ?? $run->total_net), 2) }}
                        </flux:table.cell>
                        <flux:table.cell class="text-center">
                            <flux:badge size="sm" :color="$run->statusColor()">{{ __($run->statusLabel()) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" aria-label="{{ __('Actions') }}" />

                                <flux:menu>
                                    <flux:menu.item icon="eye" :href="route('cms.payroll.runs', ['institution_id' => $institution_id, 'run_id' => $run->id])" wire:navigate>
                                        {{ __('Open Run') }}
                                    </flux:menu.item>

                                    @can('payroll.reports')
                                        <flux:menu.item icon="building-office" :href="route('cms.payroll.department-sheet', ['run' => $run->id])" wire:navigate>
                                            {{ __('Department Sheet') }}
                                        </flux:menu.item>
                                        <flux:menu.item icon="building-library" :href="route('cms.payroll.bank-schedule', ['run' => $run->id])" wire:navigate>
                                            {{ __('Bank Schedule') }}
                                        </flux:menu.item>
                                    @endcan

                                    @if($run->status !== 'paid' && (($run->status === 'approved' && auth()->user()->can('payroll.approve')) || ($run->status !== 'approved' && auth()->user()->can('payroll.create_run'))))
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDeleteRun({{ $run->id }})">
                                            {{ __('Delete Payroll') }}
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500">
                            {{ __('No payroll runs created yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Delete Payroll Run Modal --}}
    <flux:modal wire:model.self="showDeleteModal" class="max-w-md w-full">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Delete Payroll Run?') }}</flux:heading>
                <flux:subheading>
                    {{ __('Are you sure you want to delete ":title"? All calculated paylines for this period will be removed.', ['title' => $deleteRunTitle]) }}
                </flux:subheading>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" icon="trash" wire:click="deleteRun" wire:loading.attr="disabled">
                    {{ __('Delete Payroll') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
