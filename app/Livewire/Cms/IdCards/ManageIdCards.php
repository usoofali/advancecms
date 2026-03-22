<?php

namespace App\Livewire\Cms\IdCards;

use App\Models\Department;
use App\Models\IdCardRequest;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Staff;
use App\Models\Student;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Manage ID Cards')]
class ManageIdCards extends Component
{
    use WithPagination;

    #[Url]
    public string $view_mode = 'requests'; // requests, direct

    #[Url]
    public string $type = 'student'; // student, staff

    #[Url]
    public ?int $institution_id = null;

    #[Url]
    public ?int $department_id = null;

    #[Url]
    public ?int $program_id = null;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'pending';

    public array $selected_ids = [];

    public function mount(): void
    {
        if (! auth()->user()->hasRole('Super Admin')) {
            $this->institution_id = auth()->user()->institution_id;
        }
    }

    public function updated($property): void
    {
        if (in_array($property, ['view_mode', 'type', 'institution_id', 'department_id', 'program_id', 'status', 'search'])) {
            $this->resetPage();
            $this->selected_ids = [];
        }
    }

    #[Computed]
    public function institutions()
    {
        return Institution::orderBy('name')->get();
    }

    #[Computed]
    public function departments()
    {
        $query = Department::orderBy('name');
        if ($this->institution_id) {
            $query->where('institution_id', $this->institution_id);
        }

        return $query->get();
    }

    #[Computed]
    public function programs()
    {
        $query = Program::orderBy('name');
        if ($this->department_id) {
            $query->where('department_id', $this->department_id);
        }

        return $query->get();
    }

    public function toggleSelectAll($ids): void
    {
        if (count($this->selected_ids) === count($ids)) {
            $this->selected_ids = [];
        } else {
            $this->selected_ids = $ids;
        }
    }

    public function approveRequest(int $id): void
    {
        $request = IdCardRequest::findOrFail($id);
        $request->update(['status' => 'approved']);
        $this->dispatch('notify', ['message' => __('Request approved.'), 'variant' => 'success']);
    }

    public function rejectRequest(int $id): void
    {
        $request = IdCardRequest::findOrFail($id);
        $request->update(['status' => 'rejected']);
        $this->dispatch('notify', ['message' => __('Request rejected.'), 'variant' => 'info']);
    }

    public function bulkGenerate(): void
    {
        if (empty($this->selected_ids)) {
            $this->addError('bulk', __('Please select at least one record.'));

            return;
        }

        // Logic to transition status if they were requests
        if ($this->view_mode === 'requests') {
            IdCardRequest::whereIn('id', $this->selected_ids)
                ->where('status', 'approved')
                ->update(['status' => 'issued', 'issued_at' => now()]);
        }

        $payload = [
            'ids' => $this->selected_ids,
            'type' => $this->type,
            'mode' => $this->view_mode,
        ];

        $encoded = base64_encode(json_encode($payload));
        $this->redirectRoute('cms.id-cards.print', ['data' => $encoded]);
    }

    public function render()
    {
        $results = collect();

        if ($this->view_mode === 'requests') {
            $query = IdCardRequest::with(['user.student.program', 'user.staff', 'academicSession']);

            if ($this->institution_id) {
                $query->where('institution_id', $this->institution_id);
            }

            if ($this->type) {
                $query->where('type', $this->type);
            }

            if ($this->status) {
                $query->where('status', $this->status);
            }

            if ($this->search) {
                $query->whereHas('user', function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            }

            $results = $query->latest()->paginate(15);
        } else {
            // Direct Generation (Selecting from Student/Staff pool)
            if ($this->type === 'student') {
                $query = Student::with(['program', 'institution']);
                if ($this->institution_id) {
                    $query->where('institution_id', $this->institution_id);
                }
                if ($this->program_id) {
                    $query->where('program_id', $this->program_id);
                }
                if ($this->search) {
                    $query->where(fn ($q) => $q->where('first_name', 'like', '%'.$this->search.'%')
                        ->orWhere('last_name', 'like', '%'.$this->search.'%')
                        ->orWhere('matric_number', 'like', '%'.$this->search.'%'));
                }
                $results = $query->paginate(15);
            } else {
                $query = Staff::with(['institution']);
                if ($this->institution_id) {
                    $query->where('institution_id', $this->institution_id);
                }
                if ($this->search) {
                    $query->where(fn ($q) => $q->where('first_name', 'like', '%'.$this->search.'%')
                        ->orWhere('last_name', 'like', '%'.$this->search.'%')
                        ->orWhere('staff_number', 'like', '%'.$this->search.'%'));
                }
                $results = $query->paginate(15);
            }
        }

        return view('livewire.cms.id-cards.manage-id-cards', [
            'results' => $results,
        ]);
    }
}
