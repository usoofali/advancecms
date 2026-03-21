<?php

use App\Models\Student;
use App\Models\AcademicSession;
use App\Models\Semester;
use App\Models\CourseAllocation;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('My Lecturers')] class extends Component {
    public ?Student $student = null;
    public int|string $session_id = '';
    public int|string $semester_id = '';

    public function mount(): void
    {
        $user = auth()->user();
        $this->student = Student::where('email', $user->email)->first();
        
        $activeSession = AcademicSession::where('status', 'active')->first();
        if ($activeSession) {
            $this->session_id = $activeSession->id;
        }
    }

    public function render(): \Illuminate\View\View
    {
        $allocations = collect();
        
        if ($this->student && $this->session_id && $this->semester_id) {
            $registeredCourseIds = $this->student->courseRegistrations()
                ->where('academic_session_id', $this->session_id)
                ->where('semester_id', $this->semester_id)
                ->pluck('course_id');

            if ($registeredCourseIds->isNotEmpty()) {
                $allocations = CourseAllocation::with(['course', 'user'])
                    ->whereIn('course_id', $registeredCourseIds)
                    ->where('academic_session_id', $this->session_id)
                    ->where('semester_id', $this->semester_id)
                    ->get();
            }
        }

        return view('pages.cms.students.my-lecturers', [
            'sessions' => AcademicSession::orderBy('name', 'desc')->get(),
            'semesters' => $this->session_id ? Semester::where('academic_session_id', $this->session_id)->get() : collect(),
            'allocations' => $allocations,
        ]);
    }
}; ?>

<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('My Lecturers') }}</flux:heading>
            <flux:subheading>{{ __('Contact details for your course instructors') }}</flux:subheading>
        </div>
    </div>

    @if (!$student)
    <flux:card>
        <div class="p-8 text-center text-zinc-500">
            {{ __('Student record not found.') }}
        </div>
    </flux:card>
    @else
    <flux:card class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <flux:select wire:model.live="session_id" :label="__('Academic Session')">
                <option value="null">{{ __('Select Session') }}</option>
                @foreach ($sessions as $session)
                <option value="{{ $session->id }}">{{ $session->name }}</option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="semester_id" :label="__('Semester')" :disabled="!$session_id">
                <option value="null">{{ __('Select Semester') }}</option>
                @foreach ($semesters as $semester)
                <option value="{{ $semester->id }}">{{ ucfirst($semester->name) }}</option>
                @endforeach
            </flux:select>
        </div>

        @if ($semester_id)
        <div class="space-y-4">
            @if (count($allocations) > 0)
            @foreach ($allocations as $allocation)
            <div
                class="p-4 border border-zinc-200 dark:border-zinc-700 rounded-xl flex items-center justify-between hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                <div class="flex items-center gap-4">
                    <div
                        class="w-12 h-12 bg-zinc-100 dark:bg-zinc-800 rounded-full flex items-center justify-center text-zinc-500 font-bold border border-zinc-200 dark:border-zinc-700">
                        {{ substr($allocation->user->name, 0, 1) }}
                    </div>
                    <div>
                        <h4 class="font-semibold text-zinc-900 dark:text-white">{{ $allocation->user->name }}</h4>
                        <div class="text-sm text-zinc-500 font-mono uppercase tracking-tight">
                            {{ $allocation->course->course_code }}: {{ $allocation->course->title }}
                        </div>
                    </div>
                </div>
                <div class="flex flex-col items-end gap-1">
                    <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                        <flux:icon.envelope class="size-4" />
                        <a href="mailto:{{ $allocation->user->email }}" class="hover:text-blue-500">{{
                            $allocation->user->email }}</a>
                    </div>
                    @php $staff = \App\Models\Staff::where('email', $allocation->user->email)->first(); @endphp
                    @if ($staff && $staff->phone)
                    <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                        <flux:icon.phone class="size-4" />
                        <span>{{ $staff->phone }}</span>
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
            @else
            <div class="p-12 text-center border-2 border-dashed rounded-2xl text-zinc-400">
                {{ __('No courses registered or lecturers assigned for this semester.') }}
            </div>
            @endif
        </div>
        @else
        <div class="p-12 text-center border-2 border-dashed rounded-2xl text-zinc-400">
            {{ __('Please select a session and semester to view lecturer contacts.') }}
        </div>
        @endif
    </flux:card>
    @endif
</div>