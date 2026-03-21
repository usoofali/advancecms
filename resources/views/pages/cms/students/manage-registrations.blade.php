<?php

use App\Models\Student;
use App\Models\AcademicSession;
use App\Models\Institution;
use App\Models\Department;
use App\Models\Program;
use App\Models\Semester;
use App\Models\RegistrationStatus;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Manage Registrations')] class extends Component {
    use WithPagination;

    public int|string $session_id = '';
    public int|string $semester_id = '';
    public string $institution_id = '';
    public string $department_id = '';
    public string $program_id = '';
    public string $search = '';

    public function mount(): void
    {
        $activeSession = AcademicSession::where('status', 'active')->first();
        if ($activeSession) {
            $this->session_id = $activeSession->id;
        }

        // Initialize institution for non-super admins
        if (auth()->check() && !auth()->user()->hasRole('Super Admin')) {
            $this->institution_id = auth()->user()->institution_id;
        }
    }

    public function updatedInstitutionId(): void
    {
        $this->department_id = '';
        $this->program_id = '';
        $this->resetPage();
    }

    public function updatedDepartmentId(): void
    {
        $this->program_id = '';
        $this->resetPage();
    }

    public function updatedProgramId(): void
    {
        $this->resetPage();
    }

    public function updatedSessionId(): void
    {
        $this->semester_id = '';
        $this->resetPage();
    }

    public function updatedSemesterId(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public ?int $actionStudentId = null;
    public string $actionStudentName = '';
    public bool $isUnlocking = false;

    public function confirmToggle(int $studentId, string $studentName, bool $isUnlocking): void
    {
        $this->actionStudentId = $studentId;
        $this->actionStudentName = $studentName;
        $this->isUnlocking = $isUnlocking;
        $this->js('$flux.modal("confirm-toggle").show()');
    }

    public function executeToggle(): void
    {
        if (! $this->actionStudentId) {
            return;
        }

        $studentId = $this->actionStudentId;
        $user = auth()->user();

        if (!$user->can('manage_registration_status')) {
            return;
        }

        $student = clone Student::find($studentId);
        
        if (!$student) {
            return;
        }

        $institutionId = $user->institution_id ?? $student->institution_id;

        $status = RegistrationStatus::updateOrCreate(
            [
                'institution_id' => $institutionId,
                'student_id' => $studentId,
                'academic_session_id' => $this->session_id,
                'semester_id' => $this->semester_id,
            ]
        );

        if ($status->status === 'open') {
            $status->update([
                'status' => 'closed',
                'closed_by' => $user->id,
                'closed_at' => now(),
            ]);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Registration closed successfully.']);
        } else {
            $status->update([
                'status' => 'open',
                'closed_by' => null,
                'closed_at' => null,
            ]);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Registration opened successfully.']);
        }

        $this->actionStudentId = null;
        $this->actionStudentName = '';
        $this->js('$flux.modal("confirm-toggle").close()');
    }

    public function with(): array
    {
        $students = collect();
        if ($this->session_id && $this->semester_id) {
            $students = Student::query()
                ->when($this->institution_id, function ($q) {
                    $q->where('institution_id', $this->institution_id);
                })
                ->when(!$this->institution_id, function ($q) {
                    $q->where('institution_id', auth()->user()->institution_id);
                })
                ->when($this->department_id, function ($q) {
                    $q->whereHas('program', function ($pq) {
                        $pq->where('department_id', $this->department_id);
                    });
                })
                ->when($this->program_id, function ($q) {
                    $q->where('program_id', $this->program_id);
                })
                ->when($this->search, function ($query) {
                    $query->where(function ($q) {
                        $q->where('first_name', 'like', "%{$this->search}%")
                            ->orWhere('last_name', 'like', "%{$this->search}%")
                            ->orWhere('matric_number', 'like', "%{$this->search}%");
                    });
                })
                ->with(['courseRegistrations' => function ($query) {
                    $query->where('academic_session_id', $this->session_id)
                        ->where('semester_id', $this->semester_id);
                }])
                ->paginate(10);

            // Append status info manually since it's a separate table
            foreach ($students as $student) {
                $student->reg_status = RegistrationStatus::where('student_id', $student->id)
                    ->where('academic_session_id', $this->session_id)
                    ->where('semester_id', $this->semester_id)
                    ->first();
            }
        }

        $institutions = auth()->user()->hasRole('Super Admin') ? Institution::all() : collect();
        
        $departments = collect();
        if ($this->institution_id) {
            $departments = Department::where('institution_id', $this->institution_id)->get();
        }

        $programs = collect();
        if ($this->department_id) {
            $programs = Program::where('department_id', $this->department_id)->get();
        }

        return [
            'institutions' => $institutions,
            'departments' => $departments,
            'programs' => $programs,
            'sessions' => AcademicSession::orderBy('name', 'desc')->get(),
            'semesters' => $this->session_id ? Semester::where('academic_session_id', $this->session_id)->get() : [],
            'students' => $students,
        ];
    }
}; ?>

