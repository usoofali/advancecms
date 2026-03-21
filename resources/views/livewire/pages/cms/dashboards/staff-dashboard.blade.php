<div class="space-y-8">
    {{-- Uniform Welcome for all Staff --}}
    <div class="flex items-center gap-4">
        <flux:avatar :name="auth()->user()->name" size="xl" />
        <div>
            <flux:heading size="xl">{{ __('Welcome back,') }} {{ auth()->user()->name }}</flux:heading>
            <flux:subheading>
                {{ $staff?->designation ?? __('Staff Member') }} 
                @if($is_hod) <span class="text-blue-600 font-bold">({{ __('HOD') }})</span> @endif
                &bull; {{ $department_names }}
            </flux:subheading>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6 gap-4">
        {{-- Lecturer Specifics --}}
        @can('view_assigned_courses')
        <flux:card class="flex items-center gap-4">
            <div class="size-10 rounded-lg bg-blue-50 dark:bg-blue-950 flex items-center justify-center">
                <flux:icon.book-open class="size-6 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
                <div class="text-xs font-bold text-zinc-500 uppercase tracking-widest">{{ __('Assigned') }}</div>
                <div class="text-2xl font-black text-zinc-900 dark:text-white">{{ $allocations_count }}</div>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4">
            <div class="size-10 rounded-lg bg-indigo-50 dark:bg-indigo-950 flex items-center justify-center">
                <flux:icon.users class="size-6 text-indigo-600 dark:text-indigo-400" />
            </div>
            <div>
                <div class="text-xs font-bold text-zinc-500 uppercase tracking-widest">{{ __('Enrolled') }}</div>
                <div class="text-2xl font-black text-zinc-900 dark:text-white">{{ $total_registered_students }}</div>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4">
            <div class="size-10 rounded-lg bg-teal-50 dark:bg-teal-950 flex items-center justify-center">
                <flux:icon.document-check class="size-6 text-teal-600 dark:text-teal-400" />
            </div>
            <div>
                <div class="text-xs font-bold text-zinc-500 uppercase tracking-widest">{{ __('Results') }}</div>
                <div class="text-2xl font-black text-zinc-900 dark:text-white">{{ $submission_progress }}%</div>
            </div>
        </flux:card>
        @endcan

        {{-- Accountant Specifics --}}
        @can('record_payments')
        <flux:card class="flex items-center gap-4 border-orange-100 dark:border-orange-900/30">
            <div class="size-10 rounded-lg bg-orange-50 dark:bg-orange-950 flex items-center justify-center">
                <flux:icon.clock class="size-6 text-orange-600 dark:text-orange-400" />
            </div>
            <div>
                <div class="text-xs font-bold text-zinc-500 uppercase tracking-widest">{{ __('Pending') }}</div>
                <div class="text-2xl font-black text-zinc-900 dark:text-white">{{ $pending_payments_count }}</div>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4">
            <div class="size-10 rounded-lg bg-green-50 dark:bg-green-950 flex items-center justify-center">
                <flux:icon.check-circle class="size-6 text-green-600 dark:text-green-400" />
            </div>
            <div>
                <div class="text-xs font-bold text-zinc-500 uppercase tracking-widest">{{ __('Verified Today') }}</div>
                <div class="text-2xl font-black text-zinc-900 dark:text-white">{{ $today_verified_count }}</div>
            </div>
        </flux:card>
        @endcan

        {{-- Departmental Broad Metric --}}
        <flux:card class="flex items-center gap-4">
            <div class="size-10 rounded-lg bg-purple-50 dark:bg-purple-950 flex items-center justify-center">
                <flux:icon.user-group class="size-6 text-purple-600 dark:text-purple-400" />
            </div>
            <div>
                <div class="text-xs font-bold text-zinc-500 uppercase tracking-widest">{{ __('Dept. Students') }}</div>
                <div class="text-2xl font-black text-zinc-900 dark:text-white">{{ $total_dept_students }}</div>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4">
            <div class="size-10 rounded-lg bg-pink-50 dark:bg-pink-950 flex items-center justify-center">
                <flux:icon.command-line class="size-6 text-pink-600 dark:text-pink-400" />
            </div>
            <div>
                <div class="text-xs font-bold text-zinc-500 uppercase tracking-widest">{{ __('Dept. Programs') }}</div>
                <div class="text-2xl font-black text-zinc-900 dark:text-white">{{ $total_dept_programs }}</div>
            </div>
        </flux:card>

        <flux:card class="flex flex-col justify-center items-center text-center bg-blue-600 border-none text-white py-2 px-4 rounded-xl">
            <div class="text-[10px] font-bold uppercase tracking-widest opacity-80">{{ __('Active Session') }}</div>
            <div class="text-sm font-black">{{ $activeSession ? $activeSession->name : '—' }}</div>
        </flux:card>
    </div>

    <flux:card class="space-y-6">
        <flux:heading size="lg">{{ __('Assigned Responsibilities & Tools') }}</flux:heading>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {{-- Program Management --}}
            @can('view_dept_programs')
            <flux:card class="p-4 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition-colors border border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-3 mb-3">
                    <flux:icon.command-line class="size-5 text-pink-600" />
                    <span class="font-bold text-sm">{{ __('Dept. Programs') }}</span>
                </div>
                <p class="text-xs text-zinc-500 mb-4">{{ __('Manage academic programs, durations, and award types in your department.') }}</p>
                <flux:button class="w-full" size="sm" variant="ghost" href="{{ route('cms.programs.index') }}" wire:navigate>{{ __('View Programs') }}</flux:button>
            </flux:card>
            @endcan
            {{-- Teaching Tools --}}
            @can('enter_results')
            <flux:card class="p-4 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition-colors border border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-3 mb-3">
                    <flux:icon.pencil-square class="size-5 text-blue-600" />
                    <span class="font-bold text-sm">{{ __('Result Entry') }}</span>
                </div>
                <p class="text-xs text-zinc-500 mb-4">{{ __('Upload and manage student examination results for your courses.') }}</p>
                <flux:button class="w-full" size="sm" variant="ghost" href="{{ route('cms.results.entry') }}" wire:navigate>{{ __('Open Results') }}</flux:button>
            </flux:card>
            @endcan

            {{-- Financial Tools --}}
            @can('manage_invoices')
            <flux:card class="p-4 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition-colors border border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-3 mb-3">
                    <flux:icon.banknotes class="size-5 text-orange-600" />
                    <span class="font-bold text-sm">{{ __('Invoice Management') }}</span>
                </div>
                <p class="text-xs text-zinc-500 mb-4">{{ __('Create templates and manage materialized student invoices.') }}</p>
                <flux:button class="w-full" size="sm" variant="ghost" href="{{ route('cms.invoices.index') }}" wire:navigate>{{ __('Go to Invoices') }}</flux:button>
            </flux:card>
            @endcan

            {{-- Attendance Tools --}}
            @can('take_attendance')
            <flux:card class="p-4 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition-colors border border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-3 mb-3">
                    <flux:icon.calendar class="size-5 text-teal-600" />
                    <span class="font-bold text-sm">{{ __('Take Attendance') }}</span>
                </div>
                <p class="text-xs text-zinc-500 mb-4">{{ __('Record student participation for your current allocated courses.') }}</p>
                <flux:button class="w-full" size="sm" variant="ghost" href="{{ route('cms.attendance.take') }}" wire:navigate>{{ __('Record Sessions') }}</flux:button>
            </flux:card>
            @endcan

            @can('view_attendance_history')
            <flux:card class="p-4 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition-colors border border-zinc-100 dark:border-zinc-800 focus:outline focus:outline-blue-600">
                <div class="flex items-center gap-3 mb-3">
                    <flux:icon.clock class="size-5 text-indigo-600" />
                    <span class="font-bold text-sm">{{ __('Attendance & Earnings') }}</span>
                </div>
                <p class="text-xs text-zinc-500 mb-4">{{ __('View past attendance records and estimate your monthly receivables.') }}</p>
                <flux:button class="w-full" size="sm" variant="ghost" href="{{ route('cms.attendance.history') }}" wire:navigate>{{ __('View History') }}</flux:button>
            </flux:card>
            @endcan

            {{-- Accountant Payment Management --}}
            @can('manage_attendance_payments')
            <flux:card class="p-4 bg-emerald-50 dark:bg-emerald-950/20 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 transition-colors border border-emerald-100 dark:border-emerald-800">
                <div class="flex items-center gap-3 mb-3">
                    <flux:icon.banknotes class="size-5 text-emerald-600" />
                    <span class="font-bold text-sm">{{ __('Lecturer Payments') }}</span>
                </div>
                <p class="text-xs text-emerald-800/70 dark:text-emerald-400/70 mb-4">{{ __('Manage monthly contact tallies and process lecturer attendance payments.') }}</p>
                <flux:button class="w-full" size="sm" variant="ghost" href="{{ route('cms.attendance.payments') }}" wire:navigate>{{ __('Manage Payments') }}</flux:button>
            </flux:card>
            @endcan

            {{-- Student Management Tools --}}
            @can('view_dept_students')
            <flux:card class="p-4 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition-colors border border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-3 mb-3">
                    <flux:icon.users class="size-5 text-purple-600" />
                    <span class="font-bold text-sm">{{ __('Student Records') }}</span>
                </div>
                <p class="text-xs text-zinc-500 mb-4">{{ __('Access student profiles, registration status, and academic history.') }}</p>
                <flux:button class="w-full" size="sm" variant="ghost" href="{{ route('cms.students.index') }}" wire:navigate>{{ __('Open Directory') }}</flux:button>
            </flux:card>
            @endcan
        </div>
    </flux:card>
</div>
