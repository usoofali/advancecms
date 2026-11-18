<?php

use App\Models\ApplicationForm;
use App\Models\Institution;
use App\Models\Program;
use App\Models\AcademicSession;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Manage Application Forms')] class extends Component {
    use WithPagination;

    #[Url]
    public ?int $selectedInstitution = null;
    
    #[Url]
    public ?int $selectedProgram = null;

    public $admission_start_date;
    public $admission_end_date;
    public bool $is_admission_open = false;

    public function mount()
    {
        Gate::authorize('application_forms.view');

        if (!Auth::user()->hasRole('Super Admin')) {
            $this->selectedInstitution = Auth::user()->institution_id;
            $this->loadAdmissionSettings();
        }
    }

    public function updatedSelectedInstitution()
    {
        $this->loadAdmissionSettings();
        $this->resetPage();
    }

    public function loadAdmissionSettings()
    {
        if ($this->selectedInstitution) {
            $institution = Institution::find($this->selectedInstitution);
            if ($institution) {
                $this->admission_start_date = $institution->admission_start_date?->format('Y-m-d\TH:i');
                $this->admission_end_date = $institution->admission_end_date?->format('Y-m-d\TH:i');
                $this->is_admission_open = $institution->is_admission_open;
            }
        } else {
            $this->admission_start_date = null;
            $this->admission_end_date = null;
            $this->is_admission_open = false;
        }
    }

    public function saveAdmissionSettings()
    {
        $this->authorize('admission_status.update');

        if (!$this->selectedInstitution) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Please select an institution first.',
            ]);
            return;
        }

        $institution = Institution::findOrFail($this->selectedInstitution);
        $institution->update([
            'admission_start_date' => $this->admission_start_date ?: null,
            'admission_end_date' => $this->admission_end_date ?: null,
            'is_admission_open' => $this->is_admission_open,
        ]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Admission status and dates updated successfully.',
        ]);
    }

    public function delete(ApplicationForm $form)
    {
        Gate::authorize('application_forms.delete');
        
        // Prevent deletion if applicants exist
        if ($form->applicants()->exists()) {
             $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Cannot delete form with existing applications.',
            ]);
            return;
        }

        $form->delete();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Application form deleted successfully.',
        ]);
    }

    public function toggleStatus(ApplicationForm $form)
    {
        $this->authorize('application_forms.view');
        $form->update(['is_active' => !$form->is_active]);
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Form status updated.',
        ]);
    }

    public function with(): array
    {
        $query = ApplicationForm::query();

        if ($this->selectedInstitution) {
            $query->where('institution_id', $this->selectedInstitution);
        }

        if ($this->selectedProgram) {
            $query->where('program_id', $this->selectedProgram);
        }

        return [
            'forms' => $query->with(['academicSession', 'institution', 'program', 'invoice.items'])
                ->latest()
                ->paginate(10),
            'institutions' => Auth::user()->hasRole('Super Admin') ? Institution::all() : [],
            'programs' => $this->selectedInstitution ? Program::where('institution_id', $this->selectedInstitution)->get() : [],
        ];
    }
}; ?>

<div class="max-w-6xl space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Application Forms') }}</flux:heading>
            <flux:subheading>{{ __('Manage available admission forms for your institution.') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" href="{{ route('cms.admissions.forms.create') }}" wire:navigate>
            {{ __('Create New Form') }}
        </flux:button>
    </div>

    @can('admission_status.update')
    @if($this->selectedInstitution)
    <div class="p-6 bg-blue-50/50 dark:bg-blue-900/10 rounded-2xl border border-blue-100 dark:border-blue-900/30 space-y-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <flux:heading size="lg" class="text-blue-900 dark:text-blue-100">{{ __('Admission Control') }}</flux:heading>
                <flux:subheading class="text-blue-700/70 dark:text-blue-300/50">{{ __('Set the global admission window and status for ') }} <strong>{{ Institution::find($this->selectedInstitution)?->name }}</strong>.</flux:subheading>
            </div>
            <div class="flex items-center gap-3">
                <flux:switch wire:model="is_admission_open" :label="__('Admission Open')" />
                <flux:button variant="primary" size="sm" icon="check" wire:click="saveAdmissionSettings" wire:loading.attr="disabled">
                    {{ __('Save Settings') }}
                </flux:button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
            <flux:input wire:model="admission_start_date" type="datetime-local" :label="__('Opening Date & Time')" 
                icon="calendar-days" description="{{ __('Applications will only be accepted from this date.') }}" />
            
            <flux:input wire:model="admission_end_date" type="datetime-local" :label="__('Closing Date & Time')" 
                icon="clock" description="{{ __('Applicants and payments will be locked after this deadline.') }}" />
        </div>

        <div class="flex items-center gap-2 pt-2">
            @php
                $inst = Institution::find($this->selectedInstitution);
                $statusReason = $inst?->getAdmissionStatusReason();
                $isActive = $statusReason === 'active';
            @endphp
            <flux:badge color="{{ $isActive ? 'success' : 'danger' }}" variant="solid" size="sm" class="px-2">
                {{ $isActive ? __('Currently OPEN') : __('Currently CLOSED') }}
            </flux:badge>
            <flux:text size="sm" class="italic text-zinc-500">
                @if($isActive)
                    {{ __('Applicants can apply and make payments.') }}
                @else
                    @switch($statusReason)
                        @case('manual_off')
                            {{ __('Admission is manually toggled OFF.') }}
                            @break
                        @case('not_started')
                            {{ __('Opening date has not yet been reached.') }}
                            @break
                        @case('expired')
                            {{ __('Admission deadline has passed.') }}
                            @break
                        @default
                            {{ __('New applications and payments are strictly disabled.') }}
                    @endswitch
                @endif
            </flux:text>
        </div>
    </div>
    @endif
    @endcan

    <div class="flex flex-wrap gap-4 items-end">
        @if(Auth::user()->hasRole('Super Admin'))
        <flux:field class="w-full md:w-64">
            <flux:label>{{ __('Filter by Institution') }}</flux:label>
            <flux:select wire:model.live="selectedInstitution">
                <flux:select.option value="">{{ __('All Institutions') }}</flux:select.option>
                @foreach($institutions as $inst)
                <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>
        @endif

        @if($this->selectedInstitution || !Auth::user()->hasRole('Super Admin'))
        <flux:field class="w-full md:w-64">
            <flux:label>{{ __('Filter by Program') }}</flux:label>
            <flux:select wire:model.live="selectedProgram">
                <flux:select.option value="">{{ __('All Programs') }}</flux:select.option>
                @foreach($programs as $program)
                <flux:select.option :value="$program->id">{{ $program->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>
        @endif
    </div>

    <flux:card>
        <div class="relative overflow-x-auto">
            <flux:table :paginate="$forms">
                <flux:table.columns>
                    <flux:table.column>{{ __('Form Name') }}</flux:table.column>
                    @if(Auth::user()->hasRole('Super Admin'))
                        <flux:table.column>{{ __('Inst.') }}</flux:table.column>
                    @endif
                    <flux:table.column>{{ __('Program') }}</flux:table.column>
                    <flux:table.column>{{ __('Category') }}</flux:table.column>
                    <flux:table.column>{{ __('Academic Session') }}</flux:table.column>
                    <flux:table.column>{{ __('Linked Invoice') }}</flux:table.column>
                    <flux:table.column>{{ __('Amount') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($forms as $form)
                        <flux:table.row :key="$form->id">
                            <flux:table.cell>
                                <div class="flex flex-col">
                                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $form->name }}</span>
                                    <span class="text-xs text-zinc-500 md:hidden">{{ $form->program?->name ?? __('All Programs') }}</span>
                                </div>
                            </flux:table.cell>
                            @if(Auth::user()->hasRole('Super Admin'))
                                <flux:table.cell>{{ $form->institution?->acronym }}</flux:table.cell>
                            @endif
                            <flux:table.cell>
                                <flux:badge color="zinc" size="sm" variant="outline">
                                    {{ $form->program?->name ?? __('All Programs') }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" color="{{ $form->category === 'fresh' ? 'blue' : 'orange' }}" variant="solid">
                                    {{ $form->category === 'fresh' ? __('Fresh') : __('Retrainee') }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-500">{{ $form->academicSession->name }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($form->invoice)
                                    <div class="flex flex-col">
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $form->invoice->title }}</span>
                                        <span class="text-xs text-zinc-500">₦{{ number_format($form->invoice->total_amount, 2) }}</span>
                                    </div>
                                @else
                                    <flux:badge color="danger" size="sm" variant="outline">{{ __('Not linked') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-900 dark:text-zinc-100 font-medium">₦{{ number_format($form->amount, 2) }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:button variant="ghost" size="sm" wire:click="toggleStatus({{ $form->id }})">
                                    <flux:badge color="{{ $form->is_active ? 'success' : 'zinc' }}" class="cursor-pointer">
                                        {{ $form->is_active ? __('Active') : __('Inactive') }}
                                    </flux:badge>
                                </flux:button>
                            </flux:table.cell>
                            <flux:table.cell class="text-right">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" href="{{ route('cms.admissions.forms.edit', $form) }}" wire:navigate>
                                            {{ __('Edit') }}
                                        </flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" x-on:click="$flux.modal('delete-form-{{ $form->id }}').show()">
                                            {{ __('Delete') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>

                                <flux:modal name="delete-form-{{ $form->id }}" class="md:w-[400px] text-left">
                                    <div class="space-y-6">
                                        <div>
                                            <flux:heading size="lg">{{ __('Delete Application Form?') }}</flux:heading>
                                            <flux:subheading>{{ __('This action cannot be undone. Are you sure you want to delete ') }} <strong>{{ $form->name }}</strong>?</flux:subheading>
                                        </div>
                                        <div class="flex justify-end gap-2">
                                            <flux:modal.close>
                                                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                                            </flux:modal.close>
                                            <flux:button variant="danger" wire:click="delete({{ $form->id }})" wire:loading.attr="disabled">
                                                {{ __('Delete Form') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                </flux:modal>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell :colspan="Auth::user()->hasRole('Super Admin') ? 9 : 8" class="py-12 text-center text-zinc-500">
                                {{ __('No application forms found.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:card>
</div>