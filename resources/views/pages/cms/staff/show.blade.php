<?php

use App\Models\PayrollItem;
use App\Models\Staff;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Staff Profile')] class extends Component
{
    public Staff $staff;

    public function mount(Staff $staff): void
    {
        Gate::authorize('staff.view');

        $user = auth()->user();
        if ($user->institution_id && (int) $staff->institution_id !== (int) $user->institution_id) {
            abort(403, 'Unauthorized. This staff member belongs to another institution.');
        }

        $this->staff = $staff->load([
            'institution',
            'role',
            'hodDepartments',
            'payrollProfile.category',
            'payrollProfile.department',
            'user',
        ]);
    }

    public function with(): array
    {
        $recentPayslips = PayrollItem::with('run')
            ->where('staff_id', $this->staff->id)
            ->whereHas('run', fn ($q) => $q->whereIn('status', ['approved', 'paid']))
            ->orderByDesc('id')
            ->take(6)
            ->get();

        return [
            'recentPayslips' => $recentPayslips,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    {{-- Top Navigation & Actions --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-left" :href="route('cms.staff.index')" wire:navigate>
                {{ __('Back to Staff') }}
            </flux:button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @can('staff.edit')
                <flux:button variant="primary" icon="pencil-square" :href="route('cms.staff.edit', $staff->id)" wire:navigate>
                    {{ __('Edit Staff') }}
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Profile Header Card --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6 shadow-xs">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5">
            @if($staff->photo_url)
                <img src="{{ $staff->photo_url }}" alt="{{ $staff->first_name }} {{ $staff->last_name }}"
                    class="size-20 rounded-2xl object-cover border border-zinc-200 dark:border-zinc-700 shrink-0" />
            @else
                <div class="size-20 rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-200 dark:border-indigo-800 flex items-center justify-center text-2xl font-black text-indigo-600 dark:text-indigo-400 shrink-0">
                    {{ strtoupper(substr($staff->first_name, 0, 1).substr($staff->last_name, 0, 1)) }}
                </div>
            @endif

            <div class="flex-1 min-w-0 space-y-1.5">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-2xl font-black text-zinc-900 dark:text-white">
                        {{ $staff->first_name }} {{ $staff->last_name }}
                    </h1>
                    <flux:badge size="sm" :color="$staff->status === 'active' ? 'green' : 'zinc'">
                        {{ ucfirst($staff->status) }}
                    </flux:badge>
                    @if($staff->role)
                        <flux:badge size="sm" color="indigo">{{ $staff->role->role_name }}</flux:badge>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                    <span class="font-mono font-semibold text-zinc-700 dark:text-zinc-300">{{ $staff->staff_number }}</span>
                    @if($staff->designation)
                        <span>&bull; {{ $staff->designation }}</span>
                    @endif
                    @if($staff->institution)
                        <span>&bull; {{ $staff->institution->name }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Details Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Contact & Employment Info --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5 shadow-xs space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-zinc-500 border-b border-zinc-100 dark:border-zinc-700 pb-2">
                {{ __('Contact & Employment Information') }}
            </h2>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs text-zinc-500">{{ __('Email Address') }}</dt>
                    <dd class="font-medium text-zinc-900 dark:text-zinc-100 mt-0.5 break-all">{{ $staff->email ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500">{{ __('Phone Number') }}</dt>
                    <dd class="font-medium text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $staff->phone ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500">{{ __('Designation') }}</dt>
                    <dd class="font-medium text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $staff->designation ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500">{{ __('Portal Account') }}</dt>
                    <dd class="font-medium text-zinc-900 dark:text-zinc-100 mt-0.5">
                        @if($staff->user)
                            <span class="text-emerald-600 dark:text-emerald-400">{{ __('Active') }}</span>
                        @else
                            <span class="text-zinc-400">{{ __('Not Linked') }}</span>
                        @endif
                    </dd>
                </div>
            </dl>

            @if($staff->hodDepartments->isNotEmpty())
                <div class="pt-3 border-t border-zinc-100 dark:border-zinc-700">
                    <div class="text-xs text-zinc-500 mb-1.5">{{ __('Departments Led (HOD)') }}</div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($staff->hodDepartments as $dept)
                            <flux:badge size="sm" color="cyan">{{ $dept->name }}</flux:badge>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Bank & Next of Kin Info --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5 shadow-xs space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-zinc-500 border-b border-zinc-100 dark:border-zinc-700 pb-2">
                {{ __('Bank & Next of Kin') }}
            </h2>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs text-zinc-500">{{ __('Bank Name') }}</dt>
                    <dd class="font-medium text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $staff->bank_name ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500">{{ __('Account Number') }}</dt>
                    <dd class="font-mono font-medium text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $staff->account_number ?: '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs text-zinc-500">{{ __('Account Name') }}</dt>
                    <dd class="font-medium text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $staff->account_name ?: '—' }}</dd>
                </div>
            </dl>

            <div class="pt-3 border-t border-zinc-100 dark:border-zinc-700">
                <div class="text-xs font-semibold text-zinc-500 mb-2">{{ __('Next of Kin') }}</div>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-zinc-500">{{ __('Name') }}</dt>
                        <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $staff->next_of_kin_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500">{{ __('Relationship / Phone') }}</dt>
                        <dd class="font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $staff->next_of_kin_relationship ?: '—' }}
                            @if($staff->next_of_kin_phone)
                                &bull; {{ $staff->next_of_kin_phone }}
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    {{-- Payroll Profile & Recent Payslips (when user has permission) --}}
    @can('payroll.view')
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-700 pb-2">
                <h2 class="text-sm font-bold uppercase tracking-wider text-zinc-500">{{ __('Payroll & Compensation') }}</h2>
                @can('payroll.manage')
                    <flux:button size="xs" variant="ghost" :href="route('cms.payroll.staff', ['search' => $staff->staff_number])" wire:navigate>
                        {{ __('Configure Pay') }}
                    </flux:button>
                @endcan
            </div>

            @php
                $profile = $staff->payrollProfile;
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div>
                    <span class="text-xs text-zinc-500 block">{{ __('Payroll Category') }}</span>
                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $profile?->category?->name ?? __('Institution Default') }}</span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 block">{{ __('Pay Method') }}</span>
                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ ($profile?->effectivePayType() ?? 'fixed') === 'fixed' ? __('Fixed Monthly Salary') : __('Per Contact / Credit Unit') }}
                    </span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 block">{{ __('Base Salary / Rate') }}</span>
                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                        @if(($profile?->effectivePayType() ?? 'fixed') === 'fixed')
                            ₦{{ number_format((float) ($profile?->base_salary ?? 0), 2) }}
                        @else
                            ₦{{ number_format($profile ? $profile->effectiveRate() : (float) ($staff->attendance_allowance ?? 0), 2) }} /rate
                        @endif
                    </span>
                </div>
            </div>

            @if($recentPayslips->isNotEmpty())
                <div class="pt-3 border-t border-zinc-100 dark:border-zinc-700">
                    <div class="text-xs font-semibold text-zinc-500 mb-2">{{ __('Recent Approved Payslips') }}</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($recentPayslips as $slip)
                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 flex items-center justify-between text-xs">
                                <div>
                                    <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $slip->run?->title }}</div>
                                    <div class="text-emerald-600 dark:text-emerald-400 font-mono font-bold">₦{{ number_format((float) $slip->net_pay, 2) }}</div>
                                </div>
                                <flux:button size="xs" variant="ghost" icon="document-text" :href="route('cms.payroll.payslip', ['item' => $slip->id])" target="_blank">
                                    {{ __('View') }}
                                </flux:button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endcan
</div>
