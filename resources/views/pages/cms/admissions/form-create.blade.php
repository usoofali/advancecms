<?php

use App\Models\ApplicationForm;
use App\Models\AcademicSession;
use App\Models\Institution;
use App\Models\Program;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Manage Application Form')] class extends Component {
    public ?ApplicationForm $form = null;

    public $institution_id;
    public $program_id;
    public string $name = '';
    public $amount = '';
    public $academic_session_id;
    public string $category = 'fresh';
    public bool $is_active = true;

    public function mount(?ApplicationForm $form = null)
    {
        if ($form && $form->exists) {
            $this->authorize('manage_application_forms');
            $this->form = $form;
            $this->institution_id = $form->institution_id;
            $this->program_id = $form->program_id;
            $this->name = $form->name;
            $this->amount = $form->amount;
            $this->academic_session_id = $form->academic_session_id;
            $this->category = $form->category ?? 'fresh';
            $this->is_active = (bool) $form->is_active;
        } else {
            $this->institution_id = Auth::user()->institution_id;
            $this->academic_session_id = AcademicSession::latest()->first()?->id;
        }
    }

    public function save()
    {
        $this->authorize('manage_application_forms');

        $this->validate([
            'institution_id' => 'required|exists:institutions,id',
            'program_id' => 'nullable|exists:programs,id',
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'category' => 'required|in:fresh,retrainee',
            'is_active' => 'boolean',
        ]);

        $data = [
            'institution_id' => $this->institution_id,
            'program_id' => $this->program_id,
            'name' => $this->name,
            'amount' => $this->amount,
            'academic_session_id' => $this->academic_session_id,
            'category' => $this->category,
            'is_active' => $this->is_active,
        ];

        if ($this->form) {
            $this->form->update($data);
        } else {
            ApplicationForm::create($data);
        }

        session()->flash('success', 'Application form '.($this->form ? 'updated' : 'created').' successfully.');

        return $this->redirectRoute('cms.admissions.forms.index', navigate: true);
    }

    public function with(): array
    {
        return [
            'institutions' => Auth::user()->hasRole('Super Admin') ? Institution::all() : [],
            'programs' => $this->institution_id ? Program::where('institution_id', $this->institution_id)->get() : [],
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <div class="mb-6">
        <flux:heading size="xl">{{ $form ? __('Edit Application Form') : __('Create Application Form') }}</flux:heading>
        <flux:subheading>{{ __('Define the details for student admission applications.') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:fieldset>
            <flux:legend>{{ __('Form Details') }}</flux:legend>
            <div class="grid gap-6">
            @if(auth()->user()->hasRole('Super Admin'))
            <flux:field>
                <flux:label>{{ __('Institution') }}</flux:label>
                <flux:select wire:model.live="institution_id" required>
                    <flux:select.option value="">{{ __('Select Institution') }}</flux:select.option>
                    @foreach($institutions as $inst)
                        <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="institution_id" />
            </flux:field>
            @endif

            <flux:field>
                <flux:label>{{ __('Program (Optional)') }}</flux:label>
                <flux:select wire:model="program_id">
                    <flux:select.option value="">{{ __('All Programs (General Form)') }}</flux:select.option>
                    @foreach($programs as $program)
                        <flux:select.option :value="$program->id">{{ $program->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:subheading>{{ __('Associate this form with a specific program, or leave empty for a general form.') }}</flux:subheading>
                <flux:error name="program_id" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Form Name') }}</flux:label>
                <flux:input wire:model="name" placeholder="{{ __('e.g. 2026/2027 Post-UTME Admission Form') }}" />
                <flux:error name="name" />
            </flux:field>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Form Fee (₦)') }}</flux:label>
                    <flux:input wire:model="amount" type="number" step="0.01" prefix="₦" />
                    <flux:error name="amount" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Academic Session') }}</flux:label>
                    <flux:select wire:model="academic_session_id" required>
                        <flux:select.option value="">{{ __('Select Session') }}</flux:select.option>
                        @foreach(AcademicSession::all() as $session)
                            <flux:select.option :value="$session->id">{{ $session->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="academic_session_id" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Category') }}</flux:label>
                <flux:radio.group wire:model="category" class="mt-2 flex flex-col md:flex-row gap-4">
                    <flux:radio value="fresh" label="{{ __('Fresh (100 Level)') }}" />
                    <flux:radio value="retrainee" label="{{ __('Retrainee (200 Level)') }}" />
                </flux:radio.group>
                <flux:error name="category" />
            </flux:field>

            <flux:field>
                <flux:checkbox wire:model="is_active" :label="__('This form is currently open for applications')" />
                <flux:error name="is_active" />
            </flux:field>

            </div>
        </flux:fieldset>

        <div class="flex items-center justify-end gap-3">
            <flux:button :href="route('cms.admissions.forms.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ $form ? __('Update Form') : __('Create Form') }}</flux:button>
        </div>
    </form>
</div>
