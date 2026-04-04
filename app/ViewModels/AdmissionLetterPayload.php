<?php

namespace App\ViewModels;

use App\Models\Applicant;
use App\Models\Institution;
use App\Models\Student;
use Carbon\CarbonInterface;

/**
 * Normalized props for the admission letter print sheet (applicant portal or CMS student profile).
 *
 * @phpstan-type LetterArray array{
 *     institution_name: string,
 *     institution_logo_path: string|null,
 *     institution_address: string|null,
 *     institution_email: string|null,
 *     institution_phone: string|null,
 *     letter_title: string,
 *     ref: string,
 *     letter_date_formatted: string,
 *     addressee_full_name: string,
 *     academic_session_label: string,
 *     program_name: string,
 *     program_meta_line: string,
 *     is_enrolled: bool,
 *     matric_number: string|null,
 *     show_fee_paragraph: bool,
 *     details_name_label: string,
 *     details_name_value: string,
 *     application_number: string|null,
 *     email: string|null,
 *     phone: string|null,
 *     session_row_value: string,
 *     qr_data: string,
 *     back_url: string,
 *     back_label: string,
 * }
 */
final class AdmissionLetterPayload
{
    /**
     * Academic session label for enrolled students: "{admission_year}/{admission_year + 1}".
     */
    public static function academicSessionFromAdmissionYear(int $admissionYear): string
    {
        $next = $admissionYear + 1;

        return "{$admissionYear}/{$next}";
    }

    /**
     * @return LetterArray
     */
    public static function fromApplicant(Applicant $applicant, ?Student $student): array
    {
        $applicant->loadMissing(['institution', 'program', 'applicationForm.academicSession']);

        $institution = $applicant->institution;
        $isOffer = (bool) ($applicant->enrolled_at && $student);
        $letterTitle = $isOffer ? 'OFFER OF PROVISIONAL ADMISSION' : 'NOTIFICATION OF PROVISIONAL ADMISSION';
        $ref = $isOffer && $student
            ? $student->matric_number
            : 'PENDING/ENROLL/'.$applicant->application_number;

        $sessionName = $applicant->applicationForm?->academicSession?->name ?? '—';

        $qrData = implode("\n", array_filter([
            $letterTitle,
            'Ref: '.$ref,
            'Applicant: '.$applicant->full_name,
            'Program: '.$applicant->program->name,
            'Session: '.$sessionName,
            'Institution: '.$institution->name,
            'Date: '.$applicant->updated_at->format('d/m/Y'),
        ]));

        $backUrl = auth()->check() && auth()->user()->can('view_applications')
            ? route('cms.admissions.show', $applicant)
            : route('applicant.portal', ['application_number' => $applicant->application_number]);

        return self::build(
            institution: $institution,
            letterTitle: $letterTitle,
            ref: $ref,
            letterDate: $applicant->updated_at,
            addresseeFullName: $applicant->full_name,
            academicSessionLabel: $sessionName,
            programName: $applicant->program->name,
            programMetaLine: 'Entry Level: 100L &nbsp;|&nbsp; Mode: Full-time &nbsp;|&nbsp; Award: Certificate',
            isEnrolled: $isOffer,
            matricNumber: $student?->matric_number,
            showFeeParagraph: ! $isOffer,
            detailsNameLabel: __('Applicant Name'),
            detailsNameValue: strtoupper($applicant->full_name),
            applicationNumber: $applicant->application_number,
            email: $applicant->email,
            phone: $applicant->phone,
            sessionRowValue: $sessionName,
            qrData: $qrData,
            backUrl: $backUrl,
            backLabel: '← '.__('Back to Portal'),
        );
    }

    /**
     * @return LetterArray
     */
    public static function fromStudent(Student $student): array
    {
        $student->loadMissing(['institution', 'program']);

        $institution = $student->institution;
        $letterTitle = 'OFFER OF PROVISIONAL ADMISSION';
        $ref = $student->matric_number;
        $sessionLabel = self::academicSessionFromAdmissionYear((int) $student->admission_year);

        $awardLabel = match ($student->program->award_type ?? 'diploma') {
            'degree' => 'Degree',
            'diploma' => 'Diploma',
            'certificate' => 'Certificate',
            default => 'Certificate',
        };

        $programMetaLine = 'Entry Level: '.(int) $student->entry_level.'L &nbsp;|&nbsp; Mode: Full-time &nbsp;|&nbsp; Award: '.$awardLabel;

        $qrData = implode("\n", array_filter([
            $letterTitle,
            'Ref: '.$ref,
            'Student: '.$student->full_name,
            'Program: '.$student->program->name,
            'Session: '.$sessionLabel,
            'Institution: '.$institution->name,
            'Date: '.now()->format('d/m/Y'),
        ]));

        return self::build(
            institution: $institution,
            letterTitle: $letterTitle,
            ref: $ref,
            letterDate: now(),
            addresseeFullName: $student->full_name,
            academicSessionLabel: $sessionLabel,
            programName: $student->program->name,
            programMetaLine: $programMetaLine,
            isEnrolled: true,
            matricNumber: $student->matric_number,
            showFeeParagraph: false,
            detailsNameLabel: __('Student Name'),
            detailsNameValue: strtoupper($student->full_name),
            applicationNumber: null,
            email: $student->email,
            phone: $student->phone,
            sessionRowValue: $sessionLabel,
            qrData: $qrData,
            backUrl: route('cms.students.show', $student),
            backLabel: '← '.__('Back to student profile'),
        );
    }

    /**
     * @return LetterArray
     */
    private static function build(
        Institution $institution,
        string $letterTitle,
        string $ref,
        CarbonInterface $letterDate,
        string $addresseeFullName,
        string $academicSessionLabel,
        string $programName,
        string $programMetaLine,
        bool $isEnrolled,
        ?string $matricNumber,
        bool $showFeeParagraph,
        string $detailsNameLabel,
        string $detailsNameValue,
        ?string $applicationNumber,
        ?string $email,
        ?string $phone,
        string $sessionRowValue,
        string $qrData,
        string $backUrl,
        string $backLabel,
    ): array {
        return [
            'institution_name' => $institution->name,
            'institution_logo_path' => $institution->logo_path,
            'institution_address' => $institution->address,
            'institution_email' => $institution->email,
            'institution_phone' => $institution->phone,
            'letter_title' => $letterTitle,
            'ref' => $ref,
            'letter_date_formatted' => $letterDate->format('jS F, Y'),
            'addressee_full_name' => $addresseeFullName,
            'academic_session_label' => $academicSessionLabel,
            'program_name' => $programName,
            'program_meta_line' => $programMetaLine,
            'is_enrolled' => $isEnrolled,
            'matric_number' => $matricNumber,
            'show_fee_paragraph' => $showFeeParagraph,
            'details_name_label' => $detailsNameLabel,
            'details_name_value' => $detailsNameValue,
            'application_number' => $applicationNumber,
            'email' => $email,
            'phone' => $phone,
            'session_row_value' => $sessionRowValue,
            'qr_data' => $qrData,
            'back_url' => $backUrl,
            'back_label' => $backLabel,
        ];
    }
}
