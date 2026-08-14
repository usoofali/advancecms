<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentPlacement extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'organization_id',
        'placement_type_id',
        'start_date',
        'end_date',
        'academic_session',
        'custom_organization_name',
        'custom_organization_address',
        'custom_organization_city',
        'custom_organization_state',
        'status',
        'workflow_stage',
        'admin_remarks',
        'approval_status',
        'assigned_by',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'assigned_at' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function placementType()
    {
        return $this->belongsTo(PlacementType::class);
    }

    public function evaluation()
    {
        return $this->hasOne(PlacementEvaluation::class, 'placement_id');
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function generatedDocuments()
    {
        return $this->hasMany(GeneratedDocument::class, 'placement_id');
    }

    public function placementDocuments()
    {
        return $this->hasMany(PlacementDocument::class, 'placement_id');
    }

    public function getOrganizationDisplayNameAttribute(): string
    {
        if ($this->organization) {
            return $this->organization->name;
        }

        return $this->custom_organization_name ?: 'Pending Organization Selection';
    }

    public function getOrganizationDisplayAddressAttribute(): string
    {
        if ($this->organization) {
            $parts = array_filter([$this->organization->address, $this->organization->city, $this->organization->state]);

            return implode(', ', $parts);
        }

        $parts = array_filter([$this->custom_organization_address, $this->custom_organization_city, $this->custom_organization_state]);

        return ! empty($parts) ? implode(', ', $parts) : 'Address not specified';
    }

    public static function hasOverlap(int $studentId, string $startDate, string $endDate, ?int $excludePlacementId = null): bool
    {
        return static::where('student_id', $studentId)
            ->when($excludePlacementId, fn ($q) => $q->where('id', '!=', $excludePlacementId))
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->where('status', '!=', 'Cancelled')
            ->exists();
    }

    public function scopeFilterByLevel($query, int $level, ?AcademicSession $activeSession = null)
    {
        return $query->whereHas('student', function ($sq) use ($level, $activeSession) {
            if ($activeSession) {
                return $sq->whereRaw("entry_level + (CAST(SUBSTRING_INDEX(?, '/', 1) AS SIGNED) - CAST(admission_year AS SIGNED)) * 100 = ?", [
                    $activeSession->name,
                    $level,
                ]);
            }

            return $sq->where('entry_level', $level);
        });
    }
}
