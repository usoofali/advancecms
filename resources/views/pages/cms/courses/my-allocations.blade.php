<?php

use App\Models\CourseAllocation;
use App\Models\CourseRegistration;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('My Allocations')] class extends Component {
    public ?int $selectedAllocationId = null;

    public function selectAllocation(int $id): void
    {
        $this->selectedAllocationId = $id;
    }

    public function render(): \Illuminate\View\View
    {
        $user = auth()->user();

        // 1. Fetch the lecturer's allocations, eager loading the related Session, Semester, and Course.
        $allocations = CourseAllocation::with(['academicSession', 'semester', 'course'])
            ->where('user_id', $user->id)
            ->orderByDesc('academic_session_id')
            ->orderByDesc('semester_id')
            ->get();

        // 2. If an allocation is selected, fetch the students registered for that explicit course/session/semester.
        $students = collect();
        $selectedAllocation = null;

        if ($this->selectedAllocationId) {
            $selectedAllocation = $allocations->firstWhere('id', $this->selectedAllocationId);
            
            if ($selectedAllocation) {
                // Fetch all course registrations matching these exact parameters, eager loading the student profile
                $students = CourseRegistration::with('student')
                    ->where('course_id', $selectedAllocation->course_id)
                    ->where('academic_session_id', $selectedAllocation->academic_session_id)
                    ->where('semester_id', $selectedAllocation->semester_id)
                    ->get()
                    ->map(fn($reg) => $reg->student)
                    ->sortBy('last_name')
                    ->values();
            }
        }

        return view('pages.cms.courses.my-allocations', [
            'allocations' => $allocations,
            'selectedAllocation' => $selectedAllocation,
            'students' => $students,
        ]);
    }
}; ?>

<div class="mx-auto max-w-6xl">
    <div class="mb-8 items-center justify-between flex">
        <div>
            <flux:heading size="xl">{{ __('My Allocated Courses') }}</flux:heading>
            <flux:subheading>{{ __('View courses assigned to you and access their registered student lists') }}</flux:subheading>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Sidebar: List of Allocations -->
        <div class="lg:col-span-1 space-y-4">
            @forelse ($allocations as $alloc)
                <flux:card 
                    class="cursor-pointer transition-all hover:border-zinc-400 dark:hover:border-zinc-500 {{ $selectedAllocationId === $alloc->id ? 'border-zinc-500 dark:border-zinc-400 ring-1 ring-zinc-500 dark:ring-zinc-400' : '' }}"
                    wire:click="selectAllocation({{ $alloc->id }})"
                >
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <div class="font-mono text-sm tracking-tight uppercase font-semibold text-zinc-900 dark:text-white">
                                {{ $alloc->course->course_code }}
                            </div>
                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-300 line-clamp-1">
                                {{ $alloc->course->title }}
                            </div>
                        </div>
                        <flux:badge size="sm" color="zinc">{{ $alloc->course->credit_unit }} {{ __('Units') }}</flux:badge>
                    </div>
                    
                    <div class="flex items-center gap-2 text-xs text-zinc-500 mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:icon.calendar class="w-3.5 h-3.5" />
                        <span>{{ $alloc->academicSession->name }} &bull; {{ ucfirst($alloc->semester->name) }}</span>
                    </div>
                </flux:card>
            @empty
                <flux:card class="text-center py-8">
                    <flux:icon.inbox class="w-8 h-8 mx-auto text-zinc-400 mb-2" />
                    <p class="text-sm text-zinc-500">{{ __('No courses have been allocated to you yet.') }}</p>
                </flux:card>
            @endforelse
        </div>

        <!-- Main Content: Student List -->
        <div class="lg:col-span-2">
            @if ($selectedAllocation)
                <flux:card>
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                        <div>
                            <flux:heading size="lg">{{ $selectedAllocation->course->title }}</flux:heading>
                            <div class="flex items-center gap-2 mt-1">
                                <flux:badge size="sm" color="blue">{{ $selectedAllocation->course->course_code }}</flux:badge>
                                <span class="text-sm text-zinc-500">{{ $selectedAllocation->academicSession->name }} &bull; {{ ucfirst($selectedAllocation->semester->name) }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:button variant="ghost" size="sm" icon="printer" onclick="window.print()">
                                {{ __('Print List') }}
                            </flux:button>
                            <flux:button variant="primary" size="sm" href="{{ route('cms.results.entry') }}" wire:navigate>
                                {{ __('Enter Results') }}
                            </flux:button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="sm">{{ __('Registered Students') }}</flux:heading>
                        <flux:badge size="sm" color="zinc">{{ count($students) }} {{ __('Total') }}</flux:badge>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>{{ __('Matric Number') }}</flux:table.column>
                                <flux:table.column>{{ __('Student Name') }}</flux:table.column>
                                <flux:table.column>{{ __('Gender') }}</flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @forelse ($students as $student)
                                    <flux:table.row>
                                        <flux:table.cell>
                                            <div class="font-mono text-sm uppercase">{{ $student->matric_number }}</div>
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <div class="font-medium text-zinc-900 dark:text-white">{{ $student->full_name }}</div>
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <div class="text-sm">{{ ucfirst($student->gender) }}</div>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="3" class="text-center text-zinc-500 py-8">
                                            {{ __('No students have registered for this course yet.') }}
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    </div>
                </flux:card>
            @else
                <flux:card class="h-full flex flex-col items-center justify-center py-16 text-center text-zinc-500 min-h-[300px]">
                    <flux:icon.cursor-arrow-rays class="w-12 h-12 mb-4 text-zinc-300 dark:text-zinc-600" />
                    <flux:heading size="md" class="text-zinc-500 dark:text-zinc-400">{{ __('Select a Course') }}</flux:heading>
                    <p class="text-sm mt-1 max-w-xs">{{ __('Click on any of your allocated courses from the sidebar to view the list of registered students.') }}</p>
                </flux:card>
            @endif
        </div>

        </div>
    </div>
</div>

@push('styles')
<style>
    @media print {
        /* Hide layout elements */
        nav, aside, footer {
            display: none !important;
        }

        /* Hide the allocations list sidebar */
        .lg\:col-span-1 {
            display: none !important;
        }

        /* Expand main content */
        .lg\:col-span-2 {
            grid-column: span 3 / span 3 !important;
            width: 100% !important;
        }

        /* Hide specific elements inside the main content */
        button, a[href] {
            display: none !important;
        }

        /* Adjust colors and borders for printing */
        body {
            background-color: white !important;
            color: black !important;
        }

        table {
            border: 1px solid #ccc !important;
        }

        th, td {
            color: black !important;
            border-bottom: 1px solid #ccc !important;
        }

        /* Remove card styles to save ink */
        .rounded-xl, .shadow-sm, .dark\:bg-zinc-800, .bg-white {
            border: none !important;
            box-shadow: none !important;
            background: transparent !important;
            border-radius: 0 !important;
        }
    }
</style>
@endpush
