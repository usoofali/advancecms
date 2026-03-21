<?php

namespace App\Models;

use Database\Factories\AcademicSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicSession extends Model
{
    /** @use HasFactory<AcademicSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function semesters(): HasMany
    {
        return $this->hasMany(Semester::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }

    public function courseRegistrations(): HasMany
    {
        return $this->hasMany(CourseRegistration::class);
    }
}
