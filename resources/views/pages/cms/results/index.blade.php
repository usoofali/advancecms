<?php

use App\Models\Result;
use App\Models\AcademicSession;
use App\Models\Semester;
use App\Imports\ResultsImport;
use App\Services\GradingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('View Results')] class extends Component {
    use WithPagination, WithFileUploads;

    public string $search = '';
    public int|string $session_id = '';
    public int|string $semester_id = '';
    public int|string $course_id = '';
    public int|string $institution_id = '';

    public $importFile = null;
    /** @var array<int, string> */
    public array $importFailures = [];
    public int $importedCount = 0;
 
    public function mount(): void
    {
        if (auth()->user()->institution_id) {
            $this->institution_id = auth()->user()->institution_id;
        }
    }
 
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function import(): void
    {
        $this->validate(['importFile' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $this->importFailures = [];
        $this->importedCount = 0;

        $importer = new ResultsImport(
            auth()->user()->institution_id,
            app(GradingService::class)
        );
        $importer->import($this->importFile->getRealPath());

        $this->importedCount = $importer->imported;
        $this->importFailures = $importer->failures;
        $this->importFile = null;

        if ($this->importedCount > 0) {
            $this->dispatch('notify', [
                'type'    => 'success',
                'message' => "{$this->importedCount} result(s) imported successfully.",
            ]);
        }
    }

    public function with(): array
    {
        $baseQuery = Result::with(['student', 'course', 'academicSession', 'semester'])
            ->when($this->institution_id, fn($q) => $q->where('institution_id', $this->institution_id))
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->whereHas('student', function ($ssq) {
                        $ssq->where('first_name', 'like', "%{$this->search}%")
                           ->orWhere('last_name', 'like', "%{$this->search}%")
                           ->orWhere('matric_number', 'like', "%{$this->search}%");
                    })->orWhereHas('course', function ($cq) {
                        $cq->where('course_code', 'like', "%{$this->search}%");
                    });
                });
            })
            ->when($this->session_id, fn($q) => $q->where('academic_session_id', $this->session_id))
            ->when($this->semester_id, fn($q) => $q->where('semester_id', $this->semester_id))
            ->when($this->course_id, fn($q) => $q->where('course_id', $this->course_id));

        // Clone base query for aggregate statistics
        $statsQuery = clone $baseQuery;
        $allResults = $statsQuery->select('grade', 'remark')->get();
        
        $totalStudents = $allResults->count();
        $totalPass = $allResults->where('remark', 'pass')->count();
        $totalFail = $allResults->where('remark', 'fail')->count();
        
        $grades = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0];
        foreach ($allResults as $res) {
            if (isset($grades[$res->grade])) {
                $grades[$res->grade]++;
            }
        }
        
        $metrics = [
            'total' => $totalStudents,
            'pass' => $totalPass,
            'fail' => $totalFail,
            'pass_percentage' => $totalStudents > 0 ? round(($totalPass / $totalStudents) * 100, 1) : 0,
            'fail_percentage' => $totalStudents > 0 ? round(($totalFail / $totalStudents) * 100, 1) : 0,
            'grades' => [],
        ];

        foreach ($grades as $grade => $count) {
            $metrics['grades'][$grade] = [
                'count' => $count,
                'percentage' => $totalStudents > 0 ? round(($count / $totalStudents) * 100, 1) : 0,
            ];
        }

        return [
            'results' => $baseQuery->latest()->paginate(20),
            'metrics' => $metrics,
            'sessions' => AcademicSession::orderByDesc('name')->get(),
            'semesters' => $this->session_id ? Semester::where('academic_session_id', $this->session_id)->get() : [],
            'courses' => \App\Models\Course::query()
                ->when($this->institution_id, fn($q) => $q->where('institution_id', $this->institution_id))
                ->orderBy('course_code')
                ->get(),
            'institutions' => auth()->user()->institution_id 
                ? [] 
                : \App\Models\Institution::query()->where('status', 'active')->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Academic Results') }}</flux:heading>
            <flux:subheading>{{ __('Overview of all student results and grades') }}</flux:subheading>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:button variant="ghost" icon="printer" onclick="window.print()">
                {{ __('Print Results') }}
            </flux:button>
            <flux:button icon="arrow-down-tray" :href="route('cms.results.export')" wire:navigate>
                {{ __('Export CSV') }}
            </flux:button>
            <flux:button icon="arrow-up-tray" x-on:click="$flux.modal('import-results').show()">
                {{ __('Import CSV') }}
            </flux:button>
            <flux:button icon="plus" variant="primary" :href="route('cms.results.entry')" wire:navigate>
                {{ __('Enter Result') }}
            </flux:button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            :placeholder="__('Search student/course...')" />

        @if (!auth()->user()->institution_id)
        <flux:select wire:model.live="institution_id" :placeholder="__('All Institutions')">
            <flux:select.option value="null">{{ __('All Institutions') }}</flux:select.option>
            @foreach ($institutions as $inst)
            <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
            @endforeach
        </flux:select>
        @endif

        <flux:select wire:model.live="session_id">
            <flux:select.option value="null">{{ __('All Sessions') }}</flux:select.option>
            @foreach ($sessions as $session)
            <flux:select.option :value="$session->id">{{ $session->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="semester_id" :disabled="!$session_id">
            <flux:select.option value="null">{{ __('All Semesters') }}</flux:select.option>
            @foreach ($semesters as $semester)
            <flux:select.option :value="$semester->id">{{ ucfirst($semester->name) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="course_id">
            <flux:select.option value="null">{{ __('All Courses') }}</flux:select.option>
            @foreach ($courses as $course)
            <flux:select.option :value="$course->id">{{ $course->course_code }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if($metrics['total'] > 0)
    <flux:card class="mt-2 print:border-none print:shadow-none print:mb-6">
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-7 gap-y-6 gap-x-4">
            <div
                class="col-span-2 sm:col-span-3 md:col-span-1 text-center md:text-left flex flex-col justify-center border-b md:border-b-0 md:border-r border-zinc-100 dark:border-zinc-800 pb-4 md:pb-0 md:pr-4 print:border-none">
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Total Results') }}</div>
                <div class="mt-1">
                    <span class="text-3xl font-semibold text-zinc-900 dark:text-white">{{ $metrics['total'] }}</span>
                </div>
            </div>

            <div class="text-center md:border-r border-zinc-100 dark:border-zinc-800 md:pr-4 print:border-none">
                <div class="text-xs font-medium text-green-600 dark:text-green-400">{{ __('Pass') }}</div>
                <div class="mt-1 text-lg font-semibold text-zinc-900 dark:text-white">{{ $metrics['pass'] }}</div>
                <div class="text-xs text-zinc-500">{{ $metrics['pass_percentage'] }}%</div>
            </div>

            <div class="text-center md:border-r border-zinc-100 dark:border-zinc-800 md:pr-4 print:border-none">
                <div class="text-xs font-medium text-red-600 dark:text-red-400">{{ __('Fail') }}</div>
                <div class="mt-1 text-lg font-semibold text-zinc-900 dark:text-white">{{ $metrics['fail'] }}</div>
                <div class="text-xs text-zinc-500">{{ $metrics['fail_percentage'] }}%</div>
            </div>

            @foreach(['A', 'B', 'C', 'D'] as $grade)
            <div
                class="text-center {{ !$loop->last ? 'md:border-r border-zinc-100 dark:border-zinc-800 md:pr-4' : '' }} print:border-none">
                <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Grade') }} {{ $grade }}</div>
                <div class="mt-1 text-lg font-semibold text-zinc-900 dark:text-white">{{
                    $metrics['grades'][$grade]['count'] ?? 0 }}</div>
                <div class="text-xs text-zinc-500">{{ $metrics['grades'][$grade]['percentage'] ?? 0 }}%</div>
            </div>
            @endforeach
        </div>
    </flux:card>
    @endif

    <div
        class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-sm">
        <table class="w-full text-left border-collapse">
            <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700">
                <tr>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Student') }}
                    </th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Course') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Session/Sem') }}
                    </th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Score') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Grade') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Remark') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($results as $res)
                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/20 transition-colors" wire:key="{{ $res->id }}">
                    <td class="px-4 py-4">
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $res->student->full_name }}</div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $res->student->matric_number }}
                        </div>
                    </td>
                    <td class="px-4 py-4 truncate max-w-xs">
                        <div class="font-mono text-sm text-zinc-900 dark:text-zinc-100 uppercase">{{
                            $res->course->course_code }}</div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 truncate">{{ $res->course->title }}
                        </div>
                    </td>
                    <td class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400">
                        <div class="text-sm font-medium">{{ $res->academicSession->name }}</div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 capitalize">{{ $res->semester->name
                            }}</div>
                    </td>
                    <td class="px-4 py-4 font-bold text-sm text-zinc-900 dark:text-zinc-100">
                        {{ (float) $res->total_score }}
                    </td>
                    <td class="px-4 py-4 text-sm">
                        <flux:badge variant="outline" size="sm" color="zinc">{{ $res->grade }}</flux:badge>
                    </td>
                    <td class="px-4 py-4 text-sm">
                        <flux:badge :color="$res->remark === 'pass' ? 'green' : 'red'" size="sm">
                            {{ ucfirst($res->remark) }}
                        </flux:badge>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                        {{ __('No results found.') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $results->links() }}</div>

    {{-- Import Modal --}}
    <flux:modal name="import-results" variant="filled" class="min-w-[28rem]">
        <form wire:submit="import" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Import Results from CSV') }}</flux:heading>
                <flux:subheading>
                    {{ __('Upload a CSV file to bulk import student results. Grades and GPA will be automatically
                    computed.') }}
                    <a href="/templates/results-import-template.csv" class="text-accent underline" download>
                        {{ __('Download template') }}
                    </a>
                </flux:subheading>
            </div>

            <flux:input type="file" wire:model="importFile" accept=".csv,text/csv" :label="__('CSV File')" />
            <flux:error name="importFile" />

            @if (!empty($importFailures))
            <div
                class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-950/30 dark:border-red-900 p-4 space-y-1 max-h-48 overflow-y-auto">
                <p class="text-sm font-medium text-red-700 dark:text-red-400">{{ count($importFailures) }} {{ __('row(s)
                    failed:') }}</p>
                @foreach ($importFailures as $failure)
                <p class="text-xs text-red-600 dark:text-red-500">{{ $failure }}</p>
                @endforeach
            </div>
            @endif

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    {{ __('Import') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>

@push('styles')
<style>
    @media print {

        /* Hide layout elements */
        nav,
        aside,
        footer,
        header {
            display: none !important;
        }

        /* Hide interactive elements and filters */
        button,
        a[href],
        input,
        select,
        .grid-cols-1.md\:grid-cols-2 {
            display: none !important;
        }

        /* Adjust colors and borders for printing */
        body {
            background-color: white !important;
            color: black !important;
        }

        table {
            border: 1px solid #ccc !important;
            width: 100% !important;
        }

        th,
        td {
            color: black !important;
            border-bottom: 1px solid #ccc !important;
        }

        /* Remove card styles to save ink */
        .rounded-xl,
        .shadow-sm,
        .dark\:bg-zinc-800,
        .bg-white {
            border: none !important;
            box-shadow: none !important;
            background: transparent !important;
            border-radius: 0 !important;
        }

        .overflow-x-auto {
            overflow: visible !important;
        }
    }
</style>
@endpush