<div class="space-y-6">
    {{-- Staff Welcome Banner --}}
    <div class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-gradient-to-br from-white via-zinc-50 to-sky-50/60 p-5 shadow-xs dark:border-zinc-700/80 dark:from-zinc-900 dark:via-zinc-900 dark:to-sky-950/30 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1.5">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($is_hod)
                        <flux:badge color="purple" size="sm" icon="shield-check">{{ __('Head of Department') }}</flux:badge>
                    @endif
                    @if ($staff?->designation)
                        <flux:badge color="sky" size="sm">{{ $staff->designation }}</flux:badge>
                    @endif
                    @if ($activeSession)
                        <flux:badge color="emerald" size="sm" icon="calendar">{{ $activeSession->name }}</flux:badge>
                    @endif
                </div>
                <h1 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-2xl">
                    {{ $staff?->full_name ?? $user->name }}
                </h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    @if ($staff?->staff_number)
                        <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $staff->staff_number }}</span>
                        <span class="mx-1.5 text-zinc-400">&bull;</span>
                    @endif
                    {{ $department_names }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($staff)
                    <flux:button href="{{ route('cms.staff.show', $staff) }}" wire:navigate variant="filled" size="sm" icon="user">
                        {{ __('My Profile') }}
                    </flux:button>
                @endif
                @if ($hasPayrollAddon && $user->can('payroll.view_own_payslip'))
                    <flux:button href="{{ route('cms.payroll.my-payslips') }}" wire:navigate variant="ghost" size="sm" icon="document-text">
                        {{ __('My Payslips') }}
                    </flux:button>
                @endif
                @if ($show_teaching_kpis)
                    <flux:button href="{{ route('cms.timetables.my-timetable') }}" wire:navigate variant="ghost" size="sm" icon="calendar-days">
                        {{ __('My Timetable') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    {{-- Role & Permission-Aware KPI Strip --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        @if ($show_teaching_kpis)
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700/80 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Assigned Courses') }}</span>
                    <div class="rounded-lg bg-indigo-50 p-1.5 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                        <flux:icon.book-open class="size-4" />
                    </div>
                </div>
                <div class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($allocations_count) }}</div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700/80 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Course Students') }}</span>
                    <div class="rounded-lg bg-sky-50 p-1.5 text-sky-600 dark:bg-sky-950/60 dark:text-sky-400">
                        <flux:icon.academic-cap class="size-4" />
                    </div>
                </div>
                <div class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($total_registered_students) }}</div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700/80 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Result Progress') }}</span>
                    <div class="rounded-lg bg-emerald-50 p-1.5 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400">
                        <flux:icon.check-circle class="size-4" />
                    </div>
                </div>
                <div class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ $submission_progress }}%</div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700/80 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Classes Today') }}</span>
                    <div class="rounded-lg bg-amber-50 p-1.5 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400">
                        <flux:icon.clock class="size-4" />
                    </div>
                </div>
                <div class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($today_timetable_count) }}</div>
            </div>
        @endif

        @if ($show_academic_dept_kpis)
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700/80 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Dept. Students') }}</span>
                    <div class="rounded-lg bg-purple-50 p-1.5 text-purple-600 dark:bg-purple-950/60 dark:text-purple-400">
                        <flux:icon.users class="size-4" />
                    </div>
                </div>
                <div class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($total_dept_students) }}</div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700/80 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Dept. Programs') }}</span>
                    <div class="rounded-lg bg-teal-50 p-1.5 text-teal-600 dark:bg-teal-950/60 dark:text-teal-400">
                        <flux:icon.rectangle-stack class="size-4" />
                    </div>
                </div>
                <div class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($total_dept_programs) }}</div>
            </div>
        @endif

        @if ($user->can('invoices.view') || $user->can('payments.verify'))
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700/80 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Unpaid Invoices') }}</span>
                    <div class="rounded-lg bg-rose-50 p-1.5 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400">
                        <flux:icon.banknotes class="size-4" />
                    </div>
                </div>
                <div class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($pending_payments_count) }}</div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700/80 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Verified Today') }}</span>
                    <div class="rounded-lg bg-emerald-50 p-1.5 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400">
                        <flux:icon.check-badge class="size-4" />
                    </div>
                </div>
                <div class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($today_verified_count) }}</div>
            </div>
        @endif

        @if ($hasInventoryAddon && $user->can('inventory.view'))
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700/80 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Accessible Rooms') }}</span>
                    <div class="rounded-lg bg-cyan-50 p-1.5 text-cyan-600 dark:bg-cyan-950/60 dark:text-cyan-400">
                        <flux:icon.archive-box class="size-4" />
                    </div>
                </div>
                <div class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($inventory_locations_count) }}</div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700/80 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Low Stock Items') }}</span>
                    <div class="rounded-lg bg-rose-50 p-1.5 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400">
                        <flux:icon.exclamation-triangle class="size-4" />
                    </div>
                </div>
                <div class="mt-2 text-2xl font-bold {{ $inventory_low_stock_count > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-900 dark:text-white' }}">
                    {{ number_format($inventory_low_stock_count) }}
                </div>
            </div>
        @endif

        @can('applications.view')
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700/80 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Pending Applicants') }}</span>
                    <div class="rounded-lg bg-amber-50 p-1.5 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400">
                        <flux:icon.user-plus class="size-4" />
                    </div>
                </div>
                <div class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($pending_applicants_count) }}</div>
            </div>
        @endcan

        @if ($hasExamAddon && $user->can('cbt_results.view'))
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700/80 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('CBT Pending Review') }}</span>
                    <div class="rounded-lg bg-indigo-50 p-1.5 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                        <flux:icon.computer-desktop class="size-4" />
                    </div>
                </div>
                <div class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($pending_cbt_reviews_count) }}</div>
            </div>
        @endif
    </div>

    {{-- Role-Specific Workspaces --}}
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
        {{-- Payroll Operations --}}
        @if ($hasPayrollAddon && $user->can('payroll.view'))
            <flux:card class="flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            <div class="rounded-lg bg-violet-50 p-2 text-violet-600 dark:bg-violet-950/60 dark:text-violet-400">
                                <flux:icon.credit-card class="size-5" />
                            </div>
                            <flux:heading size="lg">{{ __('Payroll Management') }}</flux:heading>
                        </div>
                        @if ($latest_payroll_run)
                            <flux:badge color="{{ $latest_payroll_run->statusColor() }}" size="sm">
                                {{ $latest_payroll_run->statusLabel() }}
                            </flux:badge>
                        @endif
                    </div>

                    <div class="mt-4 rounded-xl bg-zinc-50 p-3.5 dark:bg-zinc-800/60">
                        @if ($latest_payroll_run)
                            <div class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $latest_payroll_run->title }}</div>
                            <div class="mt-1 flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                                <span>{{ __('Net Total') }}</span>
                                <span class="font-bold text-zinc-900 dark:text-white">₦{{ number_format((float) $latest_payroll_run->total_net, 2) }}</span>
                            </div>
                        @else
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('No payroll runs recorded yet.') }}</p>
                        @endif
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <flux:button href="{{ route('cms.payroll.runs') }}" wire:navigate variant="filled" size="sm" class="flex-1">
                        {{ __('Payroll Runs') }}
                    </flux:button>
                    <flux:button href="{{ route('cms.payroll.staff') }}" wire:navigate variant="ghost" size="sm">
                        {{ __('Staff Pay') }}
                    </flux:button>
                </div>
            </flux:card>
        @endif

        {{-- My Latest Payslip --}}
        @if ($hasPayrollAddon && $staff && $user->can('payroll.view_own_payslip'))
            <flux:card class="flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2.5">
                        <div class="rounded-lg bg-emerald-50 p-2 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400">
                            <flux:icon.document-currency-dollar class="size-5" />
                        </div>
                        <div>
                            <flux:heading size="lg">{{ __('My Latest Payslip') }}</flux:heading>
                            <flux:subheading>{{ __('Personal salary statement') }}</flux:subheading>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl bg-zinc-50 p-3.5 dark:bg-zinc-800/60">
                        @if ($latest_payslip && $latest_payslip->run)
                            <div class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $latest_payslip->run->title }}</div>
                            <div class="mt-1 flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                                <span>{{ __('Net Pay') }}</span>
                                <span class="text-base font-bold text-emerald-600 dark:text-emerald-400">₦{{ number_format((float) $latest_payslip->net_pay, 2) }}</span>
                            </div>
                        @else
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('No approved payslips available yet.') }}</p>
                        @endif
                    </div>
                </div>

                <div class="mt-4">
                    <flux:button href="{{ route('cms.payroll.my-payslips') }}" wire:navigate variant="ghost" size="sm" icon-trailing="arrow-right" class="w-full justify-between">
                        {{ __('View All Payslips') }}
                    </flux:button>
                </div>
            </flux:card>
        @endif

        {{-- Inventory Operations --}}
        @if ($hasInventoryAddon && $user->can('inventory.view'))
            <flux:card class="flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2.5">
                        <div class="rounded-lg bg-cyan-50 p-2 text-cyan-600 dark:bg-cyan-950/60 dark:text-cyan-400">
                            <flux:icon.archive-box class="size-5" />
                        </div>
                        <div>
                            <flux:heading size="lg">{{ __('Inventory & Stock') }}</flux:heading>
                            <flux:subheading>{{ __('Custodial rooms & stock counts') }}</flux:subheading>
                        </div>
                    </div>

                    <p class="mt-3 text-xs text-zinc-600 dark:text-zinc-400">
                        {{ __('You have access to :rooms active location(s). :low item(s) currently need replenishment.', ['rooms' => $inventory_locations_count, 'low' => $inventory_low_stock_count]) }}
                    </p>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <flux:button href="{{ route('cms.inventory.index') }}" wire:navigate variant="filled" size="sm" class="flex-1">
                        {{ __('Inventory Hub') }}
                    </flux:button>
                    @can('inventory.reconcile')
                        <flux:button href="{{ route('cms.inventory.reconcile') }}" wire:navigate variant="ghost" size="sm">
                            {{ __('Stock Count') }}
                        </flux:button>
                    @endcan
                </div>
            </flux:card>
        @endif

        {{-- Teaching & Academic Supervision --}}
        @if ($show_teaching_kpis || $user->can('projects.supervise') || $user->can('placements.supervise'))
            <flux:card class="flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2.5">
                        <div class="rounded-lg bg-indigo-50 p-2 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                            <flux:icon.academic-cap class="size-5" />
                        </div>
                        <div>
                            <flux:heading size="lg">{{ __('Teaching & Supervision') }}</flux:heading>
                            <flux:subheading>{{ __('Courses, timetables & student mentoring') }}</flux:subheading>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2">
                        @if ($show_teaching_kpis)
                            <a href="{{ route('cms.timetables.my-timetable') }}" wire:navigate class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-700 transition hover:bg-zinc-100 dark:bg-zinc-800/60 dark:text-zinc-200 dark:hover:bg-zinc-800">
                                <span>{{ __('Personal Timetable') }}</span>
                                <flux:badge size="sm" color="amber">{{ $today_timetable_count }} {{ __('today') }}</flux:badge>
                            </a>
                        @endif
                        @can('projects.supervise')
                            <a href="{{ route('cms.projects.my-supervisions') }}" wire:navigate class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-700 transition hover:bg-zinc-100 dark:bg-zinc-800/60 dark:text-zinc-200 dark:hover:bg-zinc-800">
                                <span>{{ __('Project Supervisions') }}</span>
                                <flux:badge size="sm" color="indigo">{{ $supervised_projects_count }}</flux:badge>
                            </a>
                        @endcan
                        @can('placements.supervise')
                            <a href="{{ route('cms.placements.my-supervisions') }}" wire:navigate class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-700 transition hover:bg-zinc-100 dark:bg-zinc-800/60 dark:text-zinc-200 dark:hover:bg-zinc-800">
                                <span>{{ __('Placement Supervisions') }}</span>
                                <flux:icon.arrow-right class="size-4 text-zinc-400" />
                            </a>
                        @endcan
                    </div>
                </div>
            </flux:card>
        @endif

        {{-- Bursary / Finance --}}
        @if ($user->can('invoices.view') || $user->can('payments.verify'))
            <flux:card class="flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2.5">
                        <div class="rounded-lg bg-emerald-50 p-2 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400">
                            <flux:icon.banknotes class="size-5" />
                        </div>
                        <div>
                            <flux:heading size="lg">{{ __('Bursary & Payments') }}</flux:heading>
                            <flux:subheading>{{ __('Invoices & fee verification') }}</flux:subheading>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-zinc-600 dark:text-zinc-400">
                        {{ __(':unpaid invoice(s) pending settlement; :verified payment(s) verified today.', ['unpaid' => $pending_payments_count, 'verified' => $today_verified_count]) }}
                    </p>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    @can('invoices.view')
                        <flux:button href="{{ route('cms.invoices.index') }}" wire:navigate variant="filled" size="sm" class="flex-1">
                            {{ __('Invoices') }}
                        </flux:button>
                    @endcan
                    @can('payments.verify')
                        <flux:button href="{{ route('cms.invoices.payments') }}" wire:navigate variant="ghost" size="sm">
                            {{ __('Payments') }}
                        </flux:button>
                    @endcan
                </div>
            </flux:card>
        @endif

        {{-- Admissions & CBT --}}
        @if ($user->can('applications.view') || ($hasExamAddon && $user->can('cbt_results.view')))
            <flux:card class="flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2.5">
                        <div class="rounded-lg bg-amber-50 p-2 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400">
                            <flux:icon.clipboard-document-check class="size-5" />
                        </div>
                        <div>
                            <flux:heading size="lg">{{ __('Admissions & Exams') }}</flux:heading>
                            <flux:subheading>{{ __('Applicant queue & CBT staging') }}</flux:subheading>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2">
                        @can('applications.view')
                            <a href="{{ route('cms.admissions.index') }}" wire:navigate class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-700 transition hover:bg-zinc-100 dark:bg-zinc-800/60 dark:text-zinc-200 dark:hover:bg-zinc-800">
                                <span>{{ __('Admissions Queue') }}</span>
                                <flux:badge size="sm" color="amber">{{ $pending_applicants_count }}</flux:badge>
                            </a>
                        @endcan
                        @if ($hasExamAddon && $user->can('cbt_results.view'))
                            <a href="{{ route('cms.cbt.results') }}" wire:navigate class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-700 transition hover:bg-zinc-100 dark:bg-zinc-800/60 dark:text-zinc-200 dark:hover:bg-zinc-800">
                                <span>{{ __('CBT Results Staging') }}</span>
                                <flux:badge size="sm" color="indigo">{{ $pending_cbt_reviews_count }}</flux:badge>
                            </a>
                        @endif
                    </div>
                </div>
            </flux:card>
        @endif

        {{-- Staff Self-Service --}}
        <flux:card class="flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-2.5">
                    <div class="rounded-lg bg-zinc-100 p-2 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                        <flux:icon.identification class="size-5" />
                    </div>
                    <div>
                        <flux:heading size="lg">{{ __('Staff Self-Service') }}</flux:heading>
                        <flux:subheading>{{ __('Profile, account & campus services') }}</flux:subheading>
                    </div>
                </div>

                <div class="mt-4 space-y-2">
                    @if ($staff)
                        <a href="{{ route('cms.staff.show', $staff) }}" wire:navigate class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-700 transition hover:bg-zinc-100 dark:bg-zinc-800/60 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <span>{{ __('My Staff Record') }}</span>
                            <flux:icon.arrow-right class="size-4 text-zinc-400" />
                        </a>
                    @endif
                    @can('id_cards.request')
                        <a href="{{ route('cms.id-cards.request') }}" wire:navigate class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-700 transition hover:bg-zinc-100 dark:bg-zinc-800/60 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <span>{{ __('ID Card Request') }}</span>
                            <flux:icon.arrow-right class="size-4 text-zinc-400" />
                        </a>
                    @endcan
                    <a href="{{ route('profile.edit') }}" wire:navigate class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-700 transition hover:bg-zinc-100 dark:bg-zinc-800/60 dark:text-zinc-200 dark:hover:bg-zinc-800">
                        <span>{{ __('Account Settings') }}</span>
                        <flux:icon.arrow-right class="size-4 text-zinc-400" />
                    </a>
                </div>
            </div>
        </flux:card>
    </div>
</div>
