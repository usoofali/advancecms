<?php

namespace App\Models;

use App\Notifications\EnrollmentNotification;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function ($student) {
            if (! $student->matric_number) {
                $program = Program::find($student->program_id);
                $institution = Institution::find($student->institution_id);
                $instAcronym = $institution ? ($institution->acronym ?? 'CMS') : 'CMS';
                $progAcronym = $program ? ($program->acronym ?? 'STD') : 'STD';
                $year = $student->admission_year;

                $count = static::where('institution_id', $student->institution_id)
                    ->where('program_id', $student->program_id)
                    ->where('admission_year', $year)
                    ->count() + 1;

                $student->matric_number = strtoupper(sprintf('%s/%s/%s/%03d', $instAcronym, $progAcronym, $year, $count));
            } else {
                $student->matric_number = strtoupper($student->matric_number);
            }
        });

        static::saved(function ($student) {
            if ($student->email) {
                $user = User::where('email', $student->email)->first();

                if (! $user) {
                    $password = '12345678';

                    $user = User::create([
                        'name' => "{$student->first_name} {$student->last_name}",
                        'email' => $student->email,
                        'institution_id' => $student->institution_id,
                        'password' => Hash::make($password),
                    ]);

                    $user->notify(new EnrollmentNotification($student, $password));
                } else {
                    $user->update([
                        'name' => "{$student->first_name} {$student->last_name}",
                        'institution_id' => $student->institution_id,
                    ]);
                }

                $studentRole = Role::where('role_name', 'Student')->first();
                if ($studentRole) {
                    $user->roles()->sync([$studentRole->role_id]);
                }
            }
        });
    }

    protected $fillable = [
        'institution_id',
        'program_id',
        'matric_number',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'email',
        'phone',
        'blood_group',
        'state',
        'lga',
        'sitting_1_exam_type',
        'sitting_1_exam_number',
        'sitting_1_exam_year',
        'sitting_2_exam_type',
        'sitting_2_exam_number',
        'sitting_2_exam_year',
        'subject_english',
        'subject_mathematics',
        'subject_biology',
        'subject_chemistry',
        'subject_physics',
        'admission_year',
        'entry_level',
        'status',
        'photo_path',
    ];

    /**
     * Calculate profile completion percentage based on student-editable fields.
     */
    public function getCompletionPercentageAttribute(): int
    {
        $fields = [
            'phone',
            'blood_group',
            'state',
            'lga',
            'sitting_1_exam_type',
            'sitting_1_exam_number',
            'sitting_1_exam_year',
            'subject_english',
            'subject_mathematics',
            'subject_biology',
            'subject_chemistry',
            'subject_physics',
            'photo_path',
        ];

        $filled = 0;
        foreach ($fields as $field) {
            if (! empty($this->$field)) {
                $filled++;
            }
        }

        return (int) round(($filled / count($fields)) * 100);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? asset('storage/'.$this->photo_path) : null;
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function courseRegistrations(): HasMany
    {
        return $this->hasMany(CourseRegistration::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }

    /**
     * Get the student's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Calculate the student's current level for a given academic session.
     * Session name format: "YYYY/YYYY" (e.g. "2024/2025").
     */
    public function currentLevel(AcademicSession $session): int
    {
        $sessionStartYear = (int) explode('/', $session->name)[0];
        $yearsElapsed = max(0, $sessionStartYear - $this->admission_year);

        return $this->entry_level + ($yearsElapsed * 100);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * Calculate attendance percentage.
     * If courseId is null, returns overall percentage across all registered courses.
     */
    public function getAttendancePercentage(?int $courseId = null, ?int $sessionId = null, ?int $semesterId = null): int
    {
        $query = $this->attendanceRecords();

        if ($courseId || $sessionId || $semesterId) {
            $query->whereHas('attendance.courseAllocation', function ($q) use ($courseId, $sessionId, $semesterId) {
                if ($courseId) {
                    $q->where('course_id', $courseId);
                }
                if ($sessionId) {
                    $q->where('academic_session_id', $sessionId);
                }
                if ($semesterId) {
                    $q->where('semester_id', $semesterId);
                }
            });
        }

        $totalCount = (clone $query)->count();
        if ($totalCount === 0) {
            return 0;
        }

        $presentCount = $query->where('is_present', '=', true)->count();

        return (int) round(($presentCount / $totalCount) * 100);
    }
}
