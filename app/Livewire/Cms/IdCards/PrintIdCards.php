<?php

namespace App\Livewire\Cms\IdCards;

use App\Models\IdCardRequest;
use App\Models\Staff;
use App\Models\Student;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Print ID Cards')]
#[Layout('layouts.guest')] // Use guest layout for a clean print page
class PrintIdCards extends Component
{
    public array $selected_ids = [];

    public string $type = 'student';

    public string $mode = 'requests';

    public $items = [];

    public function mount(string $data): void
    {
        $decoded = json_decode(base64_decode($data), true);
        $this->selected_ids = $decoded['ids'] ?? [];
        $this->type = $decoded['type'] ?? 'student';
        $this->mode = $decoded['mode'] ?? 'requests';

        if (empty($this->selected_ids)) {
            abort(404);
        }

        if ($this->mode === 'requests') {
            $this->items = IdCardRequest::whereIn('id', $this->selected_ids)
                ->with(['user.student.program', 'user.staff', 'institution'])
                ->get();
        } else {
            if ($this->type === 'student') {
                $this->items = Student::whereIn('id', $this->selected_ids)
                    ->with(['program', 'institution'])
                    ->get();
            } else {
                $this->items = Staff::whereIn('id', $this->selected_ids)
                    ->with(['institution'])
                    ->get();
            }
        }
    }

    public function render()
    {
        return view('livewire.cms.id-cards.print-id-cards');
    }
}
