<?php

use App\Mail\AttendanceSubmitted;
use App\Models\Attendance;
use App\Models\AttendanceRecord;
use App\Models\CourseAllocation;
use App\Models\CourseRegistration;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Take Attendance')] class extends Component
{
    public array $selected_allocation_ids = [];

    public string $date = '';

    public array $attendance_data = []; // student_id => is_present

    public string $search = '';

    public bool $is_submitting = false;

    public function mount(): void
    {
        $this->date = date('Y-m-d');
        Gate::authorize('take_attendance');
    }

    public function getAllocationsProperty()
    {
        $query = CourseAllocation::with(['course', 'academicSession', 'semester', 'user']);

        if (auth()->user()->institution_id) {
            $query->where('institution_id', auth()->user()->institution_id);
        }

        // If not Institutional Admin/Super Admin, filter by own user_id
        if (! auth()->user()->hasRole('Institutional Admin') && ! auth()->user()->hasRole('Super Admin')) {
            $query->where('user_id', auth()->user()->id);
        }

        return $query->latest()->get();
    }

    public function updatedSelectedAllocationIds(): void
    {
        $this->attendance_data = [];

        if (empty($this->selected_allocation_ids)) {
            return;
        }

        foreach ($this->selected_allocation_ids as $allocationId) {
            $allocation = CourseAllocation::find($allocationId);
            if (! $allocation) {
                continue;
            }

            $students = CourseRegistration::where([
                'course_id' => $allocation->course_id,
                'academic_session_id' => $allocation->academic_session_id,
                'semester_id' => $allocation->semester_id,
                'status' => 'registered',
            ])->get();

            foreach ($students as $reg) {
                $this->attendance_data[$reg->student_id] = true;
            }
        }
    }

    public function submit(): void
    {
        $this->validate([
            'selected_allocation_ids' => ['required', 'array', 'min:1'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'attendance_data' => ['required', 'array', 'min:1'],
        ]);

        $groupId = (string) Str::uuid();

        DB::transaction(function () use ($groupId) {
            foreach ($this->selected_allocation_ids as $index => $allocationId) {
                $allocation = CourseAllocation::findOrFail($allocationId);
                
                // Get students registered for THIS specific allocation
                $regStudentIds = CourseRegistration::where([
                    'course_id' => $allocation->course_id,
                    'academic_session_id' => $allocation->academic_session_id,
                    'semester_id' => $allocation->semester_id,
                    'status' => 'registered',
                ])->pluck('student_id')->toArray();

                $relevantData = collect($this->attendance_data)
                    ->filter(fn($val, $key) => in_array($key, $regStudentIds));

                $total_present = $relevantData->filter()->count();
                $total_absent = $relevantData->count() - $total_present;

                $attendance = Attendance::create([
                    'institution_id' => $allocation->institution_id,
                    'course_allocation_id' => $allocation->id,
                    'date' => $this->date,
                    'total_present' => $total_present,
                    'total_absent' => $total_absent,
                    'status' => 'submitted',
                    'is_combined_child' => $index > 0, // First one is primary (false), others are child (true)
                    'combined_group_id' => $groupId,
                ]);

                foreach ($relevantData as $studentId => $isPresent) {
                    AttendanceRecord::create([
                        'attendance_id' => $attendance->id,
                        'student_id' => $studentId,
                        'is_present' => $isPresent,
                    ]);
                }

                // Send Notifications (only for the overall combined session or per course?)
                // User requirement: "for the lecturer the attendance is one"
                // But HODs/Admins need to know. We'll send for each for accurate reporting.
                $this->sendNotifications($attendance);
            }
        });

        $this->reset(['selected_allocation_ids', 'attendance_data']);
        $this->dispatch('notify', [
            'message' => __('Combined attendance submitted successfully! Records split per course.'),
            'variant' => 'success',
        ]);
    }

    protected function sendNotifications(Attendance $attendance): void
    {
        $allocation = $attendance->courseAllocation;
        $course = $allocation->course;
        $institutionId = $attendance->institution_id;
        $departmentId = $course->department_id;

        // Collect stakeholder emails
        $recipients = collect();

        // 1. HOD of the department
        $hodEmail = Department::find($departmentId)?->hod?->email;
        if ($hodEmail) {
            $recipients->push($hodEmail);
        }

        // 2. Accountant, Academic Secretary, Institutional Admin
        $admins = User::where('institution_id', $institutionId)
            ->whereHas('roles', fn ($q) => $q->whereIn('role_name', [
                'Accountant',
                'Academic Secretary',
                'Institutional Admin',
            ]))
            ->pluck('email');
        $recipients = $recipients->merge($admins);

        $emails = $recipients->unique()->filter()->toArray();

        if (! empty($emails)) {
            Mail::to($emails)->send(new AttendanceSubmitted($attendance));
        }
    }

    public function render(): View
    {
        $students = collect();
        if (!empty($this->selected_allocation_ids)) {
            foreach ($this->selected_allocation_ids as $allocationId) {
                $allocation = CourseAllocation::find($allocationId);
                if ($allocation) {
                    $courseStudents = CourseRegistration::where([
                        'course_id' => $allocation->course_id,
                        'academic_session_id' => $allocation->academic_session_id,
                        'semester_id' => $allocation->semester_id,
                        'status' => 'registered',
                    ])
                        ->with('student')
                        ->get();
                    $students = $students->merge($courseStudents);
                }
            }
            
            $students = $students->unique('student_id')->sortBy(fn ($reg) => $reg->student->last_name);

            if ($this->search) {
                $students = $students->filter(fn($reg) => 
                    Str::contains(strtolower($reg->student->first_name . ' ' . $reg->student->last_name), strtolower($this->search)) ||
                    Str::contains(strtolower($reg->student->matric_number ?? ''), strtolower($this->search))
                );
            }
        }

        return view('pages::cms.attendance.take', [
            'allocations' => $this->allocations,
            'students' => $students,
        ]);
    }
}; ?>

<div class="mx-auto max-w-4xl">
    <div class="mb-8">
        <flux:heading size="xl">{{ __('Take Attendance') }}</flux:heading>
        <flux:subheading>{{ __('Record student participation for your allocated courses') }}</flux:subheading>
        
        @if(!empty($selected_allocation_ids))
            <div class="mt-2">
                <flux:badge color="zinc" size="sm" inset="top bottom">
                    {{ collect($attendance_data)->count() }} students enrolled
                </flux:badge>
                <flux:badge color="green" size="sm" inset="top bottom">
                    {{ collect($attendance_data)->filter()->count() }} present
                </flux:badge>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-8">
        <flux:card>
            <form wire:submit="submit" class="space-y-6">
                <div class="space-y-4">
                    <flux:fieldset :label="__('Select Course Allocations')">
                        <flux:description>{{ __('Select one or more courses for this combined session.') }}</flux:description>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                            @foreach ($allocations as $alloc)
                                <flux:checkbox 
                                    wire:model.live="selected_allocation_ids" 
                                    :value="$alloc->id"
                                    :label="$alloc->course->course_code . ' - ' . $alloc->course->title"
                                />
                            @endforeach
                        </div>
                        <flux:error name="selected_allocation_ids" />
                    </flux:fieldset>

                    <flux:input type="date" wire:model="date" :label="__('Lecture Date')" required />
                </div>

                @if (!empty($selected_allocation_ids) && count($students) > 0)
                    <div class="space-y-4">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <flux:heading size="md">{{ __('Student List') }} ({{ count($students) }})</flux:heading>
                            
                            <div class="flex-1 max-w-sm">
                                <flux:input 
                                    wire:model.live.debounce.300ms="search" 
                                    icon="magnifying-glass" 
                                    placeholder="{{ __('Search by name or matric number...') }}" 
                                    size="sm"
                                />
                            </div>

                            <div class="hidden md:flex gap-2">
                                <flux:button size="xs" variant="ghost" wire:click="$set('attendance_data', {{ json_encode(collect($students)->pluck('student_id')->mapWithKeys(fn($id) => [$id => true])->toArray()) }})">
                                    {{ __('Mark All Present') }}
                                </flux:button>
                                <flux:button size="xs" variant="ghost" wire:click="$set('attendance_data', {{ json_encode(collect($students)->pluck('student_id')->mapWithKeys(fn($id) => [$id => false])->toArray()) }})">
                                    {{ __('Mark All Absent') }}
                                </flux:button>
                            </div>
                        </div>

                        <!-- Mobile List (Cards) -->
                        <div class="grid grid-cols-1 gap-3 md:hidden">
                            @foreach ($students as $reg)
                                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 shadow-sm flex items-center justify-between">
                                    <div class="flex-1 pr-4">
                                        <div class="font-bold text-zinc-900 dark:text-white leading-tight">
                                            {{ $reg->student->last_name }}, {{ $reg->student->first_name }}
                                        </div>
                                        <div class="text-xs font-mono text-zinc-500 uppercase mt-1">
                                            {{ $reg->student->matric_number ?? '—' }}
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-2">
                                        @if($attendance_data[$reg->student_id] ?? false)
                                            <flux:button 
                                                size="sm" 
                                                variant="primary" 
                                                class="bg-emerald-600 hover:bg-emerald-700 border-none !px-3"
                                                wire:click="$set('attendance_data.{{ $reg->student_id }}', false)"
                                            >
                                                {{ __('Present') }}
                                            </flux:button>
                                        @else
                                            <flux:button 
                                                size="sm" 
                                                variant="ghost" 
                                                class="text-zinc-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 border-dashed border-zinc-200"
                                                wire:click="$set('attendance_data.{{ $reg->student_id }}', true)"
                                            >
                                                {{ __('Absent') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Desktop List (Table) -->
                        <div class="hidden md:block border rounded-xl overflow-hidden">
                            <table class="w-full text-left text-sm border-collapse">
                                <thead class="bg-zinc-50 dark:bg-zinc-900">
                                    <tr>
                                        <th class="px-4 py-3 font-semibold text-zinc-900 dark:text-white">{{ __('Student Name') }}</th>
                                        <th class="px-4 py-3 font-semibold text-zinc-900 dark:text-white">{{ __('Matric Number') }}</th>
                                        <th class="px-4 py-3 font-semibold text-center text-zinc-900 dark:text-white">{{ __('Present?') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @foreach ($students as $reg)
                                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                            <td class="px-4 py-3">
                                                <div class="font-medium text-zinc-900 dark:text-white">
                                                    {{ $reg->student->last_name }}, {{ $reg->student->first_name }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="font-mono text-xs uppercase text-zinc-500 dark:text-zinc-400">
                                                    {{ $reg->student->matric_number ?? '—' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <flux:checkbox wire:model="attendance_data.{{ $reg->student_id }}" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile Sticky Actions -->
                        <div class="fixed bottom-0 left-0 right-0 md:hidden bg-white/80 dark:bg-zinc-900/80 backdrop-blur-lg border-t border-zinc-200 dark:border-zinc-700 p-4 pb-safe z-50 shadow-2xl">
                            <div class="flex items-center gap-3">
                                <div class="flex-1 flex gap-2">
                                    <flux:button size="sm" variant="ghost" class="grow" wire:click="$set('attendance_data', {{ json_encode(collect($students)->pluck('student_id')->mapWithKeys(fn($id) => [$id => true])->toArray()) }})">
                                        {{ __('All Present') }}
                                    </flux:button>
                                    <flux:button size="sm" variant="ghost" class="grow" wire:click="$set('attendance_data', {{ json_encode(collect($students)->pluck('student_id')->mapWithKeys(fn($id) => [$id => false])->toArray()) }})">
                                        {{ __('All Absent') }}
                                    </flux:button>
                                </div>
                                <flux:button type="submit" variant="primary" class="shrink-0" wire:loading.attr="disabled">
                                    <flux:icon.check-circle class="size-4" />
                                    <span wire:loading.remove>{{ __('Submit') }}</span>
                                    <span wire:loading>{{ __('...') }}</span>
                                </flux:button>
                            </div>
                        </div>

                        <div class="hidden md:flex justify-end pt-4">
                            <flux:button type="submit" variant="primary" icon="check-circle" wire:loading.attr="disabled">
                                <span wire:loading.remove>{{ __('Submit Attendance') }}</span>
                                <span wire:loading>{{ __('Submitting...') }}</span>
                            </flux:button>
                        </div>
                    </div>
                @elseif (!empty($selected_allocation_ids))
                    <div class="py-12 text-center text-zinc-500 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-dashed">
                        @if($search)
                            <flux:icon.magnifying-glass class="mx-auto size-8 mb-3 opacity-20" />
                            <p>{{ __('No students found matching your search.') }}</p>
                            <flux:button variant="ghost" size="sm" class="mt-2" wire:click="$set('search', '')">{{ __('Clear Search') }}</flux:button>
                        @else
                            <flux:icon.users class="mx-auto size-8 mb-3 opacity-20" />
                            <p>{{ __('No students found registered for this course in the selected session/semester.') }}</p>
                        @endif
                    </div>
                @endif
            </form>
        </flux:card>
    </div>
    <div class="md:hidden h-24"></div> <!-- Mobile spacer for sticky footer -->
</div>
