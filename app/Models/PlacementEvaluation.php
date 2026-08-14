<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlacementEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'placement_id',
        'student_id',
        'supervisor_id',
        'academic_session_id',
        'punctuality_rating',
        'attendance_rating',
        'conduct_discipline_rating',
        'technical_skills_rating',
        'logbook_maintenance_rating',
        'total_score',
        'performance_grade',
        'supervisor_remarks',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'evaluated_at' => 'datetime',
            'punctuality_rating' => 'integer',
            'attendance_rating' => 'integer',
            'conduct_discipline_rating' => 'integer',
            'technical_skills_rating' => 'integer',
            'logbook_maintenance_rating' => 'integer',
            'total_score' => 'float',
        ];
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(StudentPlacement::class, 'placement_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }
}
