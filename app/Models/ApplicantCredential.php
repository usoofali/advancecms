<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantCredential extends Model
{
    protected $fillable = [
        'applicant_id',
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
        'primary_document_path',
        'secondary_document_path',
        'retrainee_document_path',
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }
}
