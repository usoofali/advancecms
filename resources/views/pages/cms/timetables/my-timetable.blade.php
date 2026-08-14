<?php

use App\Models\AcademicSession;
use App\Models\Semester;
use App\Models\Timetable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('My Lecture Timetable')] class extends Component {
    public int|string $session_id = '';
    public int|string $semester_id = '';

    public function mount(): void
    {
        Gate::authorize('timetables.view_personal');

        $user = auth()->user();

        $activeSession = AcademicSession::where('status', 'active')->first()
            ?? AcademicSession::latest()->first();
        if ($activeSession) {
            $this->session_id = $activeSession->id;
        }

        $activeSemester = Semester::where('academic_session_id', $this->session_id)->first();
        if ($activeSemester) {
            $this->semester_id = $activeSemester->id;
        }
    }

    public function getTimetableEntriesProperty()
    {
        $user = auth()->user();

        $query = Timetable::with(['course', 'user', 'allocatable', 'program', 'department'])
            ->where('academic_session_id', $this->session_id)
            ->where('semester_id', $this->semester_id);

        if ($user->hasRole('Student')) {
            return $query->forStudent($user)->get();
        }

        if ($user->hasRole('Lecturer')) {
            return $query->forLecturer($user)->get();
        }

        // Default or admin viewing personal route fallback
        return $query->get();
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white flex items-center gap-2">
                <flux:icon.clock class="size-7 text-indigo-500" />
                {{ __('My Lecture Timetable') }}
            </h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Your personal weekly lecture schedule.') }}
            </p>
        </div>

        <div>
            <a 
                href="{{ route('cms.timetables.print', ['session_id' => $session_id, 'semester_id' => $semester_id, 'personal' => 1]) }}" 
                target="_blank" 
                class="inline-flex items-center gap-2 rounded-lg border border-zinc-300 bg-white px-3.5 py-2 text-xs font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
            >
                <flux:icon.printer class="size-4 text-emerald-500" />
                {{ __('Print My Timetable') }}
            </a>
        </div>
    </div>

    <!-- Matrix View -->
    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        @php
            $entries = $this->timetableEntries;
            $maxAllocatedPeriod = $entries->max(fn ($e) => (int) $e->period_number) ?: 1;
            $periods = range(1, max(1, $maxAllocatedPeriod));

            $hasSaturday = $entries->contains(fn ($e) => strcasecmp($e->day_of_week, 'Saturday') === 0);
            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
            if ($hasSaturday) {
                $days[] = 'Saturday';
            }
        @endphp

        <table class="w-full min-w-[800px] border-collapse text-left text-sm">
            <thead>
                <tr class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-800/50">
                    <th class="p-3 font-semibold text-zinc-600 dark:text-zinc-400 w-32 border-r border-zinc-200 dark:border-zinc-800 text-center">
                        {{ __('Day / Period') }}
                    </th>
                    @foreach ($periods as $pNum)
                        <th class="p-3 font-semibold text-zinc-900 dark:text-white text-center border-r border-zinc-200 dark:border-zinc-800">
                            <div class="font-bold text-indigo-600 dark:text-indigo-400">Period {{ $pNum }}</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @foreach ($days as $day)
                    <tr>
                        <td class="p-3 font-bold text-zinc-900 dark:text-white bg-zinc-50/50 dark:bg-zinc-800/30 border-r border-zinc-200 dark:border-zinc-800 text-center">
                            {{ $day }}
                        </td>

                        @foreach ($periods as $pNum)
                            @php
                                $slot = $entries->first(fn ($e) => strcasecmp($e->day_of_week, $day) === 0 && (int)$e->period_number === (int)$pNum);
                            @endphp
                            <td class="p-2 border-r border-zinc-200 dark:border-zinc-800 vertical-top h-28 w-44">
                                @if ($slot)
                                    <div class="flex h-full flex-col justify-between rounded-lg border border-indigo-200 bg-indigo-50/70 p-2.5 dark:border-indigo-900/50 dark:bg-indigo-950/40">
                                        <div>
                                            <span class="font-bold text-indigo-900 dark:text-indigo-200 text-sm">
                                                {{ $slot->resolved_course?->course_code ?? $slot->course?->course_code }}
                                            </span>
                                            <p class="text-xs text-zinc-700 dark:text-zinc-300 font-medium line-clamp-1 mt-0.5">
                                                {{ $slot->resolved_course?->title ?? $slot->course?->title }}
                                            </p>
                                            <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1 flex items-center gap-1">
                                                <flux:icon.user class="size-3 text-amber-500 shrink-0" />
                                                <span class="truncate">{{ $slot->resolved_lecturer?->name ?? 'Unassigned' }}</span>
                                            </p>
                                        </div>

                                        <div class="mt-2 flex items-center justify-between pt-1 border-t border-indigo-100 dark:border-indigo-900/30 text-[10px] text-indigo-700 dark:text-indigo-300">
                                            <span>{{ $slot->start_time }} - {{ $slot->end_time }}</span>
                                            <span class="font-medium text-zinc-500">{{ $slot->program?->name }}</span>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex h-full items-center justify-center text-xs text-zinc-400">
                                        -
                                    </div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
