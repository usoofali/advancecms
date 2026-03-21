<?php

use App\Models\AcademicSession;
use App\Models\Semester;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Academic Sessions')] class extends Component {
    public string $new_session_name = '';
    public string $start_date = '';
    public string $end_date = '';
    public bool $should_be_active = true;
    public int|string|null $deletingId = null;
    public int|string|null $togglingId = null;

    public function createSession(): void
    {
        if (auth()->user()->institution_id) {
            abort(403, 'Only Super Admins can manage global academic sessions.');
        }

        $validated = $this->validate([
            'new_session_name' => ['required', 'string', 'unique:academic_sessions,name', 'regex:/^\d{4}\/\d{4}$/'],
            'start_date'       => ['nullable', 'date'],
            'end_date'         => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        // Only one session can be active at a time
        if ($this->should_be_active ?? true) {
            AcademicSession::where('status', 'active')->update(['status' => 'closed']);
        }

        $session = AcademicSession::create([
            'name'       => $this->new_session_name,
            'start_date' => $this->start_date ?: null,
            'end_date'   => $this->end_date ?: null,
            'status'     => 'active',
        ]);

        // Auto-create semesters for the session
        Semester::create(['academic_session_id' => $session->id, 'name' => 'first']);
        Semester::create(['academic_session_id' => $session->id, 'name' => 'second']);

        $this->reset(['new_session_name', 'start_date', 'end_date']);
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Academic session and semesters created successfully.',
        ]);
    }

    public function confirmToggleStatus(): void
    {
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
                <div class="hidden sm:block overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-sm">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700">
                            <tr>
                                <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Session') }}</th>
                                <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Dates') }}</th>
                                <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Status') }}</th>
                                @if (!auth()->user()->institution_id)
                                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100 text-right">{{ __('Actions') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($sessions as $session)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/20 transition-colors" wire:key="{{ $session->id }}">
                                    <td class="px-4 py-4 font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $session->name }}
                                    </td>
                                    <td class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400">
                                        @if ($session->start_date)
                                            <div class="text-sm">
                                                {{ $session->start_date->format('M j, Y') }} — {{ $session->end_date?->format('M j, Y') ?? '?' }}
                                            </div>
                                        @else
                                            <span class="text-zinc-500 italic text-xs">{{ __('Not set') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-sm">
                                        <flux:badge :color="$session->status === 'active' ? 'green' : 'zinc'" size="sm">
                                            {{ ucfirst($session->status) }}
                                        </flux:badge>
                                    </td>
                                    @if (!auth()->user()->institution_id)
                                        <td class="px-4 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <flux:button size="sm" variant="ghost" :icon="$session->status === 'active' ? 'lock-closed' : 'lock-open'" 
                                                    x-on:click="$wire.togglingId = {{ $session->id }}; $flux.modal('toggle-session-status').show()" />
                                                <flux:button size="sm" variant="ghost" icon="trash" 
                                                    x-on:click="$wire.deletingId = {{ $session->id }}; $flux.modal('delete-session').show()" />
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                        {{ __('No academic sessions yet.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="sm:hidden space-y-4">
                    @forelse ($sessions as $session)
                        <flux:card class="p-4" wire:key="mobile-{{ $session->id }}">
                            <div class="flex items-center justify-between mb-3">
                                <flux:heading size="md">{{ $session->name }}</flux:heading>
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
                                    <flux:button size="sm" variant="ghost" :icon="$session->status === 'active' ? 'lock-closed' : 'lock-open'" 
                                        x-on:click="$wire.togglingId = {{ $session->id }}; $flux.modal('toggle-session-status').show()">
                                        {{ $session->status === 'active' ? __('Close') : __('Activate') }}
                                    </flux:button>
                                    <flux:button size="sm" variant="ghost" icon="trash" 
                                        x-on:click="$wire.deletingId = {{ $session->id }}; $flux.modal('delete-session').show()">
                                        {{ __('Delete') }}
                                    </flux:button>
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
    </div>
