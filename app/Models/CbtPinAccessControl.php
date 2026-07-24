<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CbtPinAccessControl extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'academic_session_id',
        'semester_id',
        'program_id',
        'is_unlocked',
        'unlocked_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_unlocked' => 'boolean',
            'unlocked_at' => 'datetime',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Check whether CBT PIN access is unlocked for a given session, semester, and optional program.
     * Defaults to false (locked) if no record exists.
     */
    public static function isUnlocked(int $institutionId, int $sessionId, int $semesterId, ?int $programId = null): bool
    {
        if ($programId) {
            $progControl = static::where('institution_id', $institutionId)
                ->where('academic_session_id', $sessionId)
                ->where('semester_id', $semesterId)
                ->where('program_id', $programId)
                ->first();

            if ($progControl !== null) {
                return (bool) $progControl->is_unlocked;
            }
        }

        $globalControl = static::where('institution_id', $institutionId)
            ->where('academic_session_id', $sessionId)
            ->where('semester_id', $semesterId)
            ->whereNull('program_id')
            ->first();

        return $globalControl ? (bool) $globalControl->is_unlocked : false;
    }
}
