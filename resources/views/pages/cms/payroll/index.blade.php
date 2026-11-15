<?php

use App\Models\Institution;
use App\Models\PayrollCategory;
use App\Models\PayrollComponent;
use App\Models\PayrollRun;
use App\Models\PayrollStaffProfile;
use App\Models\Staff;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Payroll Overview')] class extends Component
{
    #[Url]
    public ?int $institution_id = null;

    public function mount(): void
    {
        Gate::authorize('payroll.view');

        $user = auth()->user();
        if (! $user->hasRole('Super Admin')) {
            $this->institution_id = $user->institution_id;
        } elseif (! $this->institution_id) {
            $this->institution_id = Institution::where('status', 'active')->value('id');
        }
    }

    private function resolveInstitutionId(): ?int
    {
        $user = auth()->user();

        return $user->hasRole('Super Admin')
            ? ($this->institution_id ?: Institution::where('status', 'active')->value('id'))
            : $user->institution_id;
    }

    public function with(): array
    {
        $user = auth()->user();
        $instId = $this->resolveInstitutionId();

        $latestRun = $instId
            ? PayrollRun::withCount('items')
                ->where('institution_id', $instId)
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->first()
            : null;

        $recentRuns = $instId
            ? PayrollRun::with(['preparer', 'approver'])
                ->withCount('items')
                ->where('institution_id', $instId)
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->take(6)
                ->get()
            : collect();

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

        <div class="flex flex-wrap items-center gap-2">
            @if(auth()->user()->hasRole('Super Admin'))
                <flux:select wire:model.live="institution_id" class="w-full sm:w-60" :placeholder="__('Select Institution...')">
                    @foreach($institutions as $inst)
                        <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            @can('payroll.manage')
                <flux:button variant="ghost" icon="adjustments-horizontal" :href="route('cms.payroll.settings', ['institution_id' => $institution_id])" wire:navigate class="w-full sm:w-auto">
                    {{ __('Categories & Rules') }}
                </flux:button>
            @endcan

            @can('payroll.create_run')
                <flux:button variant="primary" icon="calculator" :href="route('cms.payroll.runs', ['institution_id' => $institution_id])" wire:navigate class="w-full sm:w-auto">
                    {{ __('Run Monthly Payroll') }}
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Latest Net Payout') }}</span>
                <flux:icon.banknotes class="size-5 text-emerald-500" />
            </div>
            <div class="text-2xl font-black text-zinc-900 dark:text-white mt-2">
                ₦{{ number_format((float) ($latestRun?->total_net ?? 0), 2) }}
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
                ₦{{ number_format((float) ($latestRun?->total_gross ?? 0), 2) }}
            </div>
            <div class="text-xs text-zinc-500 mt-1">
                {{ __('Deductions:') }} ₦{{ number_format((float) ($latestRun?->total_deductions ?? 0), 2) }}
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
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
            <h3 class="font-bold text-zinc-900 dark:text-zinc-100">{{ __('Recent Monthly Runs') }}</h3>
            <flux:button size="xs" variant="ghost" :href="route('cms.payroll.runs', ['institution_id' => $institution_id])" wire:navigate>
                {{ __('View All') }}
            </flux:button>
        </div>

        {{-- Desktop Table --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 text-xs font-semibold uppercase tracking-wider text-zinc-500">
                    <tr>
                        <th class="px-5 py-3">{{ __('Payroll Period') }}</th>
                        <th class="px-5 py-3 text-center">{{ __('Staff') }}</th>
                        <th class="px-5 py-3 text-right">{{ __('Gross Pay') }}</th>
                        <th class="px-5 py-3 text-right">{{ __('Deductions') }}</th>
                        <th class="px-5 py-3 text-right">{{ __('Net Payout') }}</th>
                        <th class="px-5 py-3 text-center">{{ __('Status') }}</th>
                        <th class="px-5 py-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 text-sm">
                    @forelse($recentRuns as $run)
                        <tr wire:key="overview-run-{{ $run->id }}" class="hover:bg-zinc-50/60 dark:hover:bg-zinc-900/20">
                            <td class="px-5 py-3.5">
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $run->title }}</div>
                                <div class="text-xs text-zinc-500">{{ __('Prepared by:') }} {{ $run->preparer?->name ?? __('System') }}</div>
                            </td>
                            <td class="px-5 py-3.5 text-center font-medium">{{ $run->items_count }}</td>
                            <td class="px-5 py-3.5 text-right font-mono text-xs">₦{{ number_format((float) $run->total_gross, 2) }}</td>
                            <td class="px-5 py-3.5 text-right font-mono text-xs text-red-600 dark:text-red-400">₦{{ number_format((float) $run->total_deductions, 2) }}</td>
                            <td class="px-5 py-3.5 text-right font-bold text-emerald-600 dark:text-emerald-400">₦{{ number_format((float) $run->total_net, 2) }}</td>
                            <td class="px-5 py-3.5 text-center">
                                <flux:badge size="sm" :color="$run->statusColor()">{{ __($run->statusLabel()) }}</flux:badge>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <flux:button size="xs" variant="ghost" :href="route('cms.payroll.runs', ['institution_id' => $institution_id, 'run_id' => $run->id])" wire:navigate>
                                    {{ __('Open') }}
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-zinc-500">
                                {{ __('No payroll runs created yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile Cards --}}
        <div class="divide-y divide-zinc-200 dark:divide-zinc-700 md:hidden">
            @forelse($recentRuns as $run)
                <div class="p-4 space-y-2.5">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $run->title }}</div>
                            <div class="text-xs text-zinc-500">{{ $run->items_count }} {{ __('staff') }}</div>
                        </div>
                        <flux:badge size="sm" :color="$run->statusColor()">{{ __($run->statusLabel()) }}</flux:badge>
                    </div>
                    <div class="flex items-center justify-between text-xs pt-2 border-t border-zinc-100 dark:border-zinc-700">
                        <span class="text-zinc-500">{{ __('Net Payout:') }} <strong class="text-emerald-600 dark:text-emerald-400">₦{{ number_format((float) $run->total_net, 2) }}</strong></span>
                        <flux:button size="xs" variant="ghost" :href="route('cms.payroll.runs', ['institution_id' => $institution_id, 'run_id' => $run->id])" wire:navigate>
                            {{ __('Open') }}
                        </flux:button>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-sm text-zinc-500">
                    {{ __('No payroll runs created yet.') }}
                </div>
            @endforelse
        </div>
    </div>
</div>
