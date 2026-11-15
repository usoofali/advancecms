<div class="space-y-6">
    {{-- Student Welcome Banner --}}
    <div class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-gradient-to-br from-white via-zinc-50 to-emerald-50/60 p-5 shadow-xs dark:border-zinc-700/80 dark:from-zinc-900 dark:via-zinc-900 dark:to-emerald-950/30 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1.5">
                <div class="flex flex-wrap items-center gap-2">
                    <flux:badge color="{{ $registration_status === 'Registered' ? 'emerald' : 'amber' }}" size="sm">
                        {{ $registration_status }}
                    </flux:badge>
                    <flux:badge color="indigo" size="sm">
                        {{ __('Level') }} {{ $current_level }}
                    </flux:badge>
                    @if ($activeSession)
                        <flux:badge color="zinc" size="sm" icon="calendar">
                            {{ $activeSession->name }}
                        </flux:badge>
                    @endif
                    @if ($hasCaAddon)
                        <flux:badge color="amber" size="sm" icon="sparkles">
                            {{ number_format($coins_balance) }} {{ __('Coins') }}
                        </flux:badge>
                    @endif
                </div>
                <h1 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-2xl">
                    {{ $student?->full_name ?? $user->name }}
                </h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    @if ($student?->matric_number)
                        <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $student->matric_number }}</span>
                        <span class="mx-1.5 text-zinc-400">&bull;</span>
                    @endif
                    {{ $student?->program?->name ?? __('Student Portal') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button href="{{ route('cms.students.portal-registration') }}" wire:navigate variant="filled" size="sm" icon="book-open">
                    {{ __('Course Registration') }}
                </flux:button>
                <flux:button href="{{ route('cms.students.exam-card') }}" wire:navigate variant="ghost" size="sm" icon="identification">
                    {{ __('Exam Card') }}
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Top 4 KPI Cards --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700/80 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Current CGPA') }}</span>
                <div class="rounded-lg bg-indigo-50 p-1.5 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                    <flux:icon.academic-cap class="size-4" />
                </div>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                @if ($is_results_locked)
                    <span class="text-lg font-bold text-amber-600 dark:text-amber-400">{{ __('Locked') }}</span>
                @else
                    <span class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format((float) $cgpa, 2) }}</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $total_units }} {{ __('units') }}</span>
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700/80 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Fee Balance') }}</span>
                <div class="rounded-lg bg-rose-50 p-1.5 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400">
                    <flux:icon.banknotes class="size-4" />
                </div>
            </div>
            <div class="mt-2 truncate text-xl font-bold {{ $pending_balance > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }} sm:text-2xl">
                ₦{{ number_format((float) $pending_balance, 0) }}
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700/80 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Registered Courses') }}</span>
                <div class="rounded-lg bg-emerald-50 p-1.5 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400">
                    <flux:icon.clipboard-document-check class="size-4" />
                </div>
            </div>
            <div class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($registered_courses_count) }}</div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700/80 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Attendance') }}</span>
                <div class="rounded-lg bg-sky-50 p-1.5 text-sky-600 dark:bg-sky-950/60 dark:text-sky-400">
                    <flux:icon.clock class="size-4" />
                </div>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format((float) $overall_attendance, 1) }}%</span>
                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $today_timetable_count }} {{ __('today') }}</span>
            </div>
        </div>
    </div>

    {{-- Financial Pulse & Academic Performance --}}
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">
        {{-- Financial Pulse --}}
        <flux:card class="flex flex-col justify-between lg:col-span-6">
            <div>
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                        <div class="rounded-lg bg-emerald-50 p-2 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400">
                            <flux:icon.credit-card class="size-5" />
                        </div>
                        <div>
                            <flux:heading size="lg">{{ __('Financial Pulse') }}</flux:heading>
                            <flux:subheading>{{ __('Invoice settlement summary') }}</flux:subheading>
                        </div>
                    </div>
                    <flux:button href="{{ route('cms.students.portal-invoices') }}" wire:navigate variant="ghost" size="xs" icon-trailing="arrow-right">
                        {{ __('Invoices') }}
                    </flux:button>
                </div>

                <div class="mt-5 grid grid-cols-3 gap-3">
                    <div class="rounded-xl bg-emerald-50/70 p-3 text-center dark:bg-emerald-950/30">
                        <div class="text-xl font-bold text-emerald-700 dark:text-emerald-300">{{ $paid_invoices_count }}</div>
                        <div class="mt-0.5 text-[11px] font-medium text-emerald-700/80 dark:text-emerald-400">{{ __('Paid') }}</div>
                    </div>
                    <div class="rounded-xl bg-amber-50/70 p-3 text-center dark:bg-amber-950/30">
                        <div class="text-xl font-bold text-amber-700 dark:text-amber-300">{{ $partial_invoices_count }}</div>
                        <div class="mt-0.5 text-[11px] font-medium text-amber-700/80 dark:text-amber-400">{{ __('Partial') }}</div>
                    </div>
                    <div class="rounded-xl bg-rose-50/70 p-3 text-center dark:bg-rose-950/30">
                        <div class="text-xl font-bold text-rose-700 dark:text-rose-300">{{ $unpaid_invoices_count }}</div>
                        <div class="mt-0.5 text-[11px] font-medium text-rose-700/80 dark:text-rose-400">{{ __('Unpaid') }}</div>
                    </div>
                </div>
            </div>

            <div class="mt-5 flex items-center justify-between border-t border-zinc-100 pt-4 dark:border-zinc-800">
                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Profile Completion') }}: <strong class="text-zinc-900 dark:text-white">{{ $profile_completion }}%</strong></span>
                <div class="h-2 w-28 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <div class="h-full rounded-full bg-indigo-500" style="width: {{ min(100, $profile_completion) }}%"></div>
                </div>
            </div>
        </flux:card>

        {{-- Academic Performance --}}
        <flux:card class="flex flex-col justify-between lg:col-span-6">
            <div>
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                        <div class="rounded-lg bg-indigo-50 p-2 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                            <flux:icon.chart-bar class="size-5" />
                        </div>
                        <div>
                            <flux:heading size="lg">{{ __('Academic Standing') }}</flux:heading>
                            <flux:subheading>{{ __('Course pass rate & result history') }}</flux:subheading>
                        </div>
                    </div>
                    <flux:button href="{{ route('cms.results.portal') }}" wire:navigate variant="ghost" size="xs" icon-trailing="arrow-right">
                        {{ __('Results') }}
                    </flux:button>
                </div>

                @if ($is_results_locked)
                    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50/70 p-4 text-sm text-amber-800 dark:border-amber-800/60 dark:bg-amber-950/30 dark:text-amber-300">
                        {{ __('Results for your program are currently locked pending Senate/Academic Board release.') }}
                    </div>
                @else
                    <div class="mt-5">
                        <div class="mb-1.5 flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                            <span>{{ __('Overall Pass Rate') }}</span>
                            <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $pass_rate }}%</span>
                        </div>
                        <div class="h-2.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <div class="h-full rounded-full bg-indigo-500" style="width: {{ min(100, $pass_rate) }}%"></div>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60">
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Passed Courses') }}</div>
                            <div class="mt-1 text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($passed_count) }}</div>
                        </div>
                        <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60">
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Carryover / Failed') }}</div>
                            <div class="mt-1 text-lg font-bold text-rose-600 dark:text-rose-400">{{ number_format($failed_count) }}</div>
                        </div>
                    </div>
                @endif
            </div>
        </flux:card>
    </div>

    {{-- Campus Activities, Timetable & Student Services --}}
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
        {{-- Timetable & Attendance --}}
        <flux:card class="flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-2.5">
                    <div class="rounded-lg bg-amber-50 p-2 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400">
                        <flux:icon.calendar-days class="size-5" />
                    </div>
                    <div>
                        <flux:heading size="lg">{{ __('Schedule & Attendance') }}</flux:heading>
                        <flux:subheading>{{ __(':count class(es) scheduled today', ['count' => $today_timetable_count]) }}</flux:subheading>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <flux:button href="{{ route('cms.timetables.my-timetable') }}" wire:navigate variant="filled" size="sm" class="flex-1">
                    {{ __('My Timetable') }}
                </flux:button>
                <flux:button href="{{ route('cms.attendance.participation') }}" wire:navigate variant="ghost" size="sm">
                    {{ __('Attendance') }}
                </flux:button>
            </div>
        </flux:card>

        {{-- CA Tests & Projects / Placements --}}
        <flux:card class="flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-2.5">
                    <div class="rounded-lg bg-purple-50 p-2 text-purple-600 dark:bg-purple-950/60 dark:text-purple-400">
                        <flux:icon.sparkles class="size-5" />
                    </div>
                    <div>
                        <flux:heading size="lg">{{ __('Assessments & Research') }}</flux:heading>
                        <flux:subheading>{{ __('CA tests, projects & placements') }}</flux:subheading>
                    </div>
                </div>

                <div class="mt-4 space-y-2">
                    @if ($hasCaAddon)
                        <a href="{{ route('cms.ca-tests.student.index') }}" wire:navigate class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-700 transition hover:bg-zinc-100 dark:bg-zinc-800/60 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <span>{{ __('CA Tests & Quizzes') }}</span>
                            <flux:badge size="sm" color="amber">{{ number_format($coins_balance) }} {{ __('coins') }}</flux:badge>
                        </a>
                    @endif
                    @can('projects.view_personal')
                        <a href="{{ route('cms.projects.student.index') }}" wire:navigate class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-700 transition hover:bg-zinc-100 dark:bg-zinc-800/60 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <span>{{ __('Academic Project') }}</span>
                            <flux:badge size="sm" color="{{ $active_project ? 'indigo' : 'zinc' }}">
                                {{ $active_project ? ucfirst(str_replace('_', ' ', $active_project->overall_status)) : __('None') }}
                            </flux:badge>
                        </a>
                    @endcan
                    @can('placements.view_personal')
                        <a href="{{ route('cms.placements.student.index') }}" wire:navigate class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-700 transition hover:bg-zinc-100 dark:bg-zinc-800/60 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <span>{{ __('Industrial Placement') }}</span>
                            <flux:badge size="sm" color="{{ $active_placement ? 'emerald' : 'zinc' }}">
                                {{ $active_placement ? ucfirst($active_placement->status) : __('None') }}
                            </flux:badge>
                        </a>
                    @endcan
                </div>
            </div>
        </flux:card>

        {{-- ID Card & Account --}}
        <flux:card class="flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-2.5">
                    <div class="rounded-lg bg-sky-50 p-2 text-sky-600 dark:bg-sky-950/60 dark:text-sky-400">
                        <flux:icon.identification class="size-5" />
                    </div>
                    <div>
                        <flux:heading size="lg">{{ __('ID Card & Profile') }}</flux:heading>
                        <flux:subheading>{{ __('Campus identity & personal info') }}</flux:subheading>
                    </div>
                </div>

                <div class="mt-4 space-y-2">
                    @can('id_cards.request')
                        <a href="{{ route('cms.id-cards.request') }}" wire:navigate class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-700 transition hover:bg-zinc-100 dark:bg-zinc-800/60 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <span>{{ __('Student ID Card') }}</span>
                            <flux:badge size="sm" color="{{ $latest_id_card_request ? 'sky' : 'zinc' }}">
                                {{ $latest_id_card_request ? ucfirst($latest_id_card_request->status) : __('Request') }}
                            </flux:badge>
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
