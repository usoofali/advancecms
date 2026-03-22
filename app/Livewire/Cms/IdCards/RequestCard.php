<?php

namespace App\Livewire\Cms\IdCards;

use App\Models\AcademicSession;
use App\Models\IdCardRequest;
use App\Models\StudentInvoice;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Request ID Card')]
class RequestCard extends Component
{
    public string $reason = 'first_issue';

    public ?int $student_invoice_id = null;

    protected $rules = [
        'reason' => 'required|in:first_issue,loss,replacement',
        'student_invoice_id' => 'required_if:reason,loss,replacement',
    ];

    public function mount(): void
    {
        // Redirect if already has an issued card for this session?
        // Or if first issue and already issued once in lifetime.
    }

    #[Computed]
    public function currentSession()
    {
        return AcademicSession::where('status', 'active')->first()
            ?? AcademicSession::orderBy('end_date', 'desc')->first();
    }

    #[Computed]
    public function user()
    {
        return Auth::user();
    }

    #[Computed]
    public function profile()
    {
        $user = $this->user;

        return $user->hasRole('Student') ? $user->student : $user->staff;
    }

    #[Computed]
    public function paidInvoices()
    {
        if (! $this->user->hasRole('Student')) {
            return collect();
        }

        return StudentInvoice::where('student_id', $this->profile->id)
            ->where('status', 'paid')
            ->whereHas('invoice', function ($query) {
                $query->where('title', 'like', '%ID Card%');
            })
            ->with('invoice')
            ->get();
    }

    public function submit(): void
    {
        $this->validate();

        $user = $this->user;
        $profile = $this->profile;
        $session = $this->currentSession;

        if (! $session) {
            $this->dispatch('notify', [
                'message' => __('No active academic session found. Please contact support.'),
                'variant' => 'error',
            ]);

            return;
        }

        if (! $profile || ! $profile->photo_path) {
            $this->dispatch('notify', [
                'message' => __('Please upload a profile photo before requesting an ID card.'),
                'variant' => 'error',
            ]);

            return;
        }

        // Check for existing request in this session for replacements
        if ($this->reason !== 'first_issue') {
            $existing = IdCardRequest::where('user_id', $user->id)
                ->where('academic_session_id', $session->id)
                ->where('reason', '!=', 'first_issue')
                ->where('status', '!=', 'rejected')
                ->exists();

            if ($existing) {
                $this->dispatch('notify', [
                    'message' => __('You have already requested a replacement ID card this session.'),
                    'variant' => 'error',
                ]);

                return;
            }
        } else {
            // Check if first_issue was ever made
            $everIssued = IdCardRequest::where('user_id', $user->id)
                ->where('reason', 'first_issue')
                ->where('status', '!=', 'rejected')
                ->exists();

            if ($everIssued) {
                $this->dispatch('notify', [
                    'message' => __('Your first ID card has already been processed. Please select "Loss" or "Replacement" if you need a new one.'),
                    'variant' => 'error',
                ]);

                return;
            }
        }

        IdCardRequest::create([
            'institution_id' => $user->institution_id,
            'academic_session_id' => $session->id,
            'user_id' => $user->id,
            'type' => $user->hasRole('Student') ? 'student' : 'staff',
            'reason' => $this->reason,
            'student_invoice_id' => $this->student_invoice_id,
            'status' => 'pending',
        ]);

        $this->reset(['reason', 'student_invoice_id']);

        $this->dispatch('notify', [
            'message' => __('ID card request submitted successfully! Admin will review and issue your card.'),
            'variant' => 'success',
        ]);
    }

    public function render()
    {
        return view('livewire.cms.id-cards.request-card');
    }
}
