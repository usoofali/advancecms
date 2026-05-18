<?php

use App\Models\Attendance;
use App\Models\Staff;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('My Attendance History')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $month = '';

    #[Url]
    public string $year = '';

    public function mount(): void
    {
        Gate::authorize('attendance.view_history');

        if (! $this->month) {
            $this->month = date('n');
        }
        if (! $this->year) {
            $this->year = date('Y');
        }
    }

    public function getStaffProperty(): ?Staff
    {
        return Staff::where('email', auth()->user()->email)->first();
    }

    public function getMonthlyStatsProperty(): array
    {
        $staff = $this->staff;
        if (! $staff) {
            return ['contacts' => 0, 'amount' => 0, 'rate' => 0];
        }

        $paddedMonth = str_pad($this->month, 2, '0', STR_PAD_LEFT);
        $query = Attendance::whereMonth('attendances.date', $paddedMonth)
            ->whereYear('attendances.date', $this->year)
            ->where('attendances.status', 'submitted')
            ->where('attendances.is_combined_child', false)
            ->whereHas('courseAllocation', function ($q) {
                $q->where('user_id', auth()->user()->id);
            });

        $contacts = $query->count();

        // Calculate total estimated amount using joins for accuracy and performance
        $totalAmount = (clone $query)
            ->join('course_allocations', 'attendances.course_allocation_id', '=', 'course_allocations.id')
            ->join('users', 'course_allocations.user_id', '=', 'users.id')
            ->join('staff', 'users.email', '=', 'staff.email')
            ->sum('staff.attendance_allowance');

        return [
            'contacts' => $contacts,
            'amount' => $totalAmount,
            'rate' => $staff->attendance_allowance ?? 0,
        ];
    }

    public function getHistoryProperty()
    {
        $paddedMonth = str_pad($this->month, 2, '0', STR_PAD_LEFT);
        return Attendance::with([
            'courseAllocation.course', 
            'courseAllocation.academicSession', 
            'courseAllocation.semester',
            'courseAllocation.user.staff'
        ])
            ->whereMonth('attendances.date', $paddedMonth)
            ->whereYear('attendances.date', $this->year)
            ->whereHas('courseAllocation', function ($q) {
                $q->where('user_id', auth()->user()->id);
            })
            ->latest('attendances.date')
            ->paginate(10);
    }

    public function render(): View
    {
        return view('pages::cms.attendance.history', [
            'history' => $this->history,
            'stats' => $this->monthly_stats,
            'months' => [
                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
            ],
            'years' => range(date('Y'), date('Y') - 2),
        ]);
    }
}; ?>

<div class="mx-auto max-w-6xl">
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('My Attendance History') }}</flux:heading>
            <flux:subheading>{{ __('Review your personal lecture sessions and monthly contact tallies') }}</flux:subheading>
        </div>

        <div class="flex items-center gap-3">
            <flux:select wire:model.live="month" class="w-36">
                @foreach ($months as $num => $name)
                    <flux:select.option :value="$num">{{ __($name) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="year" class="w-28">
                @foreach ($years as $y)
                    <flux:select.option :value="$y">{{ $y }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <flux:card class="bg-blue-600 text-white border-none shadow-sm">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-white/20 rounded-xl">
                    <flux:icon.clock class="size-6" />
                </div>
                <div>
                    <div class="text-xs font-bold uppercase tracking-widest opacity-80">{{ __('Monthly Contacts') }}</div>
                    <div class="text-3xl font-black">{{ $stats['contacts'] }}</div>
                </div>
            </div>
        </flux:card>

        <flux:card class="bg-emerald-600 text-white border-none shadow-sm">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-white/20 rounded-xl">
                    <flux:icon.banknotes class="size-6" />
                </div>
                <div>
                    <div class="text-xs font-bold uppercase tracking-widest opacity-80">{{ __('Receivable (Est.)') }}</div>
                    <div class="text-3xl font-black">₦{{ number_format($stats['amount'], 2) }}</div>
                </div>
            </div>
        </flux:card>

        <flux:card class="bg-zinc-100 dark:bg-zinc-900 border-none shadow-sm">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-zinc-200 dark:bg-zinc-800 rounded-xl">
                    <flux:icon.information-circle class="size-6 text-zinc-600 dark:text-zinc-400" />
                </div>
                <div>
                    <div class="text-xs font-bold text-zinc-500 uppercase tracking-widest">{{ __('Allowance Rate') }}</div>
                    <div class="text-xl font-bold text-zinc-900 dark:text-white">₦{{ number_format($stats['rate'], 2) }} / contact</div>
                </div>
            </div>
        </flux:card>
    </div>

    <flux:card>
        <flux:table :paginate="$history">
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Course Allocation') }}</flux:table.column>
                <flux:table.column>{{ __('Session / Semester') }}</flux:table.column>
                <flux:table.column align="center">{{ __('Participation') }}</flux:table.column>
                <flux:table.column align="right">{{ __('Receivable (Est.)') }}</flux:table.column>
                <flux:table.column align="right">{{ __('Status') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($history as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $item->date->format('M d, Y') }}</div>
                            <div class="text-xs text-zinc-500">{{ $item->date->format('l') }}</div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="font-mono text-sm font-bold text-blue-600 dark:text-blue-400">{{ $item->courseAllocation->course->course_code }}</div>
                            <div class="text-xs text-zinc-600 dark:text-zinc-400 truncate max-w-xs">{{ $item->courseAllocation->course->title }}</div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="text-xs text-zinc-900 dark:text-white font-medium">{{ $item->courseAllocation->academicSession->name }}</div>
                            <div class="text-[10px] uppercase text-zinc-500">{{ ucfirst($item->courseAllocation->semester->name) }}</div>
                        </flux:table.cell>

                        <flux:table.cell align="center">
                            <div class="flex items-center justify-center gap-2">
                                <flux:badge color="green" size="sm" inset="top bottom">{{ $item->total_present }} Present</flux:badge>
                                <flux:badge color="red" size="sm" inset="top bottom">{{ $item->total_absent }} Absent</flux:badge>
                                @if($item->is_combined_child)
                                    <flux:badge color="zinc" size="sm" inset="top bottom">{{ __('Combined') }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell align="right">
                            @php
                                $itemRate = $item->courseAllocation->user->staff->attendance_allowance ?? 0;
                            @endphp
                            @if($item->is_combined_child)
                                <div class="font-bold text-zinc-400 dark:text-zinc-600">₦0.00</div>
                                <div class="text-[10px] text-zinc-500 uppercase">{{ __('Combined') }}</div>
                            @else
                                <div class="font-bold text-zinc-900 dark:text-white">₦{{ number_format($itemRate, 2) }}</div>
                                <div class="text-[10px] text-zinc-500 uppercase">{{ __('Per Contact') }}</div>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell align="right">
                            <flux:badge :color="$item->status === 'submitted' ? 'green' : 'amber'" size="sm">
                                {{ ucfirst($item->status) }}
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center py-12 text-zinc-500">
                            <flux:icon.calendar class="mx-auto size-8 mb-3 opacity-20" />
                            <p>{{ __('No attendance records found for this period.') }}</p>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
