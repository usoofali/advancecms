<?php

use App\Models\AcademicSession;
use App\Models\Semester;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Academic Sessions')] class extends Component {
    public string $new_session_name = '';
    public string $start_date = '';
    public string $end_date = '';
    public bool $should_be_active = true;
    public bool $has_batches = false;
    public string $batches_count = '2';
    public int|string|null $deletingId = null;
    public int|string|null $togglingId = null;
    public int|string|null $editingBatchesId = null;
    public bool $edit_has_batches = false;
    public string $edit_batches_count = '2';

    public function mount(): void
    {
        Gate::authorize('academic_sessions.view');
    }

    public function createSession(): void
    {
        Gate::authorize('academic_sessions.create');

        if (auth()->user()->institution_id) {
            abort(403, 'Only Super Admins can manage global academic sessions.');
        }

        $validated = $this->validate([
            'new_session_name' => [
                'required', 
                'string', 
                'unique:academic_sessions,name', 
                'regex:/^\d{4}\/\d{4}$/',
                function (string $attribute, mixed $value, Closure $fail) {
                    $years = explode('/', $value);
                    if (count($years) === 2) {
                        $firstYear = (int) $years[0];
                        $secondYear = (int) $years[1];
                        if ($secondYear - $firstYear !== 1) {
                            $fail('The session years must be consecutive (e.g. 2025/2026).');
                        }
                    }
                },
            ],
            'start_date'       => ['nullable', 'date'],
            'end_date'         => ['nullable', 'date', 'after_or_equal:start_date'],
            'has_batches'      => ['boolean'],
            'batches_count'    => ['nullable', 'in:2,3,4'],
        ]);

        // Only one session can be active at a time
        if ($this->should_be_active ?? true) {
            AcademicSession::where('status', 'active')->update(['status' => 'closed']);
        }

        $batches = null;
        if ($this->has_batches) {
            $count = max(2, min(5, (int) $this->batches_count));
            $letters = ['A', 'B', 'C', 'D', 'E'];
            $batches = array_slice($letters, 0, $count);
        }

        $session = AcademicSession::create([
            'name'        => $this->new_session_name,
            'start_date'  => $this->start_date ?: null,
            'end_date'    => $this->end_date ?: null,
            'status'      => 'active',
            'has_batches' => $this->has_batches,
            'batches'     => $batches,
        ]);

        // Auto-create semesters for the session
        Semester::create(['academic_session_id' => $session->id, 'name' => 'first']);
        Semester::create(['academic_session_id' => $session->id, 'name' => 'second']);

        $this->reset(['new_session_name', 'start_date', 'end_date', 'has_batches', 'batches_count']);
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Academic session and semesters created successfully.',
        ]);
    }

    public function openEditBatches(int $sessionId): void
    {
        Gate::authorize('academic_sessions.edit');

        if (auth()->user()->institution_id) {
            abort(403, 'Unauthorized.');
        }

        $session = AcademicSession::find($sessionId);
        if ($session) {
            $this->editingBatchesId = $session->id;
            $this->edit_has_batches = (bool) $session->has_batches;
            $this->edit_batches_count = (string) (is_array($session->batches) ? count($session->batches) : 2);
        }
    }

    public function saveBatches(): void
    {
        Gate::authorize('academic_sessions.edit');

        if (auth()->user()->institution_id) {
            abort(403, 'Unauthorized.');
        }

        if (! $this->editingBatchesId) {
            return;
        }

        $session = AcademicSession::find($this->editingBatchesId);
        if ($session) {
            $batches = null;
            if ($this->edit_has_batches) {
                $count = max(2, min(5, (int) $this->edit_batches_count));
                $letters = ['A', 'B', 'C', 'D', 'E'];
                $batches = array_slice($letters, 0, $count);
            }

            $session->update([
                'has_batches' => $this->edit_has_batches,
                'batches' => $batches,
            ]);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Session batches updated successfully.',
            ]);
        }

        $this->editingBatchesId = null;
        $this->js('$flux.modal("edit-session-batches").close()');
    }

    public function confirmToggleStatus(): void
    {
        Gate::authorize('academic_sessions.edit');

        if (auth()->user()->institution_id) {
            abort(403, 'Unauthorized.');
        }

        if (!$this->togglingId) return;

        $session = AcademicSession::find($this->togglingId);
        if ($session) {
            $this->toggleStatus($session);
        }

        $this->togglingId = null;
        $this->dispatch('modal-close', name: 'toggle-session-status');
    }

    public function toggleStatus(AcademicSession $session): void
    {
        $newStatus = $session->status === 'active' ? 'closed' : 'active';

        if ($newStatus === 'active') {
            AcademicSession::where('id', '!=', $session->id)
                ->where('status', 'active')
                ->update(['status' => 'closed']);
        }

        $session->update([
            'status' => $newStatus,
        ]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Session status updated.',
        ]);
    }

    public function confirmDelete(): void
    {
        Gate::authorize('academic_sessions.delete');

        if (auth()->user()->institution_id) {
            abort(403, 'Unauthorized.');
        }

        if (!$this->deletingId) return;
        
        $session = AcademicSession::find($this->deletingId);
        if ($session) {
            if ($session->results()->exists()) {
                $this->addError('session_delete', __('Cannot delete session with existing results.'));
            } else {
                $session->delete();
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => 'Academic session deleted.',
                ]);
            }
        }
        
        $this->deletingId = null;
        $this->dispatch('modal-close', name: 'delete-session');
    }

    public function with(): array
    {
        return [
            'sessions' => AcademicSession::query()
                ->with('semesters')
                ->latest()
                ->get(),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-8">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ __('Academic Sessions') }}</flux:heading>
                <flux:subheading>{{ __('Manage academic years and semesters') }}</flux:subheading>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <!-- Create Session Form -->
            @if (!auth()->user()->institution_id)
            <div class="lg:col-span-1">
                <flux:card>
                    <form wire:submit="createSession" class="space-y-6">
                        <flux:heading size="lg">{{ __('New Session') }}</flux:heading>
                        
                        <flux:field>
                            <flux:label>{{ __('Session Name') }}</flux:label>
                            <flux:input wire:model="new_session_name" :placeholder="__('e.g. 2025/2026')" required />
                            <flux:error name="new_session_name" />
                            <flux:description>{{ __('Format: YYYY/YYYY (e.g. 2023/2024)') }}</flux:description>
                        </flux:field>
 
                        <div class="grid grid-cols-2 gap-4">
                            <flux:input wire:model="start_date" :label="__('Start Date')" type="date" />
                            <flux:input wire:model="end_date" :label="__('End Date')" type="date" />
                        </div>

                        <div class="space-y-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 bg-zinc-50 dark:bg-zinc-800/40">
                            <flux:checkbox wire:model.live="has_batches" :label="__('Enable Multiple Batches')" :description="__('Allow admissions in Batch A, Batch B, etc.')" />

                            @if ($has_batches)
                                <flux:select wire:model="batches_count" :label="__('Number of Batches')">
                                    <flux:select.option value="2">{{ __('2 Batches (Batch A & B)') }}</flux:select.option>
                                    <flux:select.option value="3">{{ __('3 Batches (Batch A, B & C)') }}</flux:select.option>
                                    <flux:select.option value="4">{{ __('4 Batches (Batch A, B, C & D)') }}</flux:select.option>
                                </flux:select>
                            @endif
                        </div>
 
                        <flux:button type="submit" variant="primary" class="w-full">
                            {{ __('Create Session & Semesters') }}
                        </flux:button>
                    </form>
                </flux:card>
            </div>
            @endif
 
            <!-- Sessions List -->
            <div class="{{ auth()->user()->institution_id ? 'lg:col-span-3' : 'lg:col-span-2' }}">
                <flux:error name="session_delete" class="mb-4" icon="exclamation-circle" />
                
                <!-- Desktop Table View -->
                <div class="hidden sm:block">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Session') }}</flux:table.column>
                            <flux:table.column>{{ __('Dates') }}</flux:table.column>
                            <flux:table.column>{{ __('Status') }}</flux:table.column>
                            @if (!auth()->user()->institution_id)
                                <flux:table.column align="right">{{ __('Actions') }}</flux:table.column>
                            @endif
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse ($sessions as $session)
                                <flux:table.row :wire:key="$session->id">
                                    <flux:table.cell class="font-medium text-zinc-900 dark:text-zinc-100">
                                        <div>{{ $session->name }}</div>
                                        @if ($session->has_batches)
                                            <flux:badge color="sky" size="sm" class="mt-1">
                                                {{ __('Batches: ') . implode(', ', $session->getBatchesList()) }}
                                            </flux:badge>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell class="text-sm text-zinc-600 dark:text-zinc-400">
                                        @if ($session->start_date)
                                            <div class="text-sm">
                                                {{ $session->start_date->format('M j, Y') }} — {{ $session->end_date?->format('M j, Y') ?? '?' }}
                                            </div>
                                        @else
                                            <span class="text-zinc-500 italic text-xs">{{ __('Not set') }}</span>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge :color="$session->status === 'active' ? 'green' : 'zinc'" size="sm">
                                            {{ ucfirst($session->status) }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    @if (!auth()->user()->institution_id)
                                        <flux:table.cell align="right">
                                            <div class="flex items-center justify-end gap-2">
                                                @can('academic_sessions.edit')
                                                <flux:button size="sm" variant="ghost" icon="squares-2x2" title="{{ __('Configure Batches') }}"
                                                    x-on:click="$wire.openEditBatches({{ $session->id }}); $flux.modal('edit-session-batches').show()" />
                                                <flux:button size="sm" variant="ghost" :icon="$session->status === 'active' ? 'lock-closed' : 'lock-open'" 
                                                    x-on:click="$wire.togglingId = {{ $session->id }}; $flux.modal('toggle-session-status').show()" />
                                                @endcan
                                                @can('academic_sessions.delete')
                                                <flux:button size="sm" variant="ghost" icon="trash" 
                                                    x-on:click="$wire.deletingId = {{ $session->id }}; $flux.modal('delete-session').show()" />
                                                @endcan
                                            </div>
                                        </flux:table.cell>
                                    @endif
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="4" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                        {{ __('No academic sessions yet.') }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>

                <!-- Mobile Card View -->
                <div class="sm:hidden space-y-4">
                    @forelse ($sessions as $session)
                        <flux:card class="p-4" wire:key="mobile-{{ $session->id }}">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <flux:heading size="md">{{ $session->name }}</flux:heading>
                                    @if ($session->has_batches)
                                        <flux:badge color="sky" size="sm" class="mt-1">
                                            {{ __('Batches: ') . implode(', ', $session->getBatchesList()) }}
                                        </flux:badge>
                                    @endif
                                </div>
                                <flux:badge :color="$session->status === 'active' ? 'green' : 'zinc'" size="sm">
                                    {{ ucfirst($session->status) }}
                                </flux:badge>
                            </div>
                            
                            <div class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">
                                @if ($session->start_date)
                                    <div class="flex items-center gap-2">
                                        <flux:icon name="calendar" size="xs" variant="micro" />
                                        <span>{{ $session->start_date->format('M j, Y') }} — {{ $session->end_date?->format('M j, Y') ?? '?' }}</span>
                                    </div>
                                @else
                                    <span class="text-zinc-500 italic text-xs">{{ __('Dates not set') }}</span>
                                @endif
                            </div>

                                @if (!auth()->user()->institution_id)
                                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-zinc-100 dark:border-zinc-700">
                                        @can('academic_sessions.edit')
                                        <flux:button size="sm" variant="ghost" icon="squares-2x2" 
                                            x-on:click="$wire.openEditBatches({{ $session->id }}); $flux.modal('edit-session-batches').show()">
                                            {{ __('Batches') }}
                                        </flux:button>
                                        <flux:button size="sm" variant="ghost" :icon="$session->status === 'active' ? 'lock-closed' : 'lock-open'" 
                                            x-on:click="$wire.togglingId = {{ $session->id }}; $flux.modal('toggle-session-status').show()">
                                            {{ $session->status === 'active' ? __('Close') : __('Activate') }}
                                        </flux:button>
                                        @endcan
                                        @can('academic_sessions.delete')
                                        <flux:button size="sm" variant="ghost" icon="trash" 
                                            x-on:click="$wire.deletingId = {{ $session->id }}; $flux.modal('delete-session').show()">
                                            {{ __('Delete') }}
                                        </flux:button>
                                        @endcan
                                    </div>
                                @endif
                        </flux:card>
                    @empty
                        <div class="p-8 text-center text-zinc-500 dark:text-zinc-400 border border-dashed rounded-xl">
                            {{ __('No academic sessions yet.') }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
        <flux:modal name="delete-session" variant="filled" class="min-w-[22rem]">
            <form wire:submit="confirmDelete" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Delete Academic Session?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This action cannot be undone. All associated semesters will be deleted. You cannot delete a session if results have been recorded.') }}
                    </flux:subheading>
                </div>

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger">{{ __('Delete') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="toggle-session-status" variant="filled" class="min-w-[22rem]">
            <form wire:submit="confirmToggleStatus" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Change Session Status?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This will change the operational status of the academic session. If you are activating a session, it will automatically close all other active sessions.') }}
                    </flux:subheading>
                </div>

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Confirm Change') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="edit-session-batches" variant="filled" class="min-w-[22rem]">
            <form wire:submit="saveBatches" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Configure Session Batches') }}</flux:heading>
                    <flux:subheading>
                        {{ __('Enable or disable student admission batches (e.g. Batch A, Batch B) for this academic session.') }}
                    </flux:subheading>
                </div>

                <div class="space-y-4">
                    <flux:checkbox wire:model.live="edit_has_batches" :label="__('Enable Multiple Batches')" :description="__('Allow admissions in Batch A, Batch B, etc.')" />

                    @if ($edit_has_batches)
                        <flux:select wire:model="edit_batches_count" :label="__('Number of Batches')">
                            <flux:select.option value="2">{{ __('2 Batches (Batch A & B)') }}</flux:select.option>
                            <flux:select.option value="3">{{ __('3 Batches (Batch A, B & C)') }}</flux:select.option>
                            <flux:select.option value="4">{{ __('4 Batches (Batch A, B, C & D)') }}</flux:select.option>
                        </flux:select>
                    @endif
                </div>

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Save Changes') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    </div>
