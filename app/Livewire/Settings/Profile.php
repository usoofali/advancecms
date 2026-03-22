<?php

namespace App\Livewire\Settings;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Profile Settings')]
class Profile extends Component
{
    use WithFileUploads;

    public $photo;

    public string $phone = '';

    public string $gender = '';

    public string $date_of_birth = '';

    // Student Specific Fields
    public string $blood_group = '';

    public string $state = '';

    // Staff Bank Details
    public string $bank_name = '';

    public string $account_number = '';

    public string $account_name = '';

    public string $lga = '';

    public string $sitting_1_exam_type = '';

    public string $sitting_1_exam_number = '';

    public string $sitting_1_exam_year = '';

    public string $sitting_2_exam_type = '';

    public string $sitting_2_exam_number = '';

    public string $sitting_2_exam_year = '';

    public string $subject_english = '';

    public string $subject_mathematics = '';

    public string $subject_biology = '';

    public string $subject_chemistry = '';

    public string $subject_physics = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();

        if ($user->hasRole('Student')) {
            $student = $user->student;
            $this->phone = $student->phone ?? '';
            $this->gender = $student->gender ?? '';
            $this->date_of_birth = $student->date_of_birth ? $student->date_of_birth->format('Y-m-d') : '';
            $this->blood_group = $student->blood_group ?? '';
            $this->state = $student->state ?? '';
            $this->lga = $student->lga ?? '';
            $this->sitting_1_exam_type = $student->sitting_1_exam_type ?? '';
            $this->sitting_1_exam_number = $student->sitting_1_exam_number ?? '';
            $this->sitting_1_exam_year = (string) ($student->sitting_1_exam_year ?? '');
            $this->sitting_2_exam_type = $student->sitting_2_exam_type ?? '';
            $this->sitting_2_exam_number = $student->sitting_2_exam_number ?? '';
            $this->sitting_2_exam_year = (string) ($student->sitting_2_exam_year ?? '');
            $this->subject_english = $student->subject_english ?? '';
            $this->subject_mathematics = $student->subject_mathematics ?? '';
            $this->subject_biology = $student->subject_biology ?? '';
            $this->subject_chemistry = $student->subject_chemistry ?? '';
            $this->subject_physics = $student->subject_physics ?? '';
        } elseif ($user->isStaff()) {
            $this->phone = $user->staff->phone ?? '';
            $this->gender = $user->staff->gender ?? '';
            $this->date_of_birth = $user->staff->date_of_birth ? $user->staff->date_of_birth->format('Y-m-d') : '';
            $this->bank_name = $user->staff->bank_name ?? '';
            $this->account_number = $user->staff->account_number ?? '';
            $this->account_name = $user->staff->account_name ?? '';
        }
    }

    /**
     * Update the profile for the currently authenticated user.
     */
    public function updateProfile(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $rules = [
            'phone' => ['required', 'string', 'max:20'],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
        ];

        if ($user->hasRole('Student')) {
            $examTypes = ['NECO', 'WAEC', 'NABTEB', 'NBAIS'];
            $validGrades = ['A', 'A1', 'B2', 'B3', 'C4', 'C5', 'C6', 'D7', 'E8', 'F9'];
            $nigerianStates = [
                'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno', 'Cross River',
                'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT', 'Gombe', 'Imo', 'Jigawa', 'Kaduna', 'Kano',
                'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun',
                'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba', 'Yobe', 'Zamfara',
            ];

            $rules = array_merge($rules, [
                'blood_group' => ['nullable', 'string', 'max:10'],
                'state' => ['nullable', 'string', 'in:'.implode(',', $nigerianStates)],
                'lga' => ['nullable', 'string', 'max:50'],

                // Sitting 1
                'sitting_1_exam_type' => ['nullable', 'string', 'in:'.implode(',', $examTypes)],
                'sitting_1_exam_number' => ['nullable', 'string', function ($attribute, $value, $fail) {
                    if (! $this->sitting_1_exam_type) {
                        return;
                    }
                    $regex = $this->getExamRegex($this->sitting_1_exam_type);
                    if (! preg_match($regex, $value)) {
                        $fail(__('The :attribute format is invalid for :type.', ['type' => $this->sitting_1_exam_type]));
                    }
                }],
                'sitting_1_exam_year' => ['nullable', 'numeric', 'digits:4', 'min:1900', 'max:'.date('Y')],

                // Sitting 2
                'sitting_2_exam_type' => ['nullable', 'string', 'in:'.implode(',', $examTypes)],
                'sitting_2_exam_number' => ['nullable', 'string', function ($attribute, $value, $fail) {
                    if (! $this->sitting_2_exam_type) {
                        return;
                    }
                    $regex = $this->getExamRegex($this->sitting_2_exam_type);
                    if (! preg_match($regex, $value)) {
                        $fail(__('The :attribute format is invalid for :type.', ['type' => $this->sitting_2_exam_type]));
                    }
                }],
                'sitting_2_exam_year' => ['nullable', 'numeric', 'digits:4', 'min:1900', 'max:'.date('Y')],

                // Subjects
                'subject_english' => ['nullable', 'string', 'in:'.implode(',', $validGrades)],
                'subject_mathematics' => ['nullable', 'string', 'in:'.implode(',', $validGrades)],
                'subject_biology' => ['nullable', 'string', 'in:'.implode(',', $validGrades)],
                'subject_chemistry' => ['nullable', 'string', 'in:'.implode(',', $validGrades)],
                'subject_physics' => ['nullable', 'string', 'in:'.implode(',', $validGrades)],
            ]);
        }

        if ($user->isStaff()) {
            $rules = array_merge($rules, [
                'bank_name' => ['nullable', 'string', 'max:100'],
                'account_number' => ['nullable', 'string', 'digits_between:10,15'],
                'account_name' => ['nullable', 'string', 'max:100'],
            ]);
        }

        $this->validate($rules);

        $photoPath = null;
        if ($this->photo) {
            $photoPath = $this->photo->store('profiles', 'public');
        }

        if ($user->hasRole('Student')) {
            $data = [
                'phone' => $this->phone,
                'gender' => $this->gender ?: null,
                'date_of_birth' => $this->date_of_birth ?: null,
                'blood_group' => $this->blood_group ?: null,
                'state' => $this->state ?: null,
                'lga' => $this->lga ?: null,
                'sitting_1_exam_type' => $this->sitting_1_exam_type ?: null,
                'sitting_1_exam_number' => $this->sitting_1_exam_number ?: null,
                'sitting_1_exam_year' => $this->sitting_1_exam_year ?: null,
                'sitting_2_exam_type' => $this->sitting_2_exam_type ?: null,
                'sitting_2_exam_number' => $this->sitting_2_exam_number ?: null,
                'sitting_2_exam_year' => $this->sitting_2_exam_year ?: null,
                'subject_english' => $this->subject_english ?: null,
                'subject_mathematics' => $this->subject_mathematics ?: null,
                'subject_biology' => $this->subject_biology ?: null,
                'subject_chemistry' => $this->subject_chemistry ?: null,
                'subject_physics' => $this->subject_physics ?: null,
            ];

            if ($photoPath) {
                $data['photo_path'] = $photoPath;
            }

            $user->student->update($data);
        } elseif ($user->isStaff()) {
            $data = [
                'phone' => $this->phone,
                'gender' => $this->gender ?: null,
                'date_of_birth' => $this->date_of_birth ?: null,
                'bank_name' => $this->bank_name ?: null,
                'account_number' => $this->account_number ?: null,
                'account_name' => $this->account_name ?: null,
            ];

            if ($photoPath) {
                $data['photo_path'] = $photoPath;
            }

            $user->staff->update($data);
        }

        $this->dispatch('profile-updated');
    }

    /**
     * Get the regex pattern for a specific exam type.
     */
    protected function getExamRegex(string $type): string
    {
        return match ($type) {
            'NECO' => '/^\d{10}(\d{2})?[A-Za-z]{2}$/',
            'WAEC' => '/^\d{10}$/',
            'NABTEB' => '/^\d{8}$/',
            'NBAIS' => '/^\d{6,10}$/',
            default => '/.*/',
        };
    }

    #[Computed]
    public function user(): User
    {
        /** @var User */
        return Auth::user();
    }

    #[Computed]
    public function profile(): mixed
    {
        $user = $this->user();

        if ($user->hasRole('Student')) {
            return $user->student;
        }

        if ($user->isStaff()) {
            return $user->staff;
        }

        return null;
    }

    public function render()
    {
        return view('livewire.settings.profile');
    }
}
