<?php

use App\Models\Attendance;
use App\Models\AttendanceRecord;
use App\Models\Institution;
use App\Models\User;
use App\Models\Department;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Manage Attendance')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $month = '';

    #[Url]
    public string $year = '';

    #[Url]
    public ?int $institution_id = null;

    #[Url]
    public ?int $lecturer_id = null;

    #[Url]
    public string $search = '';

    public ?int $selectedAttendanceId = null;
    public array $modalStudents = [];

    public function mount(): void
    {
        Gate::authorize('attendance.manage');

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

    public function getLecturersProperty()
    {
        $query = User::whereHas('roles', fn($q) => $q->where('role_name', 'Lecturer'))
            ->orderBy('name');

        if ($this->institution_id) {
            $query->where('institution_id', $this->institution_id);
        }

        return $query->get();
    }

    public function getMonthlyStatsProperty(): array
    {
        $paddedMonth = str_pad($this->month, 2, '0', STR_PAD_LEFT);
        $query = Attendance::whereMonth('attendances.date', $paddedMonth)
            ->whereYear('attendances.date', $this->year)
            ->where('attendances.status', 'submitted')
            ->where('attendances.is_combined_child', false);

        // Apply Polymorphic Scopes for HOD, Academic Secretary, Exam Officer
        $user = auth()->user();
        $deptIds = array_merge(
            $user->getScopedModelIds('Head of Department (HOD)', Department::class),
            $user->getScopedModelIds('Academic Secretary', Department::class),
            $user->getScopedModelIds('Exam Officer', Department::class)
        );
        if (!empty($deptIds)) {
            $query->whereHas('courseAllocation.course', function ($q) use ($deptIds) {
                $q->whereIn('department_id', $deptIds);
            });
        } elseif ($this->institution_id) {
            $query->where('attendances.institution_id', $this->institution_id);
        }

        if ($this->lecturer_id) {
            $query->whereHas('courseAllocation', function ($q) {
                $q->where('user_id', $this->lecturer_id);
            });
        }

        if ($this->search) {
            $query->whereHas('courseAllocation.course', function ($q) {
                $q->where('course_code', 'like', '%'.$this->search.'%')
                  ->orWhere('title', 'like', '%'.$this->search.'%');
            });
        }

        $contacts = $query->count();

        // Calculate total estimated amount using joins for accuracy and performance
        $totalAmount = (clone $query)
            ->join('course_allocations', 'attendances.course_allocation_id', '=', 'course_allocations.id')
            ->join('users', 'course_allocations.user_id', '=', 'users.id')
            ->join('staff', 'users.email', '=', 'staff.email')
            ->sum('staff.attendance_allowance');

        // Calculate total students marked across active sessions
        $attendanceIds = (clone $query)->pluck('attendances.id');
        $studentsMarked = AttendanceRecord::whereIn('attendance_id', $attendanceIds)->count();

        return [
            'contacts' => $contacts,
            'amount' => $totalAmount,
            'students_marked' => $studentsMarked,
        ];
    }

    public function getHistoryProperty()
    {
        $paddedMonth = str_pad($this->month, 2, '0', STR_PAD_LEFT);
        $query = Attendance::with([
            'courseAllocation.course', 
            'courseAllocation.academicSession', 
            'courseAllocation.semester',
            'courseAllocation.user.staff'
        ])
            ->whereMonth('attendances.date', $paddedMonth)
            ->whereYear('attendances.date', $this->year)
            ->latest('attendances.date');

        // Apply Polymorphic Scopes for HOD, Academic Secretary, Exam Officer
        $user = auth()->user();
        $deptIds = array_merge(
            $user->getScopedModelIds('Head of Department (HOD)', Department::class),
            $user->getScopedModelIds('Academic Secretary', Department::class),
            $user->getScopedModelIds('Exam Officer', Department::class)
        );
        if (!empty($deptIds)) {
            $query->whereHas('courseAllocation.course', function ($q) use ($deptIds) {
                $q->whereIn('department_id', $deptIds);
            });
        } elseif ($this->institution_id) {
            $query->where('attendances.institution_id', $this->institution_id);
        }

        if ($this->lecturer_id) {
            $query->whereHas('courseAllocation', function ($q) {
                $q->where('user_id', $this->lecturer_id);
            });
        }

        if ($this->search) {
            $query->whereHas('courseAllocation.course', function ($q) {
                $q->where('course_code', 'like', '%'.$this->search.'%')
                  ->orWhere('title', 'like', '%'.$this->search.'%');
            });
        }

        return $query->paginate(10);
    }

    public function showStudentList(int $attendanceId): void
    {
        $this->selectedAttendanceId = $attendanceId;
        $this->modalStudents = AttendanceRecord::where('attendance_id', $attendanceId)
            ->with('student')
            ->get()
            ->sortBy(fn($record) => $record->student->last_name)
            ->values()
            ->toArray();

        $this->js('$flux.modal("student-list-modal").show()');
    }

    public function render(): View
    {
        return view('pages::cms.attendance.manage', [
            'history' => $this->history,
            'stats' => $this->monthly_stats,
            'lecturers' => $this->lecturers,
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
            <flux:heading size="xl">{{ __('Manage Attendance') }}</flux:heading>
            <flux:subheading>{{ __('Oversee lecture sessions, monthly audits, and payout tallies across all staff') }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @if(auth()->user()->hasRole('Super Admin'))
                <flux:select wire:model.live="institution_id" class="w-56" :placeholder="__('All Institutions')">
                    <flux:select.option value="">{{ __('All Institutions') }}</flux:select.option>
                    @foreach ($this->institutions as $inst)
                        <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            <flux:select wire:model.live="lecturer_id" class="w-48" :placeholder="__('All Lecturers')">
                <flux:select.option value="">{{ __('All Lecturers') }}</flux:select.option>
                @foreach ($lecturers as $lec)
                    <flux:select.option :value="$lec->id">{{ $lec->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="month" class="w-32">
                @foreach ($months as $num => $name)
                    <flux:select.option :value="$num">{{ __($name) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="year" class="w-24">
                @foreach ($years as $y)
                    <flux:select.option :value="$y">{{ $y }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <!-- Filters and Search Bar -->
    <div class="mb-6 flex flex-col md:flex-row gap-4 items-center justify-between">
        <div class="w-full md:w-80">
            <flux:input 
                wire:model.live.debounce.300ms="search" 
                icon="magnifying-glass" 
                placeholder="{{ __('Search Course Code or Title...') }}" 
                size="sm"
            />
        </div>
        <div class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">
            {{ __('Active filters synchronized with persistent URL parameters') }}
        </div>
    </div>

    <!-- KPI Panels -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <flux:card class="bg-blue-600 text-white border-none shadow-sm">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-white/20 rounded-xl">
                    <flux:icon.clock class="size-6" />
                </div>
                <div>
                    <div class="text-xs font-bold uppercase tracking-widest opacity-80">{{ __('Total Contacts Logged') }}</div>
                    <div class="text-3xl font-black">{{ $stats['contacts'] }}</div>
                </div>
            </div>
        </flux:card>

        <flux:card class="bg-violet-600 text-white border-none shadow-sm">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-white/20 rounded-xl">
                    <flux:icon.users class="size-6" />
                </div>
                <div>
                    <div class="text-xs font-bold uppercase tracking-widest opacity-80">{{ __('Students Audited') }}</div>
                    <div class="text-3xl font-black">{{ number_format($stats['students_marked']) }}</div>
                </div>
            </div>
        </flux:card>

        <flux:card class="bg-emerald-600 text-white border-none shadow-sm">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-white/20 rounded-xl">
                    <flux:icon.banknotes class="size-6" />
                </div>
                <div>
                    <div class="text-xs font-bold uppercase tracking-widest opacity-80">{{ __('Payable Allowance (Est.)') }}</div>
                    <div class="text-3xl font-black">₦{{ number_format($stats['amount'], 2) }}</div>
                </div>
            </div>
        </flux:card>
    </div>

    <!-- Main List View -->
    <flux:card>
        <flux:table :paginate="$history">
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Lecturer') }}</flux:table.column>
                <flux:table.column>{{ __('Course Code / Title') }}</flux:table.column>
                <flux:table.column>{{ __('Session / Semester') }}</flux:table.column>
                <flux:table.column align="center">{{ __('Audited Participation') }}</flux:table.column>
                <flux:table.column align="right">{{ __('Allowance') }}</flux:table.column>
                <flux:table.column align="right">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($history as $item)
                    <flux:table.row :key="$item->id">
                        <!-- Date -->
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $item->date->format('M d, Y') }}</div>
                            <div class="text-xs text-zinc-500">{{ $item->date->format('l') }}</div>
                        </flux:table.cell>

                        <!-- Lecturer -->
                        <flux:table.cell>
                            <div class="font-bold text-zinc-900 dark:text-white text-sm">{{ $item->courseAllocation->user->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $item->courseAllocation->user->email }}</div>
                        </flux:table.cell>

                        <!-- Course Code / Title -->
                        <flux:table.cell>
                            <div class="font-mono text-sm font-bold text-blue-600 dark:text-blue-400">{{ $item->courseAllocation->course->course_code }}</div>
                            <div class="text-xs text-zinc-600 dark:text-zinc-400 truncate max-w-xs">{{ $item->courseAllocation->course->title }}</div>
                        </flux:table.cell>

                        <!-- Session / Semester -->
                        <flux:table.cell>
                            <div class="text-xs text-zinc-900 dark:text-white font-medium">{{ $item->courseAllocation->academicSession->name }}</div>
                            <div class="text-[10px] uppercase text-zinc-500">{{ ucfirst($item->courseAllocation->semester->name) }}</div>
                        </flux:table.cell>

                        <!-- Audited Participation -->
                        <flux:table.cell align="center">
                            <div class="flex items-center justify-center gap-2">
                                <flux:badge color="green" size="sm" inset="top bottom">{{ $item->total_present }} Present</flux:badge>
                                <flux:badge color="red" size="sm" inset="top bottom">{{ $item->total_absent }} Absent</flux:badge>
                                @if($item->is_combined_child)
                                    <flux:badge color="zinc" size="sm" inset="top bottom">{{ __('Combined') }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>

                        <!-- Allowance -->
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

                        <!-- Actions -->
                        <flux:table.cell align="right">
                            <flux:button variant="ghost" size="xs" icon="eye" wire:click="showStudentList({{ $item->id }})">
                                {{ __('Audit List') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center py-12 text-zinc-500">
                            <flux:icon.calendar class="mx-auto size-8 mb-3 opacity-20" />
                            <p>{{ __('No attendance records found matching your filters.') }}</p>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <!-- Detailed Audited Student List Modal -->
    <flux:modal name="student-list-modal" class="md:w-[550px]">
        <div>
            <flux:heading size="lg" class="mb-4">{{ __('Conducted Session Attendance Roll') }}</flux:heading>
            
            @if ($selectedAttendanceId)
                @php
                    $sessionDetails = \App\Models\Attendance::with(['courseAllocation.course', 'courseAllocation.user'])->find($selectedAttendanceId);
                @endphp
                @if ($sessionDetails)
                    <div class="mb-4 pb-4 border-b border-zinc-100 dark:border-zinc-800 flex justify-between items-start gap-4">
                        <div>
                            <div class="text-sm font-bold text-zinc-900 dark:text-white">
                                {{ $sessionDetails->courseAllocation->course->course_code }} - {{ $sessionDetails->courseAllocation->course->title }}
                            </div>
                            <div class="text-xs text-zinc-500 mt-1">
                                Conducted by <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $sessionDetails->courseAllocation->user->name }}</span>
                            </div>
                        </div>
                        <flux:badge color="zinc" size="sm" class="shrink-0">{{ $sessionDetails->date->format('M d, Y') }}</flux:badge>
                    </div>
                @endif
            @endif

            <div class="max-h-[350px] overflow-y-auto pr-2">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Student') }}</flux:table.column>
                        <flux:table.column align="right">{{ __('Status') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($modalStudents as $record)
                            <flux:table.row :key="$record['id']">
                                <flux:table.cell>
                                    <div class="font-bold text-sm text-zinc-900 dark:text-white leading-tight">
                                        {{ $record['student']['last_name'] }}, {{ $record['student']['first_name'] }}
                                    </div>
                                    <div class="font-mono text-[10px] text-zinc-500 uppercase mt-0.5">
                                        {{ $record['student']['matric_number'] ?? '—' }}
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell align="right">
                                    @if ($record['is_present'])
                                        <flux:badge color="green" size="sm">{{ __('Present') }}</flux:badge>
                                    @else
                                        <flux:badge color="red" size="sm">{{ __('Absent') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="2" class="text-center py-6 text-zinc-500 italic">
                                    {{ __('No student participation entries logged for this session.') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <flux:button variant="ghost" size="sm" onclick="$flux.modal('student-list-modal').close()">{{ __('Dismiss') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
