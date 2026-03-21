<?php

use App\Models\Applicant;
use App\Models\Institution;
use App\Models\Program;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Manage Applications')] #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $institutionFilter = '';
    public string $programFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingInstitutionFilter(): void
    {
        $this->resetPage();
        $this->programFilter = '';
    }

    public function mount(): void
    {
        $user = auth()->user();

        // If user is an Institutional Admin, lock the filter to their institution
        if ($user->hasRole('Institutional Admin')) {
            $this->institutionFilter = (string) $user->institution_id;
        }
    }

    public function with(): array
    {
        $user = auth()->user();

        $query = Applicant::with(['institution', 'program', 'applicationForm', 'studentInvoices']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('application_number', 'like', '%' . $this->search . '%')
                  ->orWhere('full_name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->statusFilter) {
            $query->where('admission_status', $this->statusFilter);
        }

        if ($this->institutionFilter) {
            $query->where('institution_id', $this->institutionFilter);
        }

        if ($this->programFilter) {
            $query->where('program_id', $this->programFilter);
        }

        $programs = [];
        if ($this->institutionFilter) {
            $programs = Program::where('institution_id', $this->institutionFilter)->get();
        } elseif ($user->hasRole('Institutional Admin')) {
             $programs = Program::where('institution_id', $user->institution_id)->get();
        }

        return [
            'applicants' => $query->latest()->paginate(15),
            'institutions' => Institution::all(),
            'programs' => $programs,
            'isSuperAdmin' => $user->hasRole('Super Admin'),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <flux:heading size="xl">{{ __('Manage Applications') }}</flux:heading>
            <flux:subheading>{{ __('Review and process admission applications.') }}</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <!-- Add any global actions here like export -->
        </div>
    </div>

    <!-- Filters -->
    <flux:card>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search names, emails, app #...') }}" />
            
            @if($isSuperAdmin)
            <flux:select wire:model.live="institutionFilter" placeholder="{{ __('All Institutions') }}">
                <flux:select.option value="">{{ __('All Institutions') }}</flux:select.option>
                @foreach($institutions as $inst)
                    <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                @endforeach
            </flux:select>
            @endif

            <flux:select wire:model.live="programFilter" placeholder="{{ __('All Programs') }}" :disabled="empty($programs) && $isSuperAdmin">
                <flux:select.option value="">{{ __('All Programs') }}</flux:select.option>
                @foreach($programs as $prog)
                    <flux:select.option :value="$prog->id">{{ $prog->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="statusFilter" placeholder="{{ __('All Statuses') }}">
                <flux:select.option value="">{{ __('All Statuses') }}</flux:select.option>
                <flux:select.option value="pending">{{ __('Pending (No Credentials)') }}</flux:select.option>
                <flux:select.option value="under_review">{{ __('Under Review (Credentials Submitted)') }}</flux:select.option>
                <flux:select.option value="admitted">{{ __('Admitted') }}</flux:select.option>
                <flux:select.option value="rejected">{{ __('Rejected') }}</flux:select.option>
            </flux:select>
        </div>
    </flux:card>

    <!-- Data Table -->
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-sm">
        <table class="w-full text-left border-collapse">
            <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700">
                <tr>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Applicant') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('App. No.') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Program') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Status') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Fees') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Date') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100 text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($applicants as $applicant)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/20 transition-colors" wire:key="{{ $applicant->id }}">
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-3">
                                <div>
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $applicant->full_name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $applicant->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <span class="font-mono text-sm">{{ $applicant->application_number }}</span>
                        </td>
                        <td class="px-4 py-4">
                            <div class="text-sm">
                                <div>{{ $applicant->program->name }}</div>
                                @if($isSuperAdmin)
                                    <div class="text-xs text-zinc-500">{{ $applicant->institution->acronym }}</div>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <flux:badge color="{{ match($applicant->admission_status) {
                                'admitted' => 'success',
                                'under_review' => 'blue',
                                'rejected' => 'danger',
                                default => 'zinc',
                            } }}" size="sm">
                                {{ ucfirst(str_replace('_', ' ', $applicant->admission_status)) }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-4">
                            @if($applicant->admission_status === 'admitted')
                            @php
                                $inv = $applicant->studentInvoices->first();
                                $pct = ($inv && $inv->total_amount > 0) ? round(($inv->amount_paid / $inv->total_amount) * 100) : 0;
                            @endphp
                            @if($applicant->enrolled_at)
                                <flux:badge color="green" size="sm" icon="check-circle">{{ __('Enrolled') }}</flux:badge>
                            @elseif($pct >= 50)
                                <div class="flex items-center gap-1.5">
                                    <flux:badge color="lime" size="sm" icon="check">{{ __('Ready') }}</flux:badge>
                                    <span class="text-xs text-zinc-500">{{ $pct }}%</span>
                                </div>
                            @elseif($inv)
                                <div class="w-24">
                                    <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-1.5 mb-0.5">
                                        <div class="bg-amber-400 h-1.5 rounded-full" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="text-xs text-zinc-500">{{ $pct }}% paid</span>
                                </div>
                            @else
                                <span class="text-xs text-zinc-400">{{ __('No invoice') }}</span>
                            @endif
                            @else
                                <span class="text-zinc-300 dark:text-zinc-600">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-sm text-zinc-500">
                            {{ $applicant->created_at->format('M j, Y') }}
                        </td>
                        <td class="px-4 py-4 text-right">
                            <flux:button size="sm" variant="subtle" href="{{ route('cms.admissions.show', $applicant) }}" icon="eye" wire:navigate>{{ __('Review') }}</flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                            {{ __('No applications found matching your criteria.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $applicants->links() }}
    </div>
</div>
