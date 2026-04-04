<?php

use App\Models\Student;
use App\ViewModels\AdmissionLetterPayload;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admission Letter')] #[Layout('layouts.guest')] class extends Component
{
    public Student $student;

    /** @var array<string, mixed> */
    public array $letter = [];

    public function mount(Student $student): void
    {
        $user = auth()->user();
        if ($user->institution_id && $student->institution_id !== $user->institution_id) {
            abort(403, 'Unauthorized. This student record belongs to another institution.');
        }

        $this->student = $student->load(['institution', 'program.department']);
        $this->letter = AdmissionLetterPayload::fromStudent($this->student);
    }
}; ?>

<x-admission-letter.sheet :letter="$letter" />
