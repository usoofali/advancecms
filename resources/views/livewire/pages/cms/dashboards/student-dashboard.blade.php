<div class="space-y-8">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {{-- GPA Card --}}
        <flux:card class="relative overflow-hidden group border-none bg-blue-600 hover:scale-[1.02] transition-transform duration-300 shadow-lg shadow-blue-500/20">
            <div class="absolute right-[-10%] top-[-10%] opacity-15 group-hover:scale-110 transition-transform duration-500">
                <flux:icon.academic-cap class="size-28 text-white" />
            </div>
            <div class="space-y-1 relative z-10">
                <div class="text-[10px] font-black text-blue-200 uppercase tracking-widest">{{ __('Cumulative GPA') }}</div>
                <div class="text-4xl font-black text-white">
                    {{ number_format($cgpa, 2) }}
                </div>
                <div class="text-[10px] text-blue-100 font-bold opacity-80">{{ $total_units }} {{ __('Units Earned') }}</div>
            </div>
        </flux:card>

        {{-- Attendance Card --}}
        <flux:card class="relative overflow-hidden group border-none bg-emerald-600 hover:scale-[1.02] transition-transform duration-300 shadow-lg shadow-emerald-500/20">
            <div class="absolute right-[-10%] top-[-10%] opacity-15 group-hover:scale-110 transition-transform duration-500">
                <flux:icon.presentation-chart-line class="size-28 text-white" />
            </div>
            <div class="space-y-1 relative z-10">
                <div class="text-[10px] font-black text-emerald-100 uppercase tracking-widest">{{ __('Overall Attendance') }}</div>
                <div class="text-4xl font-black text-white">{{ $overall_attendance }}%</div>
                <div class="mt-2">
                    <flux:button variant="subtle" size="xs" href="{{ route('cms.attendance.participation') }}" wire:navigate class="bg-white/10 hover:bg-white/20 border-white/20 text-white font-bold">
                        {{ __('Detailed View') }}
                    </flux:button>
                </div>
            </div>
        </flux:card>

        {{-- Level & Registration Card --}}
        <flux:card class="relative overflow-hidden group border-none bg-zinc-900 dark:bg-zinc-800 hover:scale-[1.02] transition-transform duration-300 shadow-lg">
            <div class="absolute right-[-10%] top-[-10%] opacity-10 group-hover:scale-110 transition-transform duration-500">
                <flux:icon.check-badge class="size-28 text-white" />
            </div>
            <div class="space-y-1 relative z-10">
                <div class="text-[10px] font-black text-zinc-400 uppercase tracking-widest">{{ __('Current Level') }}</div>
                <div class="flex items-center gap-3">
                    <div class="text-4xl font-black text-white">{{ $current_level }}L</div>
                    <flux:badge :color="$registration_status === 'Registered' ? 'green' : 'red'" size="sm" class="font-bold border-none">
                        {{ $registration_status }}
                    </flux:badge>
                </div>
                <div class="text-[10px] text-zinc-400 font-bold opacity-80">{{ $registered_courses_count }} {{ __('Courses Registered') }}</div>
                <div class="pt-2">
                    <flux:button href="{{ route('cms.students.portal-registration') }}" size="xs" variant="subtle" class="bg-white/5 hover:bg-white/10 border-white/10 text-white/70" wire:navigate>
                        {{ __('Manage Registration') }}
                    </flux:button>
                </div>
            </div>
        </flux:card>

        {{-- Outstanding Balance Card --}}
        <flux:card class="relative overflow-hidden group border-none bg-rose-600 hover:scale-[1.02] transition-transform duration-300 shadow-lg shadow-rose-500/20">
            <div class="absolute right-[-10%] top-[-10%] opacity-15 group-hover:scale-110 transition-transform duration-500">
                <flux:icon.banknotes class="size-28 text-white" />
            </div>
            <div class="space-y-1 relative z-10">
                <div class="text-[10px] font-black text-rose-100 uppercase tracking-widest">{{ __('Balance Due') }}</div>
                <div class="text-3xl font-black text-white">₦{{ number_format($pending_balance, 2) }}</div>
                <div class="pt-2">
                    <flux:button href="{{ route('cms.students.portal-invoices') }}" size="sm" variant="subtle" class="bg-white hover:bg-white text-rose-600 font-black border-none shadow-sm" wire:navigate>
                        {{ __('Pay Fees Now') }}
                    </flux:button>
                </div>
            </div>
        </flux:card>
    </div>

    {{-- Secondary Metric Row: Financial Health --}}
    <flux:card class="p-0 overflow-hidden border-zinc-200 dark:border-zinc-800 shadow-sm">
        <div class="flex flex-col md:flex-row divide-y md:divide-y-0 md:divide-x divide-zinc-100 dark:divide-zinc-800">
            <div class="flex-1 p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="sm" class="font-black text-zinc-400 uppercase tracking-widest text-[10px]">{{ __('Detailed Financial Pulse') }}</flux:heading>
                    <flux:button variant="ghost" size="xs" icon="chevron-right" href="{{ route('cms.students.portal-invoices') }}" wire:navigate>{{ __('View All Invoices') }}</flux:button>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex-1 flex flex-col items-center justify-center p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-900 border border-dotted border-zinc-200 dark:border-zinc-800">
                        <div class="text-2xl font-black text-zinc-900 dark:text-white">{{ $paid_invoices_count }}</div>
                        <div class="text-[10px] font-black text-zinc-400 uppercase tracking-widest">{{ __('Fully Paid') }}</div>
                        <div class="mt-2 w-full h-1 bg-green-500 rounded-full opacity-50"></div>
                    </div>
                    <div class="flex-1 flex flex-col items-center justify-center p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-900 border border-dotted border-zinc-200 dark:border-zinc-800">
                        <div class="text-2xl font-black text-zinc-900 dark:text-white">{{ $partial_invoices_count }}</div>
                        <div class="text-[10px] font-black text-zinc-400 uppercase tracking-widest">{{ __('Partial') }}</div>
                        <div class="mt-2 w-full h-1 bg-orange-500 rounded-full opacity-50"></div>
                    </div>
                    <div class="flex-1 flex flex-col items-center justify-center p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-900 border border-dotted border-zinc-200 dark:border-zinc-800">
                        <div class="text-2xl font-black text-zinc-900 dark:text-white">{{ $unpaid_invoices_count }}</div>
                        <div class="text-[10px] font-black text-zinc-400 uppercase tracking-widest">{{ __('Unpaid') }}</div>
                        <div class="mt-2 w-full h-1 bg-rose-500 rounded-full opacity-50"></div>
                    </div>
                </div>
            </div>
            
            <div class="md:w-72 bg-zinc-50/50 dark:bg-zinc-900/30 p-6 flex flex-col justify-center gap-2">
                <flux:heading size="sm" class="text-zinc-400 font-black uppercase tracking-widest text-[10px]">{{ __('Account Health') }}</flux:heading>
                @if($pending_balance > 0)
                    <p class="text-xs text-zinc-500 leading-relaxed">{{ __('Your account has pending obligations. Please settle outstanding fees to ensure continued access to academic resources.') }}</p>
                @else
                    <p class="text-xs text-emerald-600 font-medium leading-relaxed">{{ __('Safe to go! Your financial records are up to date for the current session.') }}</p>
                    <div class="mt-1 flex items-center gap-2 text-emerald-500">
                        <flux:icon.check-circle class="size-4" />
                        <span class="text-[10px] font-black uppercase tracking-widest">{{ __('Clearance Active') }}</span>
                    </div>
                @endif
            </div>
        </div>
    </flux:card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <flux:card class="space-y-6 shadow-sm border-zinc-200 dark:border-zinc-800">
            <div class="flex items-center gap-2 border-b border-zinc-50 dark:border-zinc-800/50 pb-4">
                <flux:icon.academic-cap class="size-5 text-zinc-400" />
                <flux:heading size="lg" class="font-black italic text-zinc-800 dark:text-zinc-200">{{ __('Academic Performance') }}</flux:heading>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800">
                    <div class="text-xs font-bold text-zinc-500 uppercase tracking-widest mb-1">{{ __('Pass Rate') }}
                    </div>
                    <div class="flex items-end gap-2">
                        <div class="text-3xl font-black text-zinc-900 dark:text-white">{{ $pass_rate }}%</div>
                        <div class="text-sm font-bold text-green-600 mb-1">{{ $passed_count }} {{ __('Passed') }}</div>
                    </div>
                    <div class="mt-3 w-full bg-zinc-200 dark:bg-zinc-800 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-green-500 h-full transition-all duration-500" style="width: {{ $pass_rate }}%">
                        </div>
                    </div>
                </div>
                <div class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800">
                    <div class="text-xs font-bold text-zinc-500 uppercase tracking-widest mb-1">{{ __('Failed Courses')
                        }}</div>
                    <div class="text-3xl font-black text-red-600">{{ $failed_count }}</div>
                    <p class="text-[10px] text-zinc-400 mt-2">{{ __('Requires immediate attention / carryover') }}</p>
                </div>
            </div>

            <div class="space-y-3">
                <div
                    class="flex items-center gap-4 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800">
                    <flux:icon.identification class="size-8 text-zinc-400" />
                    <div class="flex-1">
                        <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Examination Card') }}</p>
                        <p class="text-xs text-zinc-500">{{ __('Generate and print your card for the current semester.')
                            }}</p>
                    </div>
                    <flux:button variant="ghost" size="sm" href="{{ route('cms.students.exam-card') }}" wire:navigate>{{
                        __('Generate') }}</flux:button>
                </div>

                <div
                    class="flex items-center gap-4 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800">
                    <flux:icon.chart-bar class="size-8 text-zinc-400" />
                    <div class="flex-1">
                        <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Results Portal') }}</p>
                        <p class="text-xs text-zinc-500">{{ __('Review your performance across all semesters.') }}</p>
                    </div>
                    <flux:button variant="ghost" size="sm" href="{{ route('cms.results.portal') }}" wire:navigate>{{
                        __('Open') }}</flux:button>
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-6 shadow-sm border-zinc-200 dark:border-zinc-800 flex flex-col">
            <div class="flex items-center justify-between border-b border-zinc-50 dark:border-zinc-800/50 pb-4">
                <div class="flex items-center gap-2">
                    <flux:icon.user-circle class="size-5 text-zinc-400" />
                    <flux:heading size="lg" class="font-black italic text-zinc-800 dark:text-zinc-200">{{ __('Profile Summary') }}</flux:heading>
                </div>
                <flux:badge size="sm" color="blue" inset="top bottom" class="font-black">{{ $profile_completion }}%</flux:badge>
            </div>

            <div class="space-y-3">
                <div class="flex justify-between text-[10px] text-zinc-400 uppercase tracking-widest font-black">
                    <span>{{ __('Information Completeness') }}</span>
                </div>
                <div class="w-full bg-zinc-100 dark:bg-zinc-800 rounded-full h-2 overflow-hidden shadow-inner">
                    <div class="bg-blue-600 h-full transition-all duration-1000 shadow-[0_0_10px_rgba(37,99,235,0.4)]" style="width: {{ $profile_completion }}%"></div>
                </div>
            </div>

            <div class="flex-1 space-y-1">
                <div class="flex items-center justify-between py-3 border-b border-zinc-50 dark:border-zinc-800/50 group">
                    <span class="text-xs font-bold text-zinc-400 uppercase tracking-widest">{{ __('Matric Number') }}</span>
                    <span class="text-sm font-black font-mono text-zinc-700 dark:text-zinc-300">{{ $student?->matric_number ?? '—' }}</span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-zinc-50 dark:border-zinc-800/50">
                    <span class="text-xs font-bold text-zinc-400 uppercase tracking-widest">{{ __('Department') }}</span>
                    <span class="text-sm font-black text-right text-zinc-700 dark:text-zinc-300">{{ $student?->program?->department?->name ?? '—' }}</span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-zinc-50 dark:border-zinc-800/50">
                    <span class="text-xs font-bold text-zinc-400 uppercase tracking-widest">{{ __('Program') }}</span>
                    <span class="text-sm font-black text-right text-zinc-700 dark:text-zinc-300">{{ $student?->program?->name ?? '—' }}</span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-zinc-50 dark:border-zinc-800/50">
                    <span class="text-xs font-bold text-zinc-400 uppercase tracking-widest">{{ __('Institution') }}</span>
                    <span class="text-sm font-black text-right text-zinc-700 dark:text-zinc-300">{{ $student?->institution?->name ?? '—' }}</span>
                </div>
            </div>

            <flux:button class="w-full mt-4 bg-zinc-50 dark:bg-zinc-900 border-zinc-200 dark:border-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-800 font-bold" href="{{ route('profile.edit') }}" variant="subtle" size="sm" icon="pencil-square" wire:navigate>
                {{ __('Update Bio-data') }}
            </flux:button>
        </flux:card>
    </div>
</div>