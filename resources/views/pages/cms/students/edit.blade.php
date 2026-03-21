<?php

use App\Models\Student;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('Edit Student')] class extends Component
{
    use WithFileUploads;

    public Student $student;

    public int|string $institution_id = '';

    public int|string $department_id = '';

    public int|string $program_id = '';

    public string $matric_number = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $gender = '';

    public string $date_of_birth = '';

    public string $email = '';

    public string $phone = '';

    public int $admission_year;

    public int $entry_level = 100;

    public string $status = 'active';

    public $photo;

    public function mount(Student $student): void
    {
        $user_institution_id = auth()->user()->institution_id;
        if ($user_institution_id && $student->institution_id !== $user_institution_id) {
            abort(403, 'Unauthorized. This student record belongs to another institution.');
        }

        $this->student = $student;
        $this->institution_id = $student->institution_id ?? ($user_institution_id ?? '');
        $this->program_id = $student->program_id;
        $this->department_id = $student->program?->department_id ?? '';
        $this->matric_number = $student->matric_number;
        $this->first_name = $student->first_name;
        $this->last_name = $student->last_name;
        $this->gender = $student->gender ?? '';
        $this->date_of_birth = $student->date_of_birth?->format('Y-m-d') ?? '';
        $this->email = $student->email ?? '';
        $this->phone = $student->phone ?? '';
        $this->admission_year = $student->admission_year;
        $this->entry_level = $student->entry_level;
        $this->status = $student->status;
    }

    public function updatedInstitutionId(): void
    {
        $this->department_id = '';
        $this->program_id = '';
    }

    public function updatedDepartmentId(): void
    {
        $this->program_id = '';
    }

    public function save(): void
    {
        $validated = $this->validate([
            'institution_id' => ['required', 'exists:institutions,id'],
            'program_id' => ['required', 'exists:programs,id'],
            'matric_number' => ['required', 'string', 'max:30', 'unique:students,matric_number,'.$this->student->id],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'date_of_birth' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'admission_year' => ['required', 'integer', 'min:1990', 'max:'.((int) date('Y') + 1)],
            'entry_level' => ['required', 'integer', 'multiple_of:100', 'min:100', 'max:600'],
            'status' => ['required', 'in:active,suspended,withdrawn,graduated,deceased'],
            'photo' => ['nullable', 'image', 'max:1024'],
        ]);

        if ($this->photo) {
            // Delete old photo if exists
            if ($this->student->photo_path) {
                Storage::disk('public')->delete($this->student->photo_path);
            }
            $validated['photo_path'] = $this->photo->store('students/photos', 'public');
        }

        $this->student->update($validated);

        session()->flash('success', 'Student record updated successfully.');

        $this->redirect(route('cms.students.index'), navigate: true);
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Edit Student') }}</flux:heading>
        <flux:subheading>{{ $first_name }} {{ $last_name }} ({{ $matric_number }})</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-8" enctype="multipart/form-data">
        <flux:fieldset>
            <flux:legend>{{ __('Profile Photo') }}</flux:legend>
            <div class="flex items-center gap-6">
                <div class="relative group">
                    <div
                        class="w-32 h-32 rounded-2xl bg-zinc-100 dark:bg-zinc-800 border-2 border-dashed border-zinc-200 dark:border-zinc-700 flex items-center justify-center overflow-hidden">
                        @if ($photo)
                        <img src="{{ $photo->temporaryUrl() }}" class="w-full h-full object-cover">
                        @elseif ($student->photo_path)
                        <img src="{{ $student->photo_url }}" class="w-full h-full object-cover">
                        @else
                        <flux:icon icon="camera" class="w-8 h-8 text-zinc-400" />
                        @endif
                    </div>
                </div>
                <div class="flex-1 space-y-2">
                    <flux:input type="file" wire:model="photo" accept="image/*" :label="__('Change Photo')" />
                    <flux:description>{{ __('Max 1MB. JPEG, PNG, or WEBP.') }}</flux:description>
                    <flux:error name="photo" />
                </div>
            </div>
        </flux:fieldset>

        <flux:fieldset>
            <flux:legend>{{ __('Personal Information') }}</flux:legend>
            <div class="grid gap-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:input wire:model="first_name" :label="__('First Name')" required />
                    <flux:input wire:model="last_name" :label="__('Last Name')" required />
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:select wire:model="gender" :label="__('Gender')" required>
                        <flux:select.option value="male">{{ __('Male') }}</flux:select.option>
                        <flux:select.option value="female">{{ __('Female') }}</flux:select.option>
                    </flux:select>
                    <flux:input wire:model="date_of_birth" :label="__('Date of Birth')" type="date" />
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:input wire:model="email" :label="__('Email')" type="email" />
                    <flux:input wire:model="phone" :label="__('Phone')" />
                </div>
            </div>
        </flux:fieldset>

        <flux:fieldset>
            <flux:legend>{{ __('Academic Information') }}</flux:legend>
            <div class="grid gap-6">
                @if (!auth()->user()->institution_id)
                <flux:select wire:model="institution_id" :label="__('Institution')" required>
                    <flux:select.option value="null">{{ __('Select institution...') }}</flux:select.option>
                    @foreach (App\Models\Institution::query()->where('status', 'active')->orderBy('name')->get() as $inst)
                    <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                @endif

                <flux:select wire:model.live="department_id" :label="__('Department')" required :disabled="!$institution_id">
                    <flux:select.option value="null">{{ __('Select department...') }}</flux:select.option>
                    @if ($institution_id)
                        @foreach (\App\Models\Department::where('institution_id', $this->institution_id)->orderBy('name')->get() as $dept)
                        <flux:select.option :value="$dept->id">{{ $dept->name }}</flux:select.option>
                        @endforeach
                    @endif
                </flux:select>

                <flux:select wire:model="program_id" :label="__('Program')" required :disabled="!$department_id">
                    <flux:select.option value="null">{{ __('Select program...') }}</flux:select.option>
                    @if ($department_id)
                        @foreach (\App\Models\Program::query()->where('department_id', $this->department_id)->where('status', 'active')->orderBy('name')->get() as $program)
                        <flux:select.option :value="$program->id">
                            {{ $program->name }}
                        </flux:select.option>
                        @endforeach
                    @endif
                </flux:select>

                <flux:input wire:model="matric_number" :label="__('Matric Number')" readonly />

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:input wire:model="admission_year" :label="__('Admission Year')" type="number" required />
                    <flux:select wire:model="entry_level" :label="__('Entry Level')">
                        <flux:select.option value="100">100</flux:select.option>
                        <flux:select.option value="200">200</flux:select.option>
                        <flux:select.option value="300">300</flux:select.option>
                        <flux:select.option value="400">400</flux:select.option>
                        <flux:select.option value="500">500</flux:select.option>
                        <flux:select.option value="600">600</flux:select.option>
                    </flux:select>
                </div>

                <flux:select wire:model="status" :label="__('Enrollment Status')">
                    <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="suspended">{{ __('Suspended') }}</flux:select.option>
                    <flux:select.option value="withdrawn">{{ __('Withdrawn') }}</flux:select.option>
                    <flux:select.option value="graduated">{{ __('Graduated') }}</flux:select.option>
                </flux:select>
            </div>
        </flux:fieldset>

        <div class="flex items-center justify-end gap-3">
            <flux:button :href="route('cms.students.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Update Student') }}</flux:button>
        </div>
    </form>
</div>
</div>