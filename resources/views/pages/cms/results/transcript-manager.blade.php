<?php

use App\Models\Student;
use App\Models\Result;
use App\Models\AcademicSession;
use App\Models\Semester;
use App\Services\GradingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Transcript Manager')] class extends Component {
    public string $search = '';
    public ?Student $selectedStudent = null;

    // Filters
    public ?int $filterInstitution = null;
    public ?int $filterDepartment = null;
    public ?int $filterProgram = null;

    public function mount(): void
    {
        if (auth()->user()->institution_id) {
            $this->filterInstitution = auth()->user()->institution_id;
        }
    }

    public function updatedFilterInstitution(): void
    {
        $this->filterDepartment = null;
        $this->filterProgram = null;
        $this->selectedStudent = null;
    }

    public function updatedFilterDepartment(): void
    {
        $this->filterProgram = null;
        $this->selectedStudent = null;
    }

    public function updatedFilterProgram(): void
    {
        $this->selectedStudent = null;
    }

    public function updatedSearch(): void
    {
        $this->selectedStudent = null;
    }

    public function selectStudent(int $id): void
    {
        $this->selectedStudent = Student::with(['program.department.institution', 'results.course', 'results.academicSession', 'results.semester'])->find($id);
        $this->search = $this->selectedStudent->matric_number;
    }

    public function with(GradingService $gradingService): array
    {
        $students = [];
        if (strlen($this->search) >= 2 || $this->filterProgram || $this->filterDepartment) {
            $students = Student::query()
                ->when($this->filterInstitution, fn($q) => $q->where('institution_id', $this->filterInstitution))
                ->when($this->filterProgram, fn($q) => $q->where('program_id', $this->filterProgram))
                ->when($this->filterDepartment, fn($q) => $q->whereHas('program', fn($pq) => $pq->where('department_id', $this->filterDepartment)))
                ->when(strlen($this->search) >= 2, function ($q) {
                    $q->where(function ($sq) {
                        $sq->where('matric_number', 'like', "%{$this->search}%")
                            ->orWhere('first_name', 'like', "%{$this->search}%")
                            ->orWhere('last_name', 'like', "%{$this->search}%");
                    });
                })
                ->limit(10)
                ->get();
        }

        $results = collect();
        $cgpa = 0.0;
        $totalUnits = 0;

        if ($this->selectedStudent) {
            $results = $this->selectedStudent->results()
                ->with(['course', 'academicSession', 'semester'])
                ->orderBy('academic_session_id')
                ->orderBy('semester_id')
                ->get();
            
            $cgpa = $gradingService->computeCgpa($this->selectedStudent);
            $totalUnits = $results->sum(fn($r) => $r->course->credit_unit ?? 0);
        }

        return [
            'institutions' => auth()->user()->hasAnyRole(['Super Admin']) ? \App\Models\Institution::all() : [],
            'departments' => $this->filterInstitution ? \App\Models\Department::where('institution_id', $this->filterInstitution)->get() : [],
            'programs' => $this->filterDepartment ? \App\Models\Program::where('department_id', $this->filterDepartment)->get() : [],
            'students' => $students,
            'results' => $results,
            'cgpa' => $cgpa,
            'totalUnits' => $totalUnits,
        ];
    }
}; ?>

