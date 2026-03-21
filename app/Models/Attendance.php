<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'course_allocation_id',
        'date',
        'start_time',
        'end_time',
        'total_present',
        'total_absent',
        'status',
        'is_combined_child',
        'combined_group_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function courseAllocation(): BelongsTo
    {
        return $this->belongsTo(CourseAllocation::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
