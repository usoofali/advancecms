<?php

use App\Models\AcademicSession;
use App\Models\Semester;
use App\Exports\ResultsExport;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Export Results')] class extends Component {
    public int|string $session_id = '';
    public int|string $semester_id = '';
    public int|string $institution_id = '';

    public function mount(): void
    {
        if (auth()->user()->institution_id) {
            $this->institution_id = auth()->user()->institution_id;
        }
    }

    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return (new ResultsExport(
            $this->institution_id ?: null,
            $this->session_id ?: null,
            $this->semester_id ?: null
        ))->download();
    }

    public function with(): array
    {
        return [
            'sessions' => AcademicSession::orderByDesc('name')->get(),
            'semesters' => Semester::orderBy('name')->get(),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Export Academic Results') }}</flux:heading>
        <flux:subheading>{{ __('Download student results as a CSV file. You can filter the export by academic session
            and semester.') }}</flux:subheading>
    </div>

    <form wire:submit="export" class="space-y-8">
        <flux:fieldset>
            <flux:legend>{{ __('Export Filters') }}</flux:legend>
            <div class="grid gap-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:select wire:model="session_id" :label="__('Academic Session')">
                        <flux:select.option value="null">{{ __('All Sessions') }}</flux:select.option>
                        @foreach ($sessions as $sess)
                        <flux:select.option :value="$sess->id">{{ $sess->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="semester_id" :label="__('Semester')">
                        <flux:select.option value="null">{{ __('All Semesters') }}</flux:select.option>
                        @foreach ($semesters as $sem)
                        <flux:select.option :value="$sem->id">{{ $sem->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <div class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Leave filters empty to export all results across all sessions and semesters.') }}
                </div>
            </div>
        </flux:fieldset>

        <div class="flex items-center justify-end gap-3">
            <flux:button :href="route('cms.results.index')" wire:navigate>{{ __('Back to Results') }}</flux:button>
            <flux:button type="submit" variant="primary" icon="arrow-down-tray">{{ __('Download CSV') }}</flux:button>
        </div>
    </form>
</div>