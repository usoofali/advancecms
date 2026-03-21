<div class="space-y-8">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6 gap-4">
        @if($is_super_admin)
        <flux:card class="flex items-center gap-4">
            <div class="size-10 rounded-lg bg-blue-50 dark:bg-blue-950 flex items-center justify-center">
                <flux:icon.building-library class="size-6 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
                <div class="text-xs font-bold text-zinc-500 uppercase tracking-widest">{{ __('Institutions') }}</div>
                <div class="text-2xl font-black text-zinc-900 dark:text-white">{{ $institutions_count }}</div>
            </div>
        </flux:card>
        @endif

        <flux:card class="flex items-center gap-4">
            <div class="size-10 rounded-lg bg-indigo-50 dark:bg-indigo-950 flex items-center justify-center">
                <flux:icon.rectangle-group class="size-6 text-indigo-600 dark:text-indigo-400" />
            </div>
            <div>
                <div class="text-xs font-bold text-zinc-500 uppercase tracking-widest">{{ __('Students') }}</div>
                <div class="text-2xl font-black text-zinc-900 dark:text-white">{{ $students_count }}</div>
                <div class="text-[10px] text-zinc-400 font-medium">+{{ $new_enrollments }} {{ __('New') }}</div>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4">
            <div class="size-10 rounded-lg bg-pink-50 dark:bg-pink-950 flex items-center justify-center">
                <flux:icon.identification class="size-6 text-pink-600 dark:text-pink-400" />
            </div>
            <div>
                <div class="text-xs font-bold text-zinc-500 uppercase tracking-widest">{{ __('Staff') }}</div>
                <div class="text-2xl font-black text-zinc-900 dark:text-white">{{ $staff_count }}</div>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4">
            <div class="size-10 rounded-lg bg-purple-50 dark:bg-purple-950 flex items-center justify-center">
                <flux:icon.book-open class="size-6 text-purple-600 dark:text-purple-400" />
            </div>
            <div>
                <div class="text-xs font-bold text-zinc-500 uppercase tracking-widest">{{ __('Courses') }}</div>
                <div class="text-2xl font-black text-zinc-900 dark:text-white">{{ $courses_count }}</div>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4">
            <div class="size-10 rounded-lg bg-orange-50 dark:bg-orange-950 flex items-center justify-center">
                <flux:icon.calendar-days class="size-6 text-orange-600 dark:text-orange-400" />
            </div>
            <div>
                <div class="text-xs font-bold text-zinc-500 uppercase tracking-widest">{{ __('Registrations') }}</div>
                <div class="text-2xl font-black text-zinc-900 dark:text-white">{{ $registration_velocity }}</div>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4">
            <div class="size-10 rounded-lg bg-green-50 dark:bg-green-950 flex items-center justify-center">
                <flux:icon.academic-cap class="size-6 text-green-600 dark:text-green-400" />
            </div>
            <div>
                <div class="text-xs font-bold text-zinc-500 uppercase tracking-widest">{{ __('Avg CGPA') }}</div>
                <div class="text-2xl font-black text-zinc-900 dark:text-white">{{ $average_gpa }}</div>
            </div>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <flux:card class="lg:col-span-3 space-y-6">
            <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-4">
                <flux:heading size="lg">{{ __('Financial Health') }}</flux:heading>
                <div class="flex gap-4">
                    <div class="text-right">
                        <div class="text-[10px] text-zinc-400 uppercase font-bold">{{ __('Collection Efficiency') }}</div>
                        <div class="text-lg font-black text-blue-600">{{ $collection_efficiency }}%</div>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="space-y-1">
                    <div class="text-xs font-bold text-zinc-500 uppercase tracking-widest">{{ __('Total Invoiced') }}</div>
                    <div class="text-2xl font-black text-zinc-900 dark:text-white tracking-tight">₦{{ number_format($total_invoiced, 2) }}</div>
                </div>
                <div class="space-y-1">
                    <div class="text-xs font-bold text-zinc-500 uppercase tracking-widest">{{ __('Total Collected') }}</div>
                    <div class="text-2xl font-black text-green-600 tracking-tight">₦{{ number_format($total_collected, 2) }}</div>
                </div>
                <div class="space-y-1">
                    <div class="text-xs font-bold text-zinc-500 uppercase tracking-widest">{{ __('Outstanding Debt') }}</div>
                    <div class="text-2xl font-black text-red-600 tracking-tight">₦{{ number_format($outstanding_debt, 2) }}</div>
                </div>
            </div>

            <div class="w-full bg-zinc-100 dark:bg-zinc-800 rounded-full h-2.5 overflow-hidden">
                <div class="bg-blue-600 h-full transition-all duration-700" style="width: {{ $collection_efficiency }}%"></div>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <div class="flex gap-6">
                    <div>
                        <span class="text-xs font-bold text-zinc-400 uppercase">{{ __('Pending Invoices') }}:</span>
                        <span class="text-xs font-black text-zinc-900 dark:text-white ml-1">{{ $unpaid_invoices_count }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <flux:button href="{{ route('cms.invoices.index') }}" variant="ghost" size="sm" icon="banknotes" wire:navigate>{{ __('Invoices') }}</flux:button>
                    <flux:button href="{{ route('cms.invoices.payments') }}" variant="ghost" size="sm" icon="check-badge" wire:navigate>{{ __('Verify') }}</flux:button>
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-6">
            <flux:heading size="lg">{{ __('Academic & Demo') }}</flux:heading>
            
            <div class="space-y-4">
                <div class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800">
                    <div class="text-xs font-bold text-zinc-500 uppercase tracking-widest mb-3">{{ __('Gender Distribution') }}</div>
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <div class="text-2xl font-black text-blue-600">{{ $male_count }}</div>
                            <div class="text-[10px] text-zinc-400 uppercase">{{ __('Male') }}</div>
                        </div>
                        <div class="flex-1 border-l border-zinc-200 dark:border-zinc-700 pl-4">
                            <div class="text-2xl font-black text-pink-600">{{ $female_count }}</div>
                            <div class="text-[10px] text-zinc-400 uppercase">{{ __('Female') }}</div>
                        </div>
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800">
                    <div class="text-xs font-bold text-zinc-500 uppercase tracking-widest mb-1">{{ __('Course Failures') }}</div>
                    <div class="text-2xl font-black text-red-600">{{ $failed_results_count }}</div>
                    <p class="text-[10px] text-zinc-400 mt-1 uppercase leading-tight">{{ __('Total failed courses results recorded') }}</p>
                </div>
            </div>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <flux:card class="lg:col-span-3 space-y-4">
            <flux:heading size="lg">{{ __('Quick Access Management') }}</flux:heading>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <flux:button class="w-full justify-start" href="{{ route('cms.departments.index') }}" variant="ghost" icon="rectangle-group" wire:navigate>{{ __('Departments') }}</flux:button>
                <flux:button class="w-full justify-start" href="{{ route('cms.programs.index') }}" variant="ghost" icon="command-line" wire:navigate>{{ __('Programs') }}</flux:button>
                <flux:button class="w-full justify-start" href="{{ route('cms.staff.index') }}" variant="ghost" icon="user-group" wire:navigate>{{ __('Staff') }}</flux:button>
                <flux:button class="w-full justify-start" href="{{ route('cms.students.index') }}" variant="ghost" icon="users" wire:navigate>{{ __('Students') }}</flux:button>
                @if($is_super_admin)
                <flux:button class="w-full justify-start" href="{{ route('cms.institutions.index') }}" variant="ghost" icon="building-office-2" wire:navigate>{{ __('Institutions') }}</flux:button>
                @endif
                <flux:button class="w-full justify-start" href="{{ route('cms.sessions.index') }}" variant="ghost" icon="calendar-date-range" wire:navigate>{{ __('Sessions') }}</flux:button>
                <flux:button class="w-full justify-start" href="{{ route('cms.invoices.index') }}" variant="ghost" icon="banknotes" wire:navigate>{{ __('Fee Setup') }}</flux:button>
                <flux:button class="w-full justify-start" href="{{ route('cms.invoices.payments') }}" variant="ghost" icon="check-badge" wire:navigate>{{ __('Settlements') }}</flux:button>
            </div>
        </flux:card>

        <flux:card class="flex flex-col justify-center items-center text-center p-6 bg-blue-600 border-none text-white">
            <flux:icon.information-circle class="size-10 mb-2 opacity-50" />
            <div class="text-xs font-bold uppercase tracking-widest opacity-80 mb-1">{{ __('Active Session') }}</div>
            <div class="text-2xl font-black">{{ $activeSession ? $activeSession->name : '—' }}</div>
            <div class="text-[10px] mt-2 bg-blue-500/50 px-3 py-1 rounded-full">{{ __('All metrics real-time') }}</div>
        </flux:card>
    </div>
</div>
