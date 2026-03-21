<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationForm extends Model
{
    protected $fillable = [
        'institution_id',
        'program_id',
        'name',
        'amount',
        'academic_session_id',
        'is_active',
        'category',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(Applicant::class);
    }
}
