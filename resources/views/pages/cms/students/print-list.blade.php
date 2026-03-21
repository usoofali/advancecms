<?php

use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Student;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.guest')] #[Title('Print Student List')] class extends Component
{
    public $institution_id;

    public $department_id;

    public $program_id;

    public $level;

    public $status;

    public string $search = '';

    public function mount()
    {
        $this->institution_id = request('institution_id');
        $this->department_id = request('department_id');
        $this->program_id = request('program_id');
        $this->level = request('level');
        $this->status = request('status');
        $this->search = request('search', '');
    }

    public function students()
    {
        return Student::query()
            ->with(['program.department.institution'])
            ->when($this->institution_id ?: auth()->user()->institution_id, fn ($q, $id) => $q->where('institution_id', $id))
            ->when($this->department_id, function ($q) {
                $q->whereHas('program', fn ($pq) => $pq->where('department_id', $this->department_id));
            })
            ->when($this->program_id, fn ($q) => $q->where('program_id', $this->program_id))
            ->when($this->level, fn ($q) => $q->where('entry_level', $this->level))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('matric_number', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('matric_number')
            ->get();
    }

    public function getHeader()
    {
        $parts = [];
        if ($this->institution_id) {
            $parts[] = Institution::find($this->institution_id)?->name;
        }
        if ($this->department_id) {
            $parts[] = Department::find($this->department_id)?->name;
        }
        if ($this->program_id) {
            $parts[] = Program::find($this->program_id)?->name;
        }
        if ($this->level) {
            $parts[] = 'Level '.$this->level;
        }
        if ($this->status) {
            $parts[] = ucfirst($this->status);
        }

        return ! empty($parts) ? implode(' - ', $parts) : 'All Students';
    }
};
?>

<div class="p-8 bg-white min-h-screen">
    <div class="flex flex-col items-center mb-8 border-b-2 border-black pb-4">
        <h1 class="text-2xl font-bold uppercase">{{ config('app.name') }}</h1>
        <h2 class="text-xl font-bold uppercase">Student Enrollment List</h2>
        <div class="mt-2 text-sm font-medium">
            {{ $this->getHeader() }}
        </div>
        <div class="mt-1 text-xs text-gray-500">
            Generated on: {{ now()->format('M d, Y H:i A') }}
        </div>
    </div>

    <table class="w-full text-sm border-collapse border border-gray-300">
        <thead>
            <tr class="bg-gray-100">
                <th class="border border-gray-300 px-3 py-2 text-left">S/N</th>
                <th class="border border-gray-300 px-3 py-2 text-left">Matric Number</th>
                <th class="border border-gray-300 px-3 py-2 text-left">Full Name</th>
                <th class="border border-gray-300 px-3 py-2 text-left">Program</th>
                <th class="border border-gray-300 px-3 py-2 text-left">Level</th>
                <th class="border border-gray-300 px-3 py-2 text-left">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($this->students() as $index => $student)
                <tr>
                    <td class="border border-gray-300 px-3 py-2">{{ $index + 1 }}</td>
                    <td class="border border-gray-300 px-3 py-2 font-mono uppercase font-bold">{{ $student->matric_number }}</td>
                    <td class="border border-gray-300 px-3 py-2 uppercase">{{ $student->full_name }}</td>
                    <td class="border border-gray-300 px-3 py-2 uppercase text-xs">{{ $student->program->name }}</td>
                    <td class="border border-gray-300 px-3 py-2">{{ $student->entry_level }}</td>
                    <td class="border border-gray-300 px-3 py-2 uppercase">{{ $student->status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-8 pt-8 flex justify-between text-sm">
        <div class="border-t border-black w-48 text-center pt-2 italic">Registrar Signature</div>
        <div class="border-t border-black w-48 text-center pt-2 italic">Date</div>
    </div>

    <style>
        @media print {
            .no-print { display: none; }
            body { padding: 0; margin: 0; }
            @page { margin: 1cm; }
        }
    </style>

    <div class="mt-10 no-print flex justify-center">
        <flux:button variant="primary" icon="printer" onclick="window.print()">Print Report</flux:button>
    </div>
</div>
