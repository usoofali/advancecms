<?php

use App\Models\Course;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Add Course')] class extends Component
{
    public int|string|null $institution_id = '';

    public int|string|null $program_id = '';

    public int|string|null $department_id = '';

    public string $course_code = '';

    public string $title = '';

    public int $credit_unit = 2;

    public string $course_type = 'core';

    public int $level = 100;

    public int $semester = 1;

    public string $status = 'active';

    public function mount(): void
    {
        if (auth()->user()->institution_id) {
            $this->institution_id = auth()->user()->institution_id;
        }
    }

    public function updatedInstitutionId(): void
    {
        $this->department_id = 'null';
        $this->program_id = 'null';
    }

    public function updatedDepartmentId(): void
    {
        $this->program_id = 'null';
    }

    public function save(): void
    {
        $this->course_code = strtoupper(str_replace(' ', '', $this->course_code));
        $this->title = strtoupper($this->title);

        $validated = $this->validate([
            'institution_id' => ['required', 'exists:institutions,id'],
            'program_id' => ['required', 'exists:programs,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'course_code' => ['required', 'string', 'size:6', 'regex:/^[A-Z]{3}[0-9]{3}$/'],
            'title' => ['required', 'string', 'max:255'],
            'credit_unit' => ['required', 'integer', 'min:1', 'max:6'],
            'course_type' => ['required', 'in:core,elective'],
            'level' => ['required', 'integer', 'multiple_of:100', 'min:100', 'max:600'],
            'semester' => ['required', 'in:1,2'],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'course_code.regex' => 'The course code must be 3 letters followed by 3 digits (e.g. CSE101).',
            'course_code.size' => 'The course code must be exactly 6 characters.',
        ]);

        Course::create($validated);

        session()->flash('success', 'Course created successfully.');

        $this->redirect(route('cms.courses.index'), navigate: true);
    }

    public function with(): array
    {
        return [
            'institutions' => auth()->user()->institution_id
                ? []
                : Institution::query()->where('status', 'active')->orderBy('name')->get(),
            'departments' => Department::query()
                ->when($this->institution_id, fn ($q) => $q->where('institution_id', $this->institution_id))
                ->orderBy('name')
                ->get(),
            'programs' => Program::query()
                ->when($this->institution_id && $this->institution_id !== 'null', fn ($q) => $q->where('institution_id', $this->institution_id))
                ->when($this->department_id && $this->department_id !== 'null', fn ($q) => $q->where('department_id', $this->department_id))
                ->orderBy('name')
                ->get(),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Add Course') }}</flux:heading>
        <flux:subheading>{{ __('Create a new course offering') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:fieldset>
            <flux:legend>{{ __('Course Details') }}</flux:legend>
            <div class="grid gap-6">
                @if (!auth()->user()->institution_id)
                <flux:select wire:model.live="institution_id" :label="__('Institution')" required>
                    <flux:select.option value="null">{{ __('Select institution...') }}</flux:select.option>
                    @foreach ($institutions as $inst)
                    <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                @endif

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:select wire:model.live="department_id" :label="__('Department')" required>
                        <flux:select.option value="null">{{ __('Select department...') }}</flux:select.option>
                        @foreach ($departments as $dept)
                        <flux:select.option :value="$dept->id">{{ $dept->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="program_id" :label="__('Program')" required :disabled="!$department_id || $department_id === 'null'">
                        <flux:select.option value="null">
                            @if(!$department_id || $department_id === 'null')
                                {{ __('Select department first...') }}
                            @else
                                {{ __('Select program...') }}
                            @endif
                        </flux:select.option>
                        @foreach ($programs as $prog)
                        <flux:select.option :value="$prog->id">{{ $prog->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div class="sm:col-span-1">
                        <flux:input wire:model="course_code" :label="__('Course Code')" required
                            :placeholder="__('e.g. CSC 101')" />
                    </div>
                    <div class="sm:col-span-2">
                        <flux:input wire:model="title" :label="__('Course Title')" required
                            :placeholder="__('e.g. Introduction to Programming')" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <flux:input wire:model="credit_unit" :label="__('Credit Units')" type="number" required />
                    <flux:select wire:model="level" :label="__('Level')">
                        <flux:select.option value="100">100</flux:select.option>
                        <flux:select.option value="200">200</flux:select.option>
                        <flux:select.option value="300">300</flux:select.option>
                        <flux:select.option value="400">400</flux:select.option>
                        <flux:select.option value="500">500</flux:select.option>
                        <flux:select.option value="600">600</flux:select.option>
                    </flux:select>
                    <flux:select wire:model="semester" :label="__('Semester')">
                        <flux:select.option value="1">{{ __('1st Semester') }}</flux:select.option>
                        <flux:select.option value="2">{{ __('2nd Semester') }}</flux:select.option>
                    </flux:select>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:select wire:model="course_type" :label="__('Course Type')">
                        <flux:select.option value="core">{{ __('Core / Compulsory') }}</flux:select.option>
                        <flux:select.option value="elective">{{ __('Elective') }}</flux:select.option>
                    </flux:select>
                    <flux:select wire:model="status" :label="__('Status')">
                        <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                        <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                    </flux:select>
                </div>
            </div>
        </flux:fieldset>

        <div class="flex items-center justify-end gap-3">
            <flux:button :href="route('cms.courses.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Save Course') }}</flux:button>
        </div>
    </form>
</div>
</div>