<?php

use App\Models\Student;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('Add Student')] class extends Component {
    use WithFileUploads;

    public int|string $institution_id = '';

    public int|string $department_id = '';

    public int|string $program_id = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $gender = '';

    public string $date_of_birth = '';

    public string $email = '';

    public string $phone = '';

    public string $next_of_kin_name = '';

    public string $next_of_kin_relationship = '';

    public string $next_of_kin_phone = '';

    public string $next_of_kin_address = '';

    public int|string $session_id = '';

    public int $entry_level = 100;

    public string $status = 'active';

    public $photo;

    public string $successStudentName = '';

    public string $successMatricNumber = '';

    public function updatedInstitutionId(): void
    {
        $this->department_id = '';
        $this->program_id = '';
    }

    public function updatedDepartmentId(): void
    {
        $this->program_id = '';
    }

    public function mount(): void
    {
        Gate::authorize('students.create');

        if (auth()->user()->institution_id) {
            $this->institution_id = auth()->user()->institution_id;
        }
    }

    public function save(): void
    {
        Gate::authorize('students.create');

        $this->first_name = strtoupper(str_replace("'", "", $this->first_name));
        $this->last_name = strtoupper(str_replace("'", "", $this->last_name));

        $validated = $this->validate([
            'institution_id' => ['required', 'exists:institutions,id'],
            'program_id' => ['required', 'exists:programs,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'date_of_birth' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'next_of_kin_name' => ['nullable', 'string', 'max:255'],
            'next_of_kin_relationship' => ['nullable', 'string', 'max:255'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:255'],
            'next_of_kin_address' => ['nullable', 'string', 'max:500'],
            'session_id' => ['required', 'exists:academic_sessions,id'],
            'entry_level' => ['required', 'integer', 'multiple_of:100', 'min:100', 'max:600'],
            'status' => ['required', 'in:active,suspended,withdrawn,graduated,deceased'],
            'photo' => ['nullable', 'image', 'max:1024'], // 1MB Max
        ]);

        $session = \App\Models\AcademicSession::find($this->session_id);
        $validated['admission_year'] = (int) explode('/', $session->name)[0];
        unset($validated['session_id']);

        $validated['date_of_birth'] = $validated['date_of_birth'] ?: null;
        $validated['email'] = $validated['email'] ?: null;
        $validated['phone'] = $validated['phone'] ?: null;

        if ($this->photo) {
            $validated['photo_path'] = $this->photo->store('students/photos', 'public');
        }

        $student = Student::create($validated);

        $this->successStudentName = $student->first_name . ' ' . $student->last_name;
        $this->successMatricNumber = $student->matric_number;

        $this->js('$flux.modal("success-modal").show()');
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Add Student') }}</flux:heading>
        <flux:subheading>{{ __('Register a new student record') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-8" enctype="multipart/form-data">

        <flux:fieldset>
            <flux:legend>{{ __('Personal Information') }}</flux:legend>
            <div class="grid gap-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:input wire:model="first_name" :label="__('First Name')" required />
                    <flux:input wire:model="last_name" :label="__('Last Name')" required />
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:select wire:model="gender" :label="__('Gender')" required>
                        <flux:select.option value="null">{{ __('Select...') }}</flux:select.option>
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
                    <flux:select wire:model.live="institution_id" :label="__('Institution')" required>
                        <flux:select.option value="null">{{ __('Select institution...') }}</flux:select.option>
                        @foreach (App\Models\Institution::query()->where('status', 'active')->orderBy('name')->get() as $inst)
                            <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <flux:select wire:model.live="department_id" :label="__('Department')" required
                    :disabled="!$institution_id">
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

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:select wire:model="session_id" :label="__('Admission Session')" required>
                        <flux:select.option value="">{{ __('Select session...') }}</flux:select.option>
                        @foreach (\App\Models\AcademicSession::orderByDesc('name')->get() as $session)
                            <flux:select.option :value="$session->id">{{ $session->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="entry_level" :label="__('Entry Level')">
                        <flux:select.option value="100">100</flux:select.option>
                        <flux:select.option value="200">200</flux:select.option>
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

        <flux:fieldset>
            <flux:legend>{{ __('Next of Kin') }}</flux:legend>
            <div class="grid gap-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:input wire:model="next_of_kin_name" :label="__('Name')" />
                    <flux:input wire:model="next_of_kin_relationship" :label="__('Relationship')" />
                </div>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:input wire:model="next_of_kin_phone" :label="__('Phone Number')" />
                    <flux:input wire:model="next_of_kin_address" :label="__('Address')" />
                </div>
            </div>
        </flux:fieldset>

        <flux:fieldset>
            <flux:legend>{{ __('Profile Photo') }}</flux:legend>
            <div class="flex flex-col sm:flex-row items-center gap-6">
                <div class="relative group">
                    <div
                        class="w-32 h-32 rounded-2xl bg-zinc-100 dark:bg-zinc-800 border-2 border-dashed border-zinc-200 dark:border-zinc-700 flex items-center justify-center overflow-hidden">
                        @if ($photo)
                            <img src="{{ $photo->temporaryUrl() }}" class="w-full h-full object-cover">
                        @else
                            <flux:icon icon="camera" class="w-8 h-8 text-zinc-400" />
                        @endif
                    </div>
                </div>
                <div class="flex-1 space-y-2">
                    <flux:input type="file" wire:model="photo" accept="image/*" :label="__('Choose Photo')" />
                    <flux:description>{{ __('Max 1MB. JPEG, PNG, or WEBP.') }}</flux:description>
                    <flux:error name="photo" />
                </div>
            </div>
        </flux:fieldset>

        <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3">
            <flux:button :href="route('cms.students.index')" wire:navigate class="w-full sm:w-auto">{{ __('Cancel') }}
            </flux:button>
            <flux:button type="submit" variant="primary" class="w-full sm:w-auto">{{ __('Register Student') }}
            </flux:button>
        </div>
    </form>

    <flux:modal name="success-modal" class="md:w-96" :dismissable="false">
        <div class="space-y-6 text-center">
            <div class="flex justify-center">
                <div
                    class="w-12 h-12 rounded-full bg-green-50 dark:bg-green-950/30 flex items-center justify-center border border-green-200 dark:border-green-800">
                    <flux:icon icon="check" class="w-6 h-6 text-green-600 dark:text-green-400" />
                </div>
            </div>
            <div>
                <flux:heading size="lg">{{ __('Student Registered Successfully!') }}</flux:heading>
                <flux:subheading class="mt-2 text-sm text-zinc-500 dark:text-zinc-400 break-words">
                    {{ __('The student record has been successfully created with the following details:') }}
                </flux:subheading>
            </div>

            <div
                class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/50 p-4 space-y-2 text-left font-sans overflow-hidden">
                <div class="break-words">
                    <span
                        class="text-xs text-zinc-500 font-medium uppercase tracking-wider block">{{ __('Full Name') }}</span>
                    <span
                        class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 block mt-0.5">{{ $successStudentName }}</span>
                </div>
                <div class="border-t border-zinc-200 dark:border-zinc-700/60 pt-2 mt-2 break-words">
                    <span
                        class="text-xs text-zinc-500 font-medium uppercase tracking-wider block">{{ __('Matriculation Number') }}</span>
                    <span
                        class="text-sm font-bold font-mono text-blue-600 dark:text-blue-400 block mt-0.5">{{ $successMatricNumber }}</span>
                </div>
            </div>

            <div class="flex justify-center">
                <flux:button :href="route('cms.students.index')" wire:navigate variant="primary"
                    class="w-full sm:w-auto min-w-[8rem]">
                    {{ __('OK') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>