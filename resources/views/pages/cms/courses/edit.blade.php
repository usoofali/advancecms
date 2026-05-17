<?php

use App\Models\Course;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Edit Course')] class extends Component
{
    public Course $course;

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

    // Scoped Role Assignment properties
    public int|string|null $assign_user_id = null;
    public int|string|null $assign_role_id = null;

    public function mount(Course $course): void
    {
        Gate::authorize('courses.edit');

        $user_institution_id = auth()->user()->institution_id;
        if ($user_institution_id && $course->institution_id !== $user_institution_id) {
            abort(403, 'Unauthorized. This course record belongs to another institution.');
        }

        $this->course = $course;
        $this->institution_id = $course->institution_id ?? ($user_institution_id ?? '');
        $this->program_id = $course->program_id;
        $this->department_id = $course->department_id;
        $this->course_code = $course->course_code;
        $this->title = $course->title;
        $this->credit_unit = $course->credit_unit;
        $this->course_type = $course->course_type;
        $this->level = $course->level;
        $this->semester = $course->semester;
        $this->status = $course->status;
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
        Gate::authorize('courses.edit');

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

        $this->course->update($validated);

        session()->flash('success', 'Course updated successfully.');

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
            'allUsers' => \App\Models\User::where('institution_id', $this->course->institution_id)
                ->whereHas('staff')
                ->with('roles')
                ->orderBy('name')
                ->get(),
            'allRoles' => Role::where('role_name', '!=', 'Super Admin')->orderBy('role_name')->get(),
            'assignedUsers' => DB::table('model_user_roles')
                ->join('users', 'model_user_roles.user_id', '=', 'users.id')
                ->join('roles', 'model_user_roles.role_id', '=', 'roles.role_id')
                ->where('model_type', $this->course->getMorphClass())
                ->where('model_id', $this->course->id)
                ->select('users.id as user_id', 'users.name as user_name', 'roles.role_id', 'roles.role_name')
                ->get(),
        ];
    }

    public function assignRole(): void
    {
        Gate::authorize('courses.assign_roles');

        $this->validate([
            'assign_user_id' => ['required', 'exists:users,id'],
            'assign_role_id' => ['required', 'exists:roles,role_id'],
        ]);

        $user = \App\Models\User::findOrFail($this->assign_user_id);
        $role = Role::where('role_id', $this->assign_role_id)->firstOrFail();

        $user->assignScopedRole($role->role_name, $this->course);

        $this->assign_user_id = null;
        $this->assign_role_id = null;

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Role assigned successfully.']);
    }

    public function removeAssignedRole(int $userId, int $roleId): void
    {
        Gate::authorize('courses.assign_roles');

        $user = \App\Models\User::findOrFail($userId);
        $role = Role::findOrFail($roleId);

        $user->removeScopedRole($role->role_name, $this->course);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Role assignment removed.']);
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Edit Course') }}</flux:heading>
        <flux:subheading>{{ $course->course_code }}: {{ $course->title }}</flux:subheading>
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
                        <flux:input wire:model="course_code" :label="__('Course Code')" required />
                    </div>
                    <div class="sm:col-span-2">
                        <flux:input wire:model="title" :label="__('Course Title')" required />
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
            <flux:button type="submit" variant="primary">{{ __('Update Course') }}</flux:button>
        </div>
    </form>

    @can('courses.assign_roles')
    <div class="mt-8 border-t border-zinc-200 dark:border-zinc-800 pt-8">
        <flux:heading size="lg" class="mb-4">{{ __('Assigned Scoped Roles') }}</flux:heading>
        <flux:subheading class="mb-6">{{ __('Dynamically assign users to specific roles strictly for this course.') }}</flux:subheading>

        <div class="bg-zinc-50 dark:bg-zinc-900 rounded-xl p-6 border border-zinc-200 dark:border-zinc-800 mb-6">
            <form wire:submit="assignRole" class="flex flex-col sm:flex-row items-end gap-4">
                <div class="flex-1 w-full">
                    <flux:select wire:model="assign_user_id" :label="__('User')" searchable required>
                        <flux:select.option value="">{{ __('Search or select user...') }}</flux:select.option>
                        @foreach ($allUsers as $u)
                            <flux:select.option :value="$u->id">
                                {{ $u->name }} @if($u->roles->isNotEmpty()) ({{ $u->roles->pluck('role_name')->implode(', ') }}) @endif
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <div class="flex-1 w-full">
                    <flux:select wire:model="assign_role_id" :label="__('Role')" searchable required>
                        <flux:select.option value="">{{ __('Search or select role...') }}</flux:select.option>
                        @foreach ($allRoles as $r)
                            <flux:select.option :value="$r->role_id">{{ $r->role_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:button type="submit" variant="primary">{{ __('Assign') }}</flux:button>
            </form>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('User') }}</flux:table.column>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($assignedUsers as $assignment)
                    <flux:table.row>
                        <flux:table.cell class="font-medium">{{ $assignment->user_name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge color="indigo" size="sm">{{ $assignment->role_name }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:button size="sm" variant="danger" icon="trash" wire:click="removeAssignedRole({{ $assignment->user_id }}, {{ $assignment->role_id }})" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="text-center text-zinc-500">
                            {{ __('No users are explicitly assigned roles for this course.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    @endcan
</div>
</div>