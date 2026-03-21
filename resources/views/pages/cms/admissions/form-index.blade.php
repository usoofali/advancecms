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
        $this->authorize('manage_admission_status');

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
        $this->authorize('manage_application_forms');
        
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
        $this->authorize('manage_application_forms');
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
            'forms' => $query->with(['academicSession', 'institution', 'program'])
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

    @can('manage_admission_status')
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

    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-sm">
        <table class="w-full text-left border-collapse">
            <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700">
                <tr>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Form Name') }}</th>
                    @if(Auth::user()->hasRole('Super Admin'))
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Inst.') }}</th>
                    @endif
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Program') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Category') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Academic Session') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Amount') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Status') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100 text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($forms as $form)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/20 transition-colors" wire:key="{{ $form->id }}">
                        <td class="px-4 py-4">
                            <div class="flex flex-col">
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $form->name }}</span>
                                <span class="text-xs text-zinc-500 md:hidden">{{ $form->program?->name ?? __('All Programs') }}</span>
                            </div>
                        </td>
                        @if(Auth::user()->hasRole('Super Admin'))
                            <td class="px-4 py-4 text-sm">{{ $form->institution?->acronym }}</td>
                        @endif
                        <td class="px-4 py-4">
                            <flux:badge color="zinc" size="sm" variant="outline">
                                {{ $form->program?->name ?? __('All Programs') }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-4">
                            <flux:badge size="sm" color="{{ $form->category === 'fresh' ? 'blue' : 'orange' }}" variant="solid">
                                {{ $form->category === 'fresh' ? __('Fresh') : __('Retrainee') }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-4 text-sm text-zinc-500">{{ $form->academicSession->name }}</td>
                        <td class="px-4 py-4 text-sm text-zinc-900 dark:text-zinc-100 font-medium">₦{{ number_format($form->amount, 2) }}</td>
                        <td class="px-4 py-4">
                             <flux:button variant="ghost" size="sm" wire:click="toggleStatus({{ $form->id }})">
                                <flux:badge color="{{ $form->is_active ? 'success' : 'zinc' }}" class="cursor-pointer">
                                    {{ $form->is_active ? __('Active') : __('Inactive') }}
                                </flux:badge>
                            </flux:button>
                        </td>
                        <td class="px-4 py-4 text-right flex justify-end gap-2">
                            <flux:button variant="ghost" icon="pencil-square" size="sm" href="{{ route('cms.admissions.forms.edit', $form) }}" wire:navigate />
                            
                            <flux:modal.trigger name="delete-form-{{ $form->id }}">
                                <flux:button variant="ghost" icon="trash" size="sm" variant="danger" />
                            </flux:modal.trigger>

                            <flux:modal name="delete-form-{{ $form->id }}" class="md:w-[400px]">
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
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if($forms->hasPages())
        <div class="p-4 border-t border-zinc-100 dark:border-zinc-800">
            {{ $forms->links() }}
        </div>
        @endif
    </div>
</div>