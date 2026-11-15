<div class="space-y-6">
    {{-- Top Header --}}
    <div class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-gradient-to-br from-white via-zinc-50 to-indigo-50/60 p-5 shadow-xs dark:border-zinc-700/80 dark:from-zinc-900 dark:via-zinc-900 dark:to-indigo-950/30 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1.5">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($activeSession)
                        <flux:badge color="emerald" size="sm" icon="calendar">
                            {{ $activeSession->name }}
                        </flux:badge>
                    @else
                        <flux:badge color="amber" size="sm" icon="exclamation-triangle">
                            {{ __('No Active Session') }}
                        </flux:badge>
                    @endif
                </div>
                <h1 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-2xl">
                    {{ $is_super_admin ? __('Overview') : ($institution?->name ?? __('Overview')) }}
                </h1>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @can('applications.view')
                    <flux:button href="{{ route('cms.admissions.index') }}" wire:navigate variant="filled" size="sm" icon="user-plus">
                        {{ __('Admissions') }}
                        @if ($pending_applicants_count > 0)
                            <span class="ml-1 rounded-full bg-white/20 px-1.5 py-0.5 text-[10px] font-semibold">{{ $pending_applicants_count }}</span>
                        @endif
                    </flux:button>
                @endcan
                @can('activity_logs.view')
                    <flux:button href="{{ route('cms.activity-logs') }}" wire:navigate variant="ghost" size="sm" icon="clipboard-document-list">
                        {{ __('Activity Logs') }}
                    </flux:button>
                @endcan
            </div>
        </div>
    </div>

    {{-- Core KPI Strip --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-6">
        <a href="{{ route('cms.students.index') }}" wire:navigate class="group rounded-xl border border-zinc-200 bg-white p-4 shadow-xs transition hover:border-indigo-300 hover:shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900 dark:hover:border-indigo-700">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Students') }}</span>
                <div class="rounded-lg bg-indigo-50 p-1.5 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                    <flux:icon.academic-cap class="size-4" />
                </div>
            </div>
            <div class="mt-2 flex items-baseline justify-between gap-1">
                <span class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($students_count) }}</span>
                @if ($new_enrollments > 0)
                    <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">+{{ $new_enrollments }}</span>
                @endif
            </div>
        </a>

        <a href="{{ route('cms.staff.index') }}" wire:navigate class="group rounded-xl border border-zinc-200 bg-white p-4 shadow-xs transition hover:border-sky-300 hover:shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900 dark:hover:border-sky-700">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Staff') }}</span>
                <div class="rounded-lg bg-sky-50 p-1.5 text-sky-600 dark:bg-sky-950/60 dark:text-sky-400">
                    <flux:icon.users class="size-4" />
                </div>
            </div>
            <div class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($staff_count) }}</div>
        </a>

        @if ($is_super_admin)
            <a href="{{ route('cms.institutions.index') }}" wire:navigate class="group rounded-xl border border-zinc-200 bg-white p-4 shadow-xs transition hover:border-purple-300 hover:shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900 dark:hover:border-purple-700">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Institutions') }}</span>
                    <div class="rounded-lg bg-purple-50 p-1.5 text-purple-600 dark:bg-purple-950/60 dark:text-purple-400">
                        <flux:icon.building-library class="size-4" />
                    </div>
                </div>
                <div class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($institutions_count) }}</div>
            </a>
        @else
            <a href="{{ route('cms.departments.index') }}" wire:navigate class="group rounded-xl border border-zinc-200 bg-white p-4 shadow-xs transition hover:border-purple-300 hover:shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900 dark:hover:border-purple-700">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Departments') }}</span>
                    <div class="rounded-lg bg-purple-50 p-1.5 text-purple-600 dark:bg-purple-950/60 dark:text-purple-400">
                        <flux:icon.building-office-2 class="size-4" />
                    </div>
                </div>
                <div class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($departments_count) }}</div>
            </a>
        @endif

        <a href="{{ route('cms.programs.index') }}" wire:navigate class="group rounded-xl border border-zinc-200 bg-white p-4 shadow-xs transition hover:border-teal-300 hover:shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900 dark:hover:border-teal-700">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Programs') }}</span>
                <div class="rounded-lg bg-teal-50 p-1.5 text-teal-600 dark:bg-teal-950/60 dark:text-teal-400">
                    <flux:icon.rectangle-stack class="size-4" />
                </div>
            </div>
            <div class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($programs_count) }}</div>
        </a>

        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700/80 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Courses') }}</span>
                <div class="rounded-lg bg-amber-50 p-1.5 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400">
                    <flux:icon.book-open class="size-4" />
                </div>
            </div>
            <div class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($courses_count) }}</div>
        </div>

        <a href="{{ route('cms.admissions.index') }}" wire:navigate class="group rounded-xl border border-zinc-200 bg-white p-4 shadow-xs transition hover:border-rose-300 hover:shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900 dark:hover:border-rose-700">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Pending Apps') }}</span>
                <div class="rounded-lg bg-rose-50 p-1.5 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400">
                    <flux:icon.inbox-arrow-down class="size-4" />
                </div>
            </div>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($pending_applicants_count) }}</span>
                @if ($pending_applicants_count > 0)
                    <span class="size-2 rounded-full bg-rose-500"></span>
                @endif
            </div>
        </a>
    </div>

    {{-- Financial Health + Academic & Demographics --}}
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">
        {{-- Financial Health --}}
        <flux:card class="flex flex-col justify-between lg:col-span-6">
            <div>
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                        <div class="rounded-lg bg-emerald-50 p-2 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400">
                            <flux:icon.banknotes class="size-5" />
                        </div>
                        <div>
                            <flux:heading size="lg">{{ __('Financial Health') }}</flux:heading>
                            <flux:subheading>{{ __('Student invoicing & fee collection') }}</flux:subheading>
                        </div>
                    </div>
                    <flux:badge color="{{ $collection_efficiency >= 75 ? 'emerald' : ($collection_efficiency >= 40 ? 'amber' : 'rose') }}" size="sm">
                        {{ $collection_efficiency }}% {{ __('Collected') }}
                    </flux:badge>
                </div>

                <div class="mt-5">
                    <div class="mb-1.5 flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span>{{ __('Collection Progress') }}</span>
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">₦{{ number_format($total_collected, 0) }} / ₦{{ number_format($total_invoiced, 0) }}</span>
                    </div>
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ min(100, $collection_efficiency) }}%"></div>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-3 gap-3">
                    <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60">
                        <div class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400">{{ __('Invoiced') }}</div>
                        <div class="mt-1 truncate text-sm font-bold text-zinc-900 dark:text-white sm:text-base">₦{{ number_format($total_invoiced, 0) }}</div>
                    </div>
                    <div class="rounded-xl bg-emerald-50/70 p-3 dark:bg-emerald-950/30">
                        <div class="text-[11px] font-medium text-emerald-700 dark:text-emerald-400">{{ __('Collected') }}</div>
                        <div class="mt-1 truncate text-sm font-bold text-emerald-700 dark:text-emerald-300 sm:text-base">₦{{ number_format($total_collected, 0) }}</div>
                    </div>
                    <div class="rounded-xl bg-rose-50/70 p-3 dark:bg-rose-950/30">
                        <div class="text-[11px] font-medium text-rose-700 dark:text-rose-400">{{ __('Outstanding') }}</div>
                        <div class="mt-1 truncate text-sm font-bold text-rose-700 dark:text-rose-300 sm:text-base">₦{{ number_format($outstanding_debt, 0) }}</div>
                    </div>
                </div>
            </div>

            <div class="mt-5 flex items-center justify-between border-t border-zinc-100 pt-4 dark:border-zinc-800">
                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                    <strong class="text-zinc-900 dark:text-white">{{ number_format($unpaid_invoices_count) }}</strong> {{ __('unpaid / partial invoices') }}
                </span>
                @can('invoices.view')
                    <flux:button href="{{ route('cms.invoices.index') }}" wire:navigate variant="ghost" size="sm" icon-trailing="arrow-right">
                        {{ __('Manage Invoices') }}
                    </flux:button>
                @endcan
            </div>
        </flux:card>

        {{-- Academic & Demographics --}}
        <flux:card class="flex flex-col justify-between lg:col-span-6">
            <div>
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                        <div class="rounded-lg bg-indigo-50 p-2 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                            <flux:icon.chart-bar class="size-5" />
                        </div>
                        <div>
                            <flux:heading size="lg">{{ __('Academic & Student Pulse') }}</flux:heading>
                            <flux:subheading>{{ __('Session registration, performance & demographics') }}</flux:subheading>
                        </div>
                    </div>
                    <flux:badge color="indigo" size="sm">
                        {{ __('Avg GPA:') }} {{ number_format($average_gpa, 2) }}
                    </flux:badge>
                </div>

                <div class="mt-5">
                    <div class="mb-1.5 flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span>{{ __('Course Registration Velocity') }}</span>
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $registration_velocity }}%</span>
                    </div>
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <div class="h-full rounded-full bg-indigo-500 transition-all" style="width: {{ min(100, $registration_velocity) }}%"></div>
                    </div>
                </div>

                @php
                    $genderTotal = max(1, $male_count + $female_count);
                    $malePct = round(($male_count / $genderTotal) * 100);
                @endphp
                <div class="mt-4">
                    <div class="mb-1.5 flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span>{{ __('Male') }}: <strong class="text-zinc-800 dark:text-zinc-200">{{ number_format($male_count) }}</strong></span>
                        <span>{{ __('Female') }}: <strong class="text-zinc-800 dark:text-zinc-200">{{ number_format($female_count) }}</strong></span>
                    </div>
                    <div class="flex h-2 w-full overflow-hidden rounded-full bg-pink-200 dark:bg-pink-900/40">
                        <div class="h-full bg-sky-500" style="width: {{ $malePct }}%"></div>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-3 gap-3">
                    <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60">
                        <div class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400">{{ __('Failed Courses') }}</div>
                        <div class="mt-1 text-base font-bold text-zinc-900 dark:text-white">{{ number_format($failed_results_count) }}</div>
                    </div>
                    <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60">
                        <div class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400">{{ __('Placements') }}</div>
                        <div class="mt-1 text-base font-bold text-zinc-900 dark:text-white">{{ number_format($active_placements_count) }}</div>
                    </div>
                    <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60">
                        <div class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400">{{ __('Projects') }}</div>
                        <div class="mt-1 text-base font-bold text-zinc-900 dark:text-white">{{ number_format($active_projects_count) }}</div>
                    </div>
                </div>
            </div>

            @if ($pending_cbt_reviews_count > 0)
                <div class="mt-5 flex items-center justify-between rounded-xl border border-amber-200 bg-amber-50/80 px-3.5 py-2.5 dark:border-amber-800/60 dark:bg-amber-950/30">
                    <span class="text-xs font-medium text-amber-800 dark:text-amber-300">
                        {{ __(':count CBT result(s) awaiting review', ['count' => $pending_cbt_reviews_count]) }}
                    </span>
                    <flux:button href="{{ route('cms.cbt.results') }}" wire:navigate variant="ghost" size="xs">
                        {{ __('Review') }}
                    </flux:button>
                </div>
            @endif
        </flux:card>
    </div>

    {{-- Operational Add-ons (Payroll & Inventory) --}}
    @if ($hasPayrollAddon || $hasInventoryAddon)
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            @if ($hasPayrollAddon)
                <flux:card class="flex flex-col justify-between">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <div class="rounded-lg bg-violet-50 p-2 text-violet-600 dark:bg-violet-950/60 dark:text-violet-400">
                                <flux:icon.credit-card class="size-5" />
                            </div>
                            <div>
                                <flux:heading size="lg">{{ __('Payroll Operations') }}</flux:heading>
                                <flux:subheading>{{ __('Latest cycle & approval queue') }}</flux:subheading>
                            </div>
                        </div>
                        @if ($pending_payroll_runs_count > 0)
                            <flux:badge color="amber" size="sm">
                                {{ $pending_payroll_runs_count }} {{ __('Under Review') }}
                            </flux:badge>
                        @endif
                    </div>

                    <div class="mt-4 rounded-xl border border-zinc-100 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-800/50">
                        @if ($latest_payroll_run)
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <div class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $latest_payroll_run->title }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ \Carbon\Carbon::create()->month((int) $latest_payroll_run->month)->format('F') }} {{ $latest_payroll_run->year }}
                                    </div>
                                </div>
                                <flux:badge color="{{ $latest_payroll_run->statusColor() }}" size="sm">
                                    {{ $latest_payroll_run->statusLabel() }}
                                </flux:badge>
                            </div>
                            <div class="mt-3 flex items-baseline justify-between border-t border-zinc-200/70 pt-3 dark:border-zinc-700/60">
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Total Net Payout') }}</span>
                                <span class="text-base font-bold text-zinc-900 dark:text-white">₦{{ number_format((float) $latest_payroll_run->total_net, 2) }}</span>
                            </div>
                        @else
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No payroll runs created yet.') }}</p>
                        @endif
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                        @can('payroll.view')
                            <flux:button href="{{ route('cms.payroll.runs') }}" wire:navigate variant="ghost" size="sm">
                                {{ __('Payroll Runs') }}
                            </flux:button>
                            <flux:button href="{{ route('cms.payroll.index') }}" wire:navigate variant="filled" size="sm" icon-trailing="arrow-right">
                                {{ __('Open Payroll') }}
                            </flux:button>
                        @endcan
                    </div>
                </flux:card>
            @endif

            @if ($hasInventoryAddon)
                <flux:card class="flex flex-col justify-between">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <div class="rounded-lg bg-cyan-50 p-2 text-cyan-600 dark:bg-cyan-950/60 dark:text-cyan-400">
                                <flux:icon.archive-box class="size-5" />
                            </div>
                            <div>
                                <flux:heading size="lg">{{ __('Campus Inventory') }}</flux:heading>
                                <flux:subheading>{{ __('Stockrooms, labs & replenishment alerts') }}</flux:subheading>
                            </div>
                        </div>
                        @if ($inventory_low_stock_count > 0)
                            <flux:badge color="rose" size="sm">
                                {{ $inventory_low_stock_count }} {{ __('Low Stock') }}
                            </flux:badge>
                        @else
                            <flux:badge color="emerald" size="sm">{{ __('Healthy Stock') }}</flux:badge>
                        @endif
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-xl border border-zinc-100 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-800/50">
                            <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Active Rooms / Stores') }}</div>
                            <div class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($inventory_locations_count) }}</div>
                        </div>
                        <div class="rounded-xl border border-zinc-100 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-800/50">
                            <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Items Below Reorder') }}</div>
                            <div class="mt-1 text-2xl font-bold {{ $inventory_low_stock_count > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-900 dark:text-white' }}">
                                {{ number_format($inventory_low_stock_count) }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                        @can('inventory.reconcile')
                            <flux:button href="{{ route('cms.inventory.reconcile') }}" wire:navigate variant="ghost" size="sm">
                                {{ __('Stock Count') }}
                            </flux:button>
                        @endcan
                        @can('inventory.view')
                            <flux:button href="{{ route('cms.inventory.index') }}" wire:navigate variant="filled" size="sm" icon-trailing="arrow-right">
                                {{ __('Open Inventory') }}
                            </flux:button>
                        @endcan
                    </div>
                </flux:card>
            @endif
        </div>
    @endif

    {{-- Recent Activity & Quick Access Management --}}
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">
        {{-- Recent Activity Feed --}}
        <flux:card class="lg:col-span-5">
            <div class="flex items-center justify-between gap-2">
                <flux:heading size="lg">{{ __('Recent Activity') }}</flux:heading>
                @can('activity_logs.view')
                    <flux:button href="{{ route('cms.activity-logs') }}" wire:navigate variant="ghost" size="xs" icon-trailing="arrow-right">
                        {{ __('View All') }}
                    </flux:button>
                @endcan
            </div>

            <div class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($recentActivities as $log)
                    <div class="py-3 first:pt-0 last:pb-0">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $log->description ?: ucfirst(str_replace('_', ' ', $log->action)) }}
                                </p>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                    <span>{{ $log->user?->name ?? __('System') }}</span>
                                    @if ($log->module)
                                        <span class="rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                            {{ $log->module }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <span class="shrink-0 text-[11px] text-zinc-400 dark:text-zinc-500">
                                {{ $log->created_at?->diffForHumans() }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('No recent activity recorded.') }}
                    </div>
                @endforelse
            </div>
        </flux:card>

        {{-- Quick Access Management --}}
        <flux:card class="lg:col-span-7">
            <flux:heading size="lg">{{ __('Quick Access') }}</flux:heading>
            <flux:subheading>{{ __('Jump directly to key institutional modules') }}</flux:subheading>

            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                @can('students.view')
                    <a href="{{ route('cms.students.index') }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 transition hover:border-indigo-300 hover:bg-indigo-50/40 dark:border-zinc-800 dark:hover:border-indigo-700 dark:hover:bg-indigo-950/20">
                        <flux:icon.academic-cap class="size-5 shrink-0 text-indigo-600 dark:text-indigo-400" />
                        <span class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Students') }}</span>
                    </a>
                @endcan

                @can('staff.view')
                    <a href="{{ route('cms.staff.index') }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 transition hover:border-sky-300 hover:bg-sky-50/40 dark:border-zinc-800 dark:hover:border-sky-700 dark:hover:bg-sky-950/20">
                        <flux:icon.users class="size-5 shrink-0 text-sky-600 dark:text-sky-400" />
                        <span class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Staff') }}</span>
                    </a>
                @endcan

                @can('invoices.view')
                    <a href="{{ route('cms.invoices.index') }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 transition hover:border-emerald-300 hover:bg-emerald-50/40 dark:border-zinc-800 dark:hover:border-emerald-700 dark:hover:bg-emerald-950/20">
                        <flux:icon.banknotes class="size-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        <span class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Invoices') }}</span>
                    </a>
                @endcan

                @can('applications.view')
                    <a href="{{ route('cms.admissions.index') }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 transition hover:border-rose-300 hover:bg-rose-50/40 dark:border-zinc-800 dark:hover:border-rose-700 dark:hover:bg-rose-950/20">
                        <flux:icon.user-plus class="size-5 shrink-0 text-rose-600 dark:text-rose-400" />
                        <span class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Admissions') }}</span>
                    </a>
                @endcan

                @can('timetables.view')
                    <a href="{{ route('cms.timetables.index') }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 transition hover:border-amber-300 hover:bg-amber-50/40 dark:border-zinc-800 dark:hover:border-amber-700 dark:hover:bg-amber-950/20">
                        <flux:icon.calendar-days class="size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                        <span class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Timetables') }}</span>
                    </a>
                @endcan

                @can('placements.view')
                    <a href="{{ route('cms.placements.index') }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 transition hover:border-teal-300 hover:bg-teal-50/40 dark:border-zinc-800 dark:hover:border-teal-700 dark:hover:bg-teal-950/20">
                        <flux:icon.briefcase class="size-5 shrink-0 text-teal-600 dark:text-teal-400" />
                        <span class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Placements') }}</span>
                    </a>
                @endcan

                @can('projects.view')
                    <a href="{{ route('cms.projects.index') }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 transition hover:border-purple-300 hover:bg-purple-50/40 dark:border-zinc-800 dark:hover:border-purple-700 dark:hover:bg-purple-950/20">
                        <flux:icon.folder-open class="size-5 shrink-0 text-purple-600 dark:text-purple-400" />
                        <span class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Projects') }}</span>
                    </a>
                @endcan

                @if ($hasInventoryAddon && auth()->user()->can('inventory.view'))
                    <a href="{{ route('cms.inventory.index') }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 transition hover:border-cyan-300 hover:bg-cyan-50/40 dark:border-zinc-800 dark:hover:border-cyan-700 dark:hover:bg-cyan-950/20">
                        <flux:icon.archive-box class="size-5 shrink-0 text-cyan-600 dark:text-cyan-400" />
                        <span class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Inventory') }}</span>
                    </a>
                @endif

                @if ($hasPayrollAddon && auth()->user()->can('payroll.view'))
                    <a href="{{ route('cms.payroll.index') }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 transition hover:border-violet-300 hover:bg-violet-50/40 dark:border-zinc-800 dark:hover:border-violet-700 dark:hover:bg-violet-950/20">
                        <flux:icon.credit-card class="size-5 shrink-0 text-violet-600 dark:text-violet-400" />
                        <span class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Payroll') }}</span>
                    </a>
                @endif

                @can('sessions.view')
                    <a href="{{ route('cms.sessions.index') }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 transition hover:border-zinc-400 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:border-zinc-600 dark:hover:bg-zinc-800/50">
                        <flux:icon.cog-6-tooth class="size-5 shrink-0 text-zinc-600 dark:text-zinc-400" />
                        <span class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Sessions') }}</span>
                    </a>
                @endcan
            </div>
        </flux:card>
    </div>
</div>
