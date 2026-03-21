<?php

use App\Models\Student;
use App\Models\AcademicSession;
use App\Models\Semester;
use App\Services\GradingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('My Academic Results')] class extends Component {
    public ?Student $student = null;
    public int|string $filterSession = '';
    public int|string $filterSemester = '';

    public function mount(): void
    {
        $user = auth()->user();

        // For admin/staff viewing a student portal — they can pass a ?student= param
        if (request()->has('student') && $user->can('view_dept_results')) {
            $this->student = Student::find(request('student'));
        }

        // For student self-service: auto-load by matching email
        if (! $this->student) {
            $this->student = Student::where('email', $user->email)->first();
        }
    }

    public function updatedFilterSession(): void
    {
        $this->filterSemester = '';
    }

    public function generateResultInvoice(App\Services\StudentInvoiceService $service, $sessionId, $semesterId): void
    {
        if (!$this->student) {
            return;
        }

        $template = App\Models\Invoice::where('category', App\Models\Invoice::CATEGORY_RESULT)
            ->where('academic_session_id', $sessionId)
            ->where(function ($q) use ($semesterId) {
                $q->whereNull('semester_id')->orWhere('semester_id', $semesterId);
            })
            ->where('status', 'published')
            ->get()
            ->first(fn($i) => $service->isEligible($this->student, $i));

        if ($template) {
            $service->materializeInvoice($this->student, $template);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Result Fee invoice generated. Please proceed to payment.']);
        }
    }

    public function with(App\Services\PaymentAccessService $accessService, App\Services\StudentInvoiceService $invoiceService): array
    {
        if (! $this->student) {
            return [
                'sessions' => [],
                'semesters' => [],
                'results' => collect(),
                'cgpa' => 0.0,
                'sessionGpa' => [],
                'totalUnits' => 0,
                'accessMap' => [],
            ];
        }

        $resultsQuery = $this->student
            ->results()
            ->with(['course', 'academicSession', 'semester'])
            ->when($this->filterSession && $this->filterSession !== 'null', fn($q) => $q->where('academic_session_id', $this->filterSession))
            ->when($this->filterSemester && $this->filterSemester !== 'null', fn($q) => $q->where('semester_id', $this->filterSemester))
            ->orderBy('academic_session_id', 'desc')
            ->orderBy('semester_id', 'desc');

        $results = $resultsQuery->get();
        $cgpa = app(GradingService::class)->computeCgpa($this->student);

        // Access Map for grouped results
        $accessMap = [];
        $missingInvoicesMap = [];
        
        $groups = $results->groupBy(fn($r) => $r->academic_session_id . '-' . $r->semester_id);
        
        foreach ($groups as $key => $groupResults) {
            $first = $groupResults->first();
            $canAccess = $accessService->canAccessResults($this->student, $first->academicSession, $first->semester);
            $accessMap[$key] = $canAccess;
            
            if (!$canAccess) {
                $template = App\Models\Invoice::where('category', App\Models\Invoice::CATEGORY_RESULT)
                    ->where('academic_session_id', $first->academic_session_id)
                    ->where(function ($q) use ($first) {
                        $q->whereNull('semester_id')->orWhere('semester_id', $first->semester_id);
                    })
                    ->where('status', 'published')
                    ->get()
                    ->first(fn($i) => $invoiceService->isEligible($this->student, $i));

                if ($template) {
                    $accessMap[$key . '-invoice'] = $invoiceService->materializeInvoice($this->student, $template);
                }
            }
        }

        // Identify best result for each course to mark Others as superseded
        $bestResultIds = $this->student->results()
            ->get()
            ->groupBy('course_id')
            ->map(fn($group) => $group->sortByDesc('grade_point')->first()->id)
            ->values()
            ->toArray();

        // Compute per-session GPA summary
        $sessionGpa = $this->student->results()
            ->with(['course', 'academicSession', 'semester'])
            ->get()
            ->groupBy('academic_session_id')
            ->map(function ($sessionResults) {
                $totalPoints = $sessionResults->sum(fn($r) => $r->grade_point * $r->course->credit_unit);
                $totalUnits = $sessionResults->sum(fn($r) => $r->course->credit_unit);
                return [
                    'session' => $sessionResults->first()->academicSession->name,
                    'gpa' => $totalUnits > 0 ? round($totalPoints / $totalUnits, 2) : 0,
                    'units' => $totalUnits,
                ];
            });

        return [
            'sessions' => AcademicSession::orderBy('name', 'desc')->get(),
            'semesters' => $this->filterSession
                ? Semester::where('academic_session_id', $this->filterSession)->get()
                : [],
            'results' => $results,
            'bestResultIds' => $bestResultIds,
            'cgpa' => $cgpa,
            'sessionGpa' => $sessionGpa,
            'totalUnits' => $this->student->results()->with('course')->get()
                ->groupBy('course_id')
                ->map(fn($g) => $g->sortByDesc('grade_point')->first()->course->credit_unit)
                ->sum(),
            'accessMap' => $accessMap,
        ];
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-8">

    {{-- Header --}}
    <div class="flex items-center justify-between print:hidden">
        <div>
            <flux:heading size="xl">{{ __('My Academic Results') }}</flux:heading>
            <flux:subheading>{{ __('Your academic performance and transcript summary') }}</flux:subheading>
        </div>
        <div>
            <flux:button variant="primary" icon="printer" onclick="window.print()">
                {{ __('Print Results') }}
            </flux:button>
        </div>
    </div>

    @if (!$this->student)
    <flux:card>
        <div class="text-center p-10 text-zinc-500">
            <flux:icon.exclamation-triangle class="h-12 w-12 mx-auto mb-4 text-yellow-400" />
            <h3 class="text-lg font-semibold text-zinc-800 dark:text-white">{{ __('No Student Profile Found') }}</h3>
            <p class="mt-1 text-sm">{{ __('Your account is not linked to a student record. Please contact the academic
                office.') }}</p>
        </div>
    </flux:card>
    @else
    {{-- Filters --}}
    <div class="flex flex-wrap gap-4 print:hidden">
        <div class="flex-1 min-w-[180px]">
            <flux:select wire:model.live="filterSession" :label="__('Session')" placeholder="{{ __('All Sessions') }}">
                <flux:select.option value="null">{{ __('All Sessions') }}</flux:select.option>
                @foreach ($sessions as $session)
                <flux:select.option :value="$session->id">{{ $session->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div class="flex-1 min-w-[180px]">
            <flux:select wire:model.live="filterSemester" :label="__('Semester')" :disabled="!$filterSession"
                placeholder="{{ __('All Semesters') }}">
                <flux:select.option value="null">{{ __('All Semesters') }}</flux:select.option>
                @foreach ($semesters as $semester)
                <flux:select.option :value="$semester->id">{{ ucfirst($semester->name) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    @if ($filterSession && $filterSemester && $filterSession !== 'null' && $filterSemester !== 'null')
    {{-- Results Table --}}
    @if ($results->count() > 0)
    @php $grouped = $results->groupBy(fn($r) => $r->academicSession->name . ' – ' . ucfirst($r->semester->name) . '
    Semester'); @endphp
    <div class="space-y-6">
        @foreach ($grouped as $groupLabel => $groupResults)
        @php
        $first = $groupResults->first();
        $accessKey = $first->academic_session_id . '-' . $first->semester_id;
        $hasAccess = $accessMap[$accessKey] ?? true;
        @endphp
        <flux:card>
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 justify-between">
                <div class="flex items-center gap-5">
                    <div>
                        <flux:heading size="lg">{{ $this->student->full_name }}</flux:heading>
                        <p class="font-mono text-sm text-zinc-500 dark:text-zinc-400">{{ $this->student->matric_number
                            }}
                        </p>
                        <p class="text-sm mt-0.5 text-zinc-500">
                            {{ $this->student->program->name ?? '—' }} &bull; {{
                            $this->student->program?->department?->institution?->name ?? '—' }}
                        </p>
                    </div>
                </div>
                <div class="text-center sm:text-right shrink-0">
                    <div class="text-xs font-semibold uppercase tracking-widest text-zinc-400 mb-1">{{ __('Cumulative
                        GPA')
                        }}</div>
                    <div
                        class="text-5xl font-black {{ $cgpa >= 3.5 ? 'text-green-500' : ($cgpa >= 2.0 ? 'text-blue-500' : 'text-red-500') }}">
                        {{ number_format($cgpa, 2) }}
                    </div>
                    <div class="text-xs text-zinc-400 mt-1">{{ $totalUnits }} {{ __('Units Attempted') }}</div>
                </div>
            </div>
        </flux:card>
        <flux:card>
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="md">{{ $groupLabel }}</flux:heading>
                @if ($hasAccess)
                @php
                $grpUnits = $groupResults->sum(fn($r) => $r->course->credit_unit ?? 0);
                $grpPoints = $groupResults->sum(fn($r) => $r->grade_point * ($r->course->credit_unit ?? 0));
                $grpGpa = $grpUnits > 0 ? round($grpPoints / $grpUnits, 2) : 0;
                @endphp
                <div class="flex items-center gap-2">
                    <flux:badge color="zinc" size="sm">{{ $grpUnits }} {{ __('units') }}</flux:badge>
                    <flux:badge :color="$grpGpa >= 3.5 ? 'green' : ($grpGpa >= 2.0 ? 'blue' : 'red')" size="sm">GPA {{
                        number_format($grpGpa, 2) }}</flux:badge>
                </div>
                @else
                <flux:badge color="red" icon="lock-closed" size="sm">{{ __('Payment Required') }}</flux:badge>
                @endif
            </div>

            @if ($hasAccess)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-100 dark:border-zinc-700">
                            <th class="pb-2 text-left font-semibold text-zinc-600 dark:text-zinc-400">{{ __('Course') }}
                            </th>
                            <th class="pb-2 text-center font-semibold text-zinc-600 dark:text-zinc-400">{{ __('Units')
                                }}</th>
                            <th class="pb-2 text-center font-semibold text-zinc-600 dark:text-zinc-400">{{ __('Score')
                                }}</th>
                            <th class="pb-2 text-center font-semibold text-zinc-600 dark:text-zinc-400">{{ __('Grade')
                                }}</th>
                            <th class="pb-2 text-center font-semibold text-zinc-600 dark:text-zinc-400">{{ __('GP') }}
                            </th>
                            <th class="pb-2 text-right font-semibold text-zinc-600 dark:text-zinc-400">{{ __('Remark')
                                }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($groupResults as $res)
                        @php $isSuperseded = !in_array($res->id, $bestResultIds); @endphp
                        <tr
                            class="hover:bg-zinc-50 dark:hover:bg-zinc-900/20 transition-colors {{ $isSuperseded ? 'opacity-50 grayscale select-none' : '' }}">
                            <td class="py-3 pr-4">
                                <div class="flex items-center gap-2">
                                    <div class="font-mono font-semibold uppercase text-zinc-900 dark:text-zinc-100">{{
                                        $res->course->course_code }}</div>
                                    @if ($isSuperseded)
                                    <flux:badge size="sm" inset="top bottom" color="zinc"
                                        class="text-[10px] uppercase tracking-tighter">{{ __('Superseded') }}
                                    </flux:badge>
                                    @endif
                                </div>
                                <div class="text-xs text-zinc-500">{{ $res->course->title }}</div>
                            </td>
                            <td class="py-3 text-center text-zinc-600 dark:text-zinc-400">{{ $res->course->credit_unit
                                }}</td>
                            <td class="py-3 text-center font-medium text-zinc-800 dark:text-zinc-200">{{ (float)
                                $res->total_score }}</td>
                            <td class="py-3 text-center">
                                <span
                                    class="inline-block px-2 py-0.5 rounded font-bold text-sm {{ $res->remark === 'pass' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' }}">
                                    {{ $res->grade }}
                                </span>
                            </td>
                            <td class="py-3 text-center text-zinc-600 dark:text-zinc-400">{{ number_format((float)
                                $res->grade_point, 1) }}</td>
                            <td class="py-3 text-right">
                                <flux:badge :color="$res->remark === 'pass' ? 'green' : 'red'" size="sm">
                                    {{ ucfirst($res->remark) }}
                                </flux:badge>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            {{-- Locked State UI --}}
            <div
                class="py-8 text-center bg-zinc-50 dark:bg-zinc-900/20 rounded-xl border border-dashed border-zinc-200 dark:border-zinc-800">
                <div
                    class="size-12 bg-white dark:bg-zinc-800 rounded-full shadow-sm flex items-center justify-center mx-auto mb-4">
                    <flux:icon.lock-closed class="size-6 text-zinc-400" />
                </div>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-6 max-w-xs mx-auto">
                    {{ __('These results are locked. Please pay the required Result Checking Fee to view your
                    performance.') }}
                </p>

                @php $missingInv = $accessMap[$accessKey . '-invoice'] ?? null; @endphp

                @if ($missingInv)
                <div class="inline-flex flex-col items-center">
                    <span class="text-lg font-black mb-3">₦{{ number_format($missingInv->total_amount, 2) }}</span>
                    <flux:button href="{{ route('cms.students.portal-invoices') }}" variant="primary" size="sm"
                        icon="credit-card">
                        {{ __('Pay to Unlock Results') }}
                    </flux:button>
                </div>
                @else
                <flux:button
                    wire:click="generateResultInvoice({{ $first->academic_session_id }}, {{ $first->semester_id }})"
                    variant="primary" size="sm">
                    {{ __('Generate Invoice & Unlock') }}
                </flux:button>
                @endif
            </div>
            @endif
        </flux:card>
        @endforeach
    </div>
    @else
    <div class="p-12 text-center border-2 border-dashed rounded-2xl text-zinc-500 dark:border-zinc-700">
        {{ __('No results found for the selected filters.') }}
    </div>
    @endif
    @else
    <div class="p-12 text-center border-2 border-dashed rounded-2xl text-zinc-400 dark:border-zinc-800">
        <flux:icon.presentation-chart-line class="size-12 mx-auto mb-4 text-zinc-300" />
        <h3 class="font-medium text-zinc-900 dark:text-white mb-1">{{ __('Select Session & Semester') }}</h3>
        <p class="text-sm">{{ __('Please select an academic session and semester to view your results.') }}</p>
    </div>
    @endif
    @endif
</div>

@push('styles')
<style>
    @media print {

        /* Hide Navigation and Sidebar (assuming standard layout classes) */
        header,
        aside,
        .flux-sidebar,
        .flux-navbar {
            display: none !important;
        }

        /* Adjust main area to use full width and no margins */
        main,
        .flux-main {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        /* Remove borders and shadows from cards */
        .flux-card,
        [data-flux-card] {
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            background: transparent !important;
        }

        /* Prevent page breaks inside cards/tables */
        table {
            page-break-inside: avoid;
        }

        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        /* Ensure text colors are printable */
        * {
            color: #000 !important;
            background-color: transparent !important;
        }

        /* Optional: Add a custom print header */
        body::before {
            content: "Official Academic Results Transcript";
            display: block;
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 20px;
        }
    }
</style>
@endpush