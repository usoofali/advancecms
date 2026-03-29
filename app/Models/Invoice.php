<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const CATEGORY_GENERAL = 'general';
    public const CATEGORY_ADMISSION = 'admission';
    public const CATEGORY_EXAM = 'exam_fee';
    public const CATEGORY_RESULT = 'result_fee';
    public const CATEGORY_REGISTRATION = 'registration';
    public const CATEGORY_INDEXING = 'indexing';
    public const CATEGORY_PRACTICAL = 'practical';
    public const CATEGORY_PROJECT = 'project';
    public const CATEGORY_REFRESHMENT = 'refreshment';
    public const CATEGORY_NATIONAL = 'national';
    public const CATEGORY_INDUCTION = 'induction';
    public const CATEGORY_CERTIFICATE = 'certificate';

    protected $fillable = [
        'institution_id',
        'title',
        'academic_session_id',
        'semester_id',
        'category',
        'due_date',
        'target_type',
        'department_id',
        'program_id',
        'level',
        'status',
        'is_required_for_results',
        'is_required_for_exams',
        'account_name',
        'account_number',
        'bank_name',
        'created_by',
    ];
                                                                                                                                                                                                                    
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function studentInvoices(): HasMany
    {
        return $this->hasMany(StudentInvoice::class);
    }

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'is_required_for_results' => 'boolean',
            'is_required_for_exams' => 'boolean',
        ];
    }

    public function getTotalAmountAttribute(): float|string
    {
        return $this->items()->sum('amount');
    }
}
