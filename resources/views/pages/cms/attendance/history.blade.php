<?php

use App\Models\Attendance;
use App\Models\Institution;
use App\Models\Staff;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Attendance History')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $month = '';

    #[Url]
    public string $year = '';

    #[Url]
    public ?int $institution_id = null;

    public function mount(): void
    {
        Gate::authorize('view_attendance_history');

        if (! $this->month) {
            $this->month = date('n');
        }
        if (! $this->year) {
            $this->year = date('Y');
        }

        // Default to user's institution if they are not Super Admin
        if (! auth()->user()->hasRole('Super Admin')) {
            $this->institution_id = auth()->user()->institution_id;
        }
    }

    public function getInstitutionsProperty()
    {
        return Institution::orderBy('name')->get();
    }

    public function getStaffProperty()
    {
        return Staff::where('email', auth()->user()->email)->first();
    }

    public function getMonthlyStatsProperty()
    {
        $staff = $this->staff;
        if (! $staff) {
            return ['contacts' => 0, 'amount' => 0, 'rate' => 0];
        }

        $query = Attendance::whereMonth('date', $this->month)
            ->whereYear('date', $this->year)
            ->where('status', 'submitted')
            ->where('is_combined_child', false);

        // Filter by permissions and selected institution
        if (! auth()->user()->hasRole('Institutional Admin') && ! auth()->user()->hasRole('Super Admin')) {
            $query->whereHas('courseAllocation', function ($q) {
                $q->where('user_id', auth()->user()->id);
            });
        } elseif ($this->institution_id) {
            $query->where('institution_id', $this->institution_id);
        }

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
        $query = Attendance::with([
            'courseAllocation.course', 
            'courseAllocation.academicSession', 
            'courseAllocation.semester',
            'courseAllocation.user.staff'
        ])
            ->whereMonth('date', $this->month)
            ->whereYear('date', $this->year)
            ->latest('date');

        if (! auth()->user()->hasRole('Institutional Admin') && ! auth()->user()->hasRole('Super Admin')) {
            $query->whereHas('courseAllocation', function ($q) {
                $q->where('user_id', auth()->user()->id);
            });
        } elseif ($this->institution_id) {
            $query->where('institution_id', $this->institution_id);
        }

        return $query->paginate(10);
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
            <flux:heading size="xl">{{ __('Attendance History') }}</flux:heading>
            <flux:subheading>{{ __('Review your lecture sessions and monthly contact tallies') }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @if(auth()->user()->hasRole('Super Admin'))
                <flux:select wire:model.live="institution_id" class="w-64" :placeholder="__('All Institutions')">
                    <flux:select.option value="">{{ __('All Institutions') }}</flux:select.option>
                    @foreach ($this->institutions as $inst)
                        <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
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

    @if(!auth()->user()->hasRole('Institutional Admin') && !auth()->user()->hasRole('Super Admin'))
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <flux:card class="bg-blue-600 text-white border-none">
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

        <flux:card class="bg-emerald-600 text-white border-none">
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

        <flux:card class="bg-zinc-100 dark:bg-zinc-900 border-none">
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
    @endif

    <flux:card>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm border-collapse">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-4 py-3 font-semibold text-zinc-900 dark:text-white">{{ __('Date') }}</th>
                        <th class="px-4 py-3 font-semibold text-zinc-900 dark:text-white">{{ __('Course Allocation') }}</th>
                        <th class="px-4 py-3 font-semibold text-zinc-900 dark:text-white">{{ __('Session / Semester') }}</th>
                        <th class="px-4 py-3 font-semibold text-center text-zinc-900 dark:text-white">{{ __('Participation') }}</th>
                        <th class="px-4 py-3 font-semibold text-right text-zinc-900 dark:text-white">{{ __('Receivable (Est.)') }}</th>
                        <th class="px-4 py-3 font-semibold text-right text-zinc-900 dark:text-white">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($history as $item)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $item->date->format('M d, Y') }}</div>
                                <div class="text-xs text-zinc-500">{{ $item->date->format('l') }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-mono text-sm font-bold text-blue-600 dark:text-blue-400">{{ $item->courseAllocation->course->course_code }}</div>
                                <div class="text-xs text-zinc-600 dark:text-zinc-400">{{ Str::limit($item->courseAllocation->course->title, 50) }}</div>
                                @if(auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Institutional Admin'))
                                    <div class="mt-1 text-[10px] font-bold uppercase text-zinc-400">Lecturer: {{ $item->courseAllocation->user->name }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-xs text-zinc-900 dark:text-white font-medium">{{ $item->courseAllocation->academicSession->name }}</div>
                                <div class="text-[10px] uppercase text-zinc-500">{{ ucfirst($item->courseAllocation->semester->name) }}</div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <flux:badge color="green" size="sm" inset="top bottom">{{ $item->total_present }} Present</flux:badge>
                                    <flux:badge color="red" size="sm" inset="top bottom">{{ $item->total_absent }} Absent</flux:badge>
                                    @if($item->is_combined_child)
                                        <flux:badge color="zinc" size="sm" inset="top bottom">{{ __('Combined') }}</flux:badge>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
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
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:badge :color="$item->status === 'submitted' ? 'green' : 'amber'" size="sm">
                                    {{ ucfirst($item->status) }}
                                </flux:badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-zinc-500">
                                <flux:icon.calendar class="mx-auto size-8 mb-3 opacity-20" />
                                <p>{{ __('No attendance records found for this period.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 px-4 pb-4">
            {{ $history->links() }}
        </div>
    </flux:card>
</div>