<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Manage Registrations') }}</flux:heading>
            <flux:subheading>{{ __('Verify and lock student course registrations') }}</flux:subheading>
        </div>
    </div>

    <flux:card class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-6 gap-4">
            @if(auth()->user()->hasRole('Super Admin'))
            <flux:select wire:model.live="institution_id" :label="__('Institution')" class="lg:col-span-2">
                <option value="">{{ __('Select Institution') }}</option>
                @foreach ($institutions as $inst)
                <option value="{{ $inst->id }}">{{ $inst->name }}</option>
                @endforeach
            </flux:select>
            @endif

            <flux:select wire:model.live="department_id" :label="__('Department')" class="lg:col-span-2">
                <option value="">{{ __('All Departments') }}</option>
                @foreach ($departments as $dept)
                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="program_id" :label="__('Program')" :disabled="!$department_id" class="lg:col-span-2">
                <option value="">{{ __('All Programs') }}</option>
                @foreach ($programs as $prog)
                <option value="{{ $prog->id }}">{{ $prog->name }}</option>
                @endforeach
            </flux:select>
            
            <flux:select wire:model.live="session_id" :label="__('Academic Session')" class="lg:col-span-2">
                <option value="">{{ __('Select Session') }}</option>
                @foreach ($sessions as $session)
                <option value="{{ $session->id }}">{{ $session->name }}</option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="semester_id" :label="__('Semester')" :disabled="!$session_id" class="lg:col-span-2">
                <option value="">{{ __('Select Semester') }}</option>
                @foreach ($semesters as $semester)
                <option value="{{ $semester->id }}">{{ ucfirst($semester->name) }}</option>
                @endforeach
            </flux:select>

            <flux:input wire:model.live.debounce.300ms="search" :label="__('Search Student')" class="lg:col-span-2"
                placeholder="{{ __('Name or Matric Number...') }}" icon="magnifying-glass" />
        </div>

        @if ($session_id && $semester_id)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-800">
                        <th class="py-3 px-4 text-left font-bold uppercase text-[10px] tracking-widest text-zinc-500">{{
                            __('Student') }}</th>
                        <th class="py-3 px-4 text-center font-bold uppercase text-[10px] tracking-widest text-zinc-500">
                            {{ __('Units') }}</th>
                        <th class="py-3 px-4 text-center font-bold uppercase text-[10px] tracking-widest text-zinc-500">
                            {{ __('Status') }}</th>
                        <th class="py-3 px-4 text-right font-bold uppercase text-[10px] tracking-widest text-zinc-500">
                            {{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($students as $student)
                    @php
                    $units = $student->courseRegistrations->sum(fn($r) => $r->course->credit_unit ?? 0);
                    $isClosed = $student->reg_status && $student->reg_status->status === 'closed';
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/50 transition-colors">
                        <td class="py-4 px-4">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-8 h-8 rounded-lg bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center font-bold text-zinc-500">
                                    {{ substr($student->first_name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="font-bold text-zinc-900 dark:text-white">{{ $student->full_name }}</div>
                                    <div class="text-xs font-mono text-zinc-500">{{ $student->matric_number }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4 text-center font-mono font-bold">{{ $units }}</td>
                        <td class="py-4 px-4 text-center">
                            @if ($isClosed)
                            <flux:badge color="red" size="sm" inset="top bottom">{{ __('Closed') }}</flux:badge>
                            @else
                            <flux:badge color="green" size="sm" inset="top bottom">{{ __('Open') }}</flux:badge>
                            @endif
                        </td>
                        <td class="py-4 px-4">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button size="sm" variant="ghost" icon="eye"
                                    :href="route('cms.students.course-form', ['student' => $student->id, 'session' => $session_id, 'semester' => $semester_id])"
                                    wire:navigate title="{{ __('View Course Form') }}" />

                                @if ($isClosed)
                                <flux:button size="sm" variant="danger" icon="lock-closed"
                                    wire:click="confirmToggle({{ $student->id }}, '{{ addslashes($student->full_name) }}', true)" />
                                @else
                                <flux:button size="sm" variant="primary" icon="lock-open"
                                    wire:click="confirmToggle({{ $student->id }}, '{{ addslashes($student->full_name) }}', false)"
                                    :disabled="$units === 0" />
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="p-12 text-center text-zinc-400">
                            {{ __('No students found for this selection.') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($students->hasPages())
        <div class="mt-4">
            {{ $students->links() }}
        </div>
        @endif
        @else
        <div class="p-12 text-center border-2 border-dashed rounded-2xl text-zinc-400">
            {{ __('Please select a session and semester to manage registrations.') }}
        </div>
        @endif
    </flux:card>

    <flux:modal name="confirm-toggle" class="min-w-[400px]">
        <form wire:submit="executeToggle" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $isUnlocking ? __('Unlock Registration?') : __('Close Registration?') }}</flux:heading>
                <flux:subheading>
                    @if($isUnlocking)
                        {{ __('Are you sure you want to unlock the registration for') }} <strong>{{ $actionStudentName }}</strong>?
                    @else
                        {{ __('Are you sure you want to close the registration for') }} <strong>{{ $actionStudentName }}</strong>? {{ __('This will prevent them from adding or dropping courses.') }}
                    @endif
                </flux:subheading>
            </div>

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" :variant="$isUnlocking ? 'danger' : 'primary'">
                    {{ $isUnlocking ? __('Confirm Unlock') : __('Confirm Close') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>