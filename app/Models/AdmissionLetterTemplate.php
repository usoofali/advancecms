<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionLetterTemplate extends Model
{
    use HasFactory;

    public const TYPE_NOTIFICATION = 'notification';

    public const TYPE_OFFER = 'offer';

    protected $fillable = [
        'institution_id',
        'type',
        'letter_title',
        'salutation',
        'opening_text',
        'body_text',
        'conditions_text',
        'closing_text',
        'signatory_title',
        'signatory_subtitle',
        'show_qr_code',
        'logo_position',
        'qr_position',
    ];

    protected function casts(): array
    {
        return [
            'show_qr_code' => 'boolean',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getDefaults(string $type, ?Institution $institution = null): array
    {
        $instName = $institution?->name ?? '{institution_name}';

        if ($type === self::TYPE_OFFER) {
            return [
                'letter_title' => 'OFFER OF PROVISIONAL ADMISSION',
                'salutation' => 'Dear {applicant_name},',
                'opening_text' => 'I am pleased to inform you that following your application and subsequent review of your credentials, the Management of '.$instName.' has offered you Provisional Admission for the {academic_session} Academic Session, to pursue a course of study in:',
                'body_text' => 'Your designated Matriculation Number is {matric_number}. Please quote this number in all your future correspondence with the institution. You are expected to log in to the student portal and complete your course registration promptly.',
                'conditions_text' => 'This offer is provisional and subject to verification of your submitted academic credentials. Any discrepancy discovered during the screening exercise will automatically invalidate this admission. You are required to present all original certificates for verification during registration.',
                'closing_text' => 'Accept our warm congratulations and best wishes as you commence this new academic journey.',
                'signatory_title' => 'Registrar',
                'signatory_subtitle' => $instName,
                'show_qr_code' => true,
                'logo_position' => 'left',
                'qr_position' => 'header_right',
            ];
        }

        return [
            'letter_title' => 'NOTIFICATION OF PROVISIONAL ADMISSION',
            'salutation' => 'Dear {applicant_name},',
            'opening_text' => 'I am pleased to inform you that following your application and subsequent review of your credentials, the Management of '.$instName.' has offered you Provisional Admission for the {academic_session} Academic Session, to pursue a course of study in:',
            'body_text' => 'To formally accept this offer and generate your official Matriculation Number, you are required to pay a minimum of 50% of your total Admission Fees via your Applicant Portal. Your Matriculation Number will be issued upon successful enrollment.',
            'conditions_text' => 'This offer is provisional and subject to verification of your submitted academic credentials. Any discrepancy discovered during the screening exercise will automatically invalidate this admission. You are required to present all original certificates for verification during registration.',
            'closing_text' => 'Accept our warm congratulations and best wishes as you commence this new academic journey.',
            'signatory_title' => 'Registrar',
            'signatory_subtitle' => $instName,
            'show_qr_code' => true,
            'logo_position' => 'left',
            'qr_position' => 'header_right',
        ];
    }

    /**
     * Retrieve the institution template or default instance.
     */
    public static function forInstitution(int|Institution $institution, string $type): self
    {
        $institutionId = $institution instanceof Institution ? $institution->id : $institution;
        $instModel = $institution instanceof Institution ? $institution : Institution::find($institutionId);

        $record = static::where('institution_id', $institutionId)
            ->where('type', $type)
            ->first();

        if (! $record) {
            $record = new static([
                'institution_id' => $institutionId,
                'type' => $type,
                ...static::getDefaults($type, $instModel),
            ]);
        }

        return $record;
    }
}