<div class="mx-auto max-w-5xl">
    <div class="mb-8 flex items-center justify-between print:hidden">
        <div>
            <flux:heading size="xl">{{ __('Transcript Manager') }}</flux:heading>
            <flux:subheading>{{ __('Search for a student to view and print their comprehensive academic record.') }}</flux:subheading>
        </div>

        @if ($selectedStudent)
        <flux:button icon="printer" variant="primary" x-on:click="window.print()">
            {{ __('Print Transcript') }}
        </flux:button>
        @endif
    </div>

    <flux:card class="mb-8 print:hidden">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            @if (auth()->user()->hasAnyRole(['Super Admin']))
            <flux:select wire:model.live="filterInstitution" :label="__('Institution')" placeholder="{{ __('All Institutions') }}">
                @foreach ($institutions as $inst)
                <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                @endforeach
            </flux:select>
            @endif

            <flux:select wire:model.live="filterDepartment" :label="__('Department')" placeholder="{{ __('All Departments') }}" :disabled="!$filterInstitution">
                @foreach ($departments as $dept)
                <flux:select.option :value="$dept->id">{{ $dept->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filterProgram" :label="__('Program')" placeholder="{{ __('All Programs') }}" :disabled="!$filterDepartment">
                @foreach ($programs as $prog)
                <flux:select.option :value="$prog->id">{{ $prog->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search by Matric Number or Name...') }}" />

        @if (count($students) > 0 && !$selectedStudent)
        <div class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
            @foreach ($students as $stu)
            <button wire:click="selectStudent({{ $stu->id }})" class="w-full text-left px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-900/50 transition-colors flex items-center justify-between group">
                <div>
                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $stu->full_name }}</div>
                    <div class="text-xs text-zinc-500 font-mono">{{ $stu->matric_number }}</div>
                </div>
                <flux:icon.chevron-right class="size-4 text-zinc-300 group-hover:text-zinc-500" />
            </button>
            @endforeach
        </div>
        @endif
    </flux:card>

    @if ($selectedStudent)
    <div class="space-y-6 print:space-y-4">
        {{-- Official Header --}}
        <div class="flex flex-col md:flex-row items-center md:items-start justify-center text-center md:text-left border-b-2 border-zinc-900 dark:border-white pb-3 pt-0 gap-4">
            <div class="flex flex-col md:flex-row items-center gap-4 mx-auto">
                @php $institution = $selectedStudent->program?->department?->institution; @endphp
                @if ($institution?->logo_url)
                <img src="{{ $institution->logo_url }}" alt="Logo" class="size-20 object-contain" />
                @else
                <div class="size-20 bg-zinc-100 dark:bg-zinc-800 rounded flex items-center justify-center border border-zinc-200 dark:border-zinc-700 shrink-0">
                    <flux:icon.building-library class="size-10 text-zinc-300 dark:text-zinc-600" />
                </div>
                @endif
                
                <div class="space-y-1">
                    <h1 class="text-2xl font-black uppercase tracking-tight text-zinc-900 dark:text-white leading-none">{{ $institution?->name ?? __('Institution Name') }}</h1>
                    <div class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">{{ $institution?->address ?? __('Institution Address') }}</div>
                    <div class="flex flex-col md:flex-row md:items-center justify-center text-[10px] font-bold text-zinc-500 uppercase tracking-widest md:gap-4">
                        <span>TEL: {{ $institution?->phone ?? '—' }}</span>
                        <span>EMAIL: {{ $institution?->email ?? '—' }}</span>
                    </div>
                    <div class="inline-block px-3 py-1 mt-1 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 text-[11px] font-black tracking-[0.2em] uppercase">
                        {{ __('Official Academic Transcript') }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Profile Section --}}
        <div class="print:border-b-2 print:border-zinc-900 pb-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 items-start border-b border-zinc-100 dark:border-zinc-800 pb-4 print:hidden">
                <div class="md:col-span-2">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-zinc-400 mb-1">{{ __('Student Full Name') }}</div>
                    <div class="text-2xl font-black text-zinc-900 dark:text-white uppercase leading-tight">{{ $selectedStudent->full_name }}</div>
                </div>
                
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-widest text-zinc-400 mb-1">{{ __('Matriculation Number') }}</div>
                    <div class="font-mono text-lg font-bold text-zinc-900 dark:text-white">{{ $selectedStudent->matric_number }}</div>
                </div>

                <div class="sm:text-right lg:text-right">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-zinc-400 mb-1">{{ __('Cumulative GPA') }}</div>
                    <div class="text-3xl font-black {{ $cgpa >= 3.5 ? 'text-green-600' : ($cgpa >= 2.0 ? 'text-blue-600' : 'text-red-600') }}">
                        {{ number_format($cgpa, 2) }}
                    </div>
                </div>

                <div class="md:col-span-2">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-zinc-400 mb-1">{{ __('Academic Program') }}</div>
                    <div class="text-sm font-bold text-zinc-800 dark:text-zinc-200 tracking-tight">{{ $selectedStudent->program->name ?? '—' }}</div>
                </div>

                <div>
                    <div class="text-[10px] font-bold uppercase tracking-widest text-zinc-400 mb-1">{{ __('Department') }}</div>
                    <div class="text-sm font-bold text-zinc-800 dark:text-zinc-200 tracking-tight">{{ $selectedStudent->program?->department?->name ?? '—' }}</div>
                </div>

                <div class="sm:text-right lg:text-right">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-zinc-400 mb-1">{{ __('Units Earned') }}</div>
                    <div class="text-sm font-black text-zinc-900 dark:text-white">{{ $totalUnits }}</div>
                </div>
            </div>

            {{-- Print-Only Stable Profile Layout --}}
            <table class="hidden print:table w-full border-collapse mb-4 mt-4">
                <tr>
                    <td class="py-1 w-1/3">
                        <div class="text-[8px] font-bold text-zinc-400 uppercase">{{ __('Student Full Name') }}</div>
                        <div class="text-lg font-black text-zinc-900 uppercase">{{ $selectedStudent->full_name }}</div>
                    </td>
                    <td class="py-1 w-1/3">
                        <div class="text-[8px] font-bold text-zinc-400 uppercase">{{ __('Matriculation Number') }}</div>
                        <div class="text-lg font-bold text-zinc-900">{{ $selectedStudent->matric_number }}</div>
                    </td>
                    <td class="py-1 w-1/3 text-right">
                        <div class="text-[8px] font-bold text-zinc-400 uppercase">{{ __('Cumulative GPA') }}</div>
                        <div class="text-2xl font-black text-zinc-900">{{ number_format($cgpa, 2) }}</div>
                    </td>
                </tr>
                <tr>
                    <td class="py-1" colspan="2">
                        <div class="text-[8px] font-bold text-zinc-400 uppercase">{{ __('Academic Program') }}</div>
                        <div class="text-sm font-bold text-zinc-900">{{ $selectedStudent->program->name ?? '—' }}</div>
                    </td>
                    <td class="py-1 text-right">
                        <div class="text-[8px] font-bold text-zinc-400 uppercase">{{ __('Units Earned') }}</div>
                        <div class="text-sm font-black text-zinc-900">{{ $totalUnits }}</div>
                    </td>
                </tr>
                <tr>
                    <td class="py-1" colspan="3">
                        <div class="text-[8px] font-bold text-zinc-400 uppercase">{{ __('Department') }}</div>
                        <div class="text-sm font-bold text-zinc-900">{{ $selectedStudent->program?->department?->name ?? '—' }}</div>
                    </td>
                </tr>
            </table>
        </div>

        {{-- Results Breakdown --}}
        @php $grouped = $results->groupBy(fn($r) => $r->academicSession->name . ' – ' . ucfirst($r->semester->name) . ' Semester'); @endphp
        
        <div class="space-y-6 print:space-y-4">
            @foreach ($grouped as $groupLabel => $groupResults)
            @php
                $grpUnits = $groupResults->sum(fn($r) => $r->course->credit_unit ?? 0);
                $grpPoints = $groupResults->sum(fn($r) => $r->grade_point * ($r->course->credit_unit ?? 0));
                $grpGpa = $grpUnits > 0 ? round($grpPoints / $grpUnits, 2) : 0;
            @endphp
            <div class="print:break-inside-avoid">
                <div class="flex items-center justify-between mb-3 px-1">
                    <flux:heading size="md" class="uppercase tracking-wide font-bold print:text-sm">{{ $groupLabel }}</flux:heading>
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-bold text-zinc-400 uppercase tracking-widest print:text-[8px]">{{ __('GPA') }}</span>
                        <flux:badge :color="$grpGpa >= 3.5 ? 'green' : ($grpGpa >= 2.0 ? 'blue' : 'red')" size="sm" class="font-black print:text-zinc-900 print:bg-white print:border print:border-zinc-200">
                            {{ number_format($grpGpa, 2) }}
                        </flux:badge>
                    </div>
                </div>

                <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-800 rounded-xl bg-white dark:bg-zinc-900 shadow-sm print:shadow-none print:rounded-none">
                    <table class="w-full text-sm print:text-[9px] min-w-[600px] md:min-w-full">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-800">
                            <tr>
                                <th class="px-4 py-3 text-left font-bold text-zinc-500 uppercase tracking-tighter text-[10px] print:text-[8px]">{{ __('Course Code') }}</th>
                                <th class="px-4 py-3 text-left font-bold text-zinc-500 uppercase tracking-tighter text-[10px] print:text-[8px]">{{ __('Course Title') }}</th>
                                <th class="px-4 py-3 text-center font-bold text-zinc-500 uppercase tracking-tighter text-[10px] print:text-[8px] w-12">{{ __('Units') }}</th>
                                <th class="px-4 py-3 text-center font-bold text-zinc-500 uppercase tracking-tighter text-[10px] print:text-[8px] w-12">{{ __('Grade') }}</th>
                                <th class="px-4 py-3 text-center font-bold text-zinc-500 uppercase tracking-tighter text-[10px] print:text-[8px] w-12">{{ __('GP') }}</th>
                                <th class="px-4 py-3 text-right font-bold text-zinc-500 uppercase tracking-tighter text-[10px] print:text-[8px]">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($groupResults as $res)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/10 transition-colors">
                                <td class="px-4 py-3 font-mono font-bold text-zinc-900 dark:text-zinc-100 print:py-1.5">{{ $res->course->course_code }}</td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400 print:py-1.5">{{ $res->course->title }}</td>
                                <td class="px-4 py-3 text-center font-medium print:py-1.5">{{ $res->course->credit_unit }}</td>
                                <td class="px-4 py-3 text-center print:py-1.5">
                                    <span class="font-black {{ $res->remark === 'pass' ? 'text-green-600' : 'text-red-600' }} print:text-zinc-900">
                                        {{ $res->grade }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center font-medium print:py-1.5">{{ number_format((float)$res->grade_point, 1) }}</td>
                                <td class="px-4 py-3 text-right print:py-1.5">
                                    <span class="text-[10px] font-bold uppercase tracking-widest {{ $res->remark === 'pass' ? 'text-green-500' : 'text-red-500' }} print:text-zinc-900 print:text-[7px]">
                                        {{ $res->remark }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Verification Section --}}
        <div class="mt-8 flex justify-between items-end print:mt-8">
            <div class="space-y-4">
                <div class="text-xs font-bold uppercase tracking-widest text-zinc-400 print:text-[8px]">{{ __('Authorized Signature') }}</div>
                <div class="w-64 border-b-2 border-zinc-900 dark:border-white h-8"></div>
                <div class="text-[10px] font-bold text-zinc-500 print:text-[8px]">{{ __('Academic Secretary / Registrar') }}</div>
            </div>

            <div class="text-center flex flex-col items-center gap-1 -mb-1">
                <div class="size-16 border border-zinc-200 dark:border-zinc-800 p-1 flex items-center justify-center bg-white shadow-sm ring-4 ring-zinc-50 dark:ring-zinc-900/50">
                    <svg viewBox="0 0 100 100" class="size-full text-zinc-900">
                        @for ($i = 0; $i < 10; $i++)
                            @for ($j = 0; $j < 10; $j++)
                                <rect x="{{ $i * 10 + 4 }}" y="{{ $j * 10 + 4 }}" width="2" height="2" fill="currentColor" opacity="0.1" />
                            @endfor
                        @endfor
                        <path d="M5,5 h25 v25 h-25 z M10,10 h15 v15 h-15 z M13,13 h9 v9 h-9 z" fill="currentColor" />
                        <path d="M70,5 h25 v25 h-25 z M75,10 h15 v15 h-15 z M78,13 h9 v9 h-9 z" fill="currentColor" />
                        <path d="M5,70 h25 v25 h-25 z M10,75 h15 v15 h-15 z M13,78 h9 v9 h-9 z" fill="currentColor" />
                        <path d="M72,72 h12 v12 h-12 z M75,75 h6 v6 h-6 z" fill="currentColor" />
                        <rect x="40" y="5" width="5" height="5" fill="currentColor" />
                        <rect x="50" y="10" width="5" height="5" fill="currentColor" />
                        <rect x="40" y="20" width="10" height="5" fill="currentColor" />
                        <rect x="55" y="5" width="5" height="10" fill="currentColor" />
                        <rect x="5" y="40" width="5" height="5" fill="currentColor" />
                        <rect x="15" y="45" width="5" height="10" fill="currentColor" />
                        <rect x="25" y="40" width="5" height="5" fill="currentColor" />
                        <rect x="40" y="40" width="20" height="5" fill="currentColor" />
                        <rect x="40" y="50" width="5" height="20" fill="currentColor" />
                        <rect x="55" y="45" width="5" height="5" fill="currentColor" />
                        <rect x="65" y="40" width="10" height="5" fill="currentColor" />
                        <rect x="70" y="40" width="5" height="5" fill="currentColor" />
                        <rect x="85" y="45" width="5" height="15" fill="currentColor" />
                        <rect x="45" y="75" width="10" height="5" fill="currentColor" />
                        <rect x="40" y="85" width="15" height="5" fill="currentColor" />
                        <rect x="60" y="80" width="5" height="5" fill="currentColor" />
                    </svg>
                </div>
                <div class="text-[7px] font-bold text-zinc-400 uppercase tracking-widest">{{ __('Scan to Verify') }}</div>
            </div>

            <div class="text-right space-y-2">
                <div class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest print:text-[7px]">{{ __('Date of Issue') }}</div>
                <div class="text-sm font-black print:text-[10px]">{{ now()->format('D, M j, Y') }}</div>
                <div class="text-[8px] italic text-zinc-400 print:text-[6px]">{{ __('This transcript is valid only when it bears the official stamp.') }}</div>
            </div>
        </div>
    </div>
    @else
    <div class="p-20 text-center border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-3xl text-zinc-400">
        <flux:icon.academic-cap class="size-16 mx-auto mb-6 text-zinc-200 dark:text-zinc-800" />
        <flux:heading size="lg" class="mb-2">{{ __('No Student Selected') }}</flux:heading>
        <p class="max-w-xs mx-auto text-sm">{{ __('Use the search bar above to find a student by their matric number or full name.') }}</p>
    </div>
    @endif

    <style>
        @media print {
            body { 
                background: white !important;
                color: black !important;
                font-size: 10px;
            }
            .mx-auto.max-w-5xl {
                max-width: 100%;
                width: 100%;
                margin: 0;
                padding: 0;
            }
            @page {
                margin: 0.5cm;
                size: portrait;
            }
            flux-card {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
            }
            .space-y-6 { margin-top: 0.5rem; }
            table td, table th { padding: 4px 8px !important; }
            h1 { font-size: 1.5rem !important; }
            .p-6, .px-4, .py-3 { padding: 0.25rem !important; }
        }
    </style>
</div>
