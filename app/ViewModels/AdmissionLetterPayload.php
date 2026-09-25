<?php

namespace App\ViewModels;

use App\Models\AdmissionLetterTemplate;
use App\Models\Applicant;
use App\Models\Institution;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Normalized props for the admission letter print sheet (applicant portal or CMS student profile).
 *
 * @phpstan-type LetterArray array{
 *     institution_name: string,
 *     institution_meta: string|null,
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
 *     salutation: string,
 *     opening_text: string,
 *     body_text: string,
 *     conditions_text: string,
 *     closing_text: string,
 *     signatory_title: string,
 *     signatory_subtitle: string,
 *     show_qr_code: bool,
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

    public static function replacePlaceholders(?string $text, array $vars): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $map = [
            '{applicant_name}' => $vars['applicant_name'] ?? '',
            '{student_name}' => $vars['student_name'] ?? $vars['applicant_name'] ?? '',
            '{program_name}' => $vars['program_name'] ?? '',
            '{academic_session}' => $vars['academic_session'] ?? '',
            '{institution_name}' => $vars['institution_name'] ?? '',
            '{matric_number}' => $vars['matric_number'] ?? '—',
            '{application_number}' => $vars['application_number'] ?? '—',
            '{entry_level}' => $vars['entry_level'] ?? '',
            '{award_type}' => $vars['award_type'] ?? '',
            '{date}' => $vars['date'] ?? '',
            '{ref}' => $vars['ref'] ?? '',
        ];

        return strtr($text, $map);
    }

    /**
     * @return LetterArray
     */
    public static function fromApplicant(Applicant $applicant, ?Student $student): array
    {
        $applicant->loadMissing(['institution', 'program', 'applicationForm.academicSession']);

        $institution = $applicant->institution;
        $isOffer = (bool) ($applicant->enrolled_at && $student);
        $type = $isOffer ? AdmissionLetterTemplate::TYPE_OFFER : AdmissionLetterTemplate::TYPE_NOTIFICATION;
        $template = AdmissionLetterTemplate::forInstitution($institution, $type);

        $ref = $isOffer && $student
            ? $student->matric_number
            : 'PENDING/ENROLL/'.$applicant->application_number;

        $sessionName = $applicant->applicationForm?->academicSession?->name ?? '—';

        $vars = [
            'applicant_name' => strtoupper($applicant->full_name),
            'student_name' => strtoupper($applicant->full_name),
            'program_name' => $applicant->program->name,
            'academic_session' => $sessionName,
            'institution_name' => $institution->name,
            'matric_number' => $student?->matric_number ?? '—',
            'application_number' => $applicant->application_number,
            'entry_level' => '100L',
            'award_type' => 'Certificate',
            'date' => $applicant->updated_at->format('jS F, Y'),
            'ref' => $ref,
        ];

        $letterTitle = self::replacePlaceholders($template->letter_title, $vars);

        $qrData = implode("\n", array_filter([
            $letterTitle,
            'Ref: '.$ref,
            'Applicant: '.$applicant->full_name,
            'Program: '.$applicant->program->name,
            'Session: '.$sessionName,
            'Institution: '.$institution->name,
            'Date: '.$applicant->updated_at->format('d/m/Y'),
        ]));

        $backUrl = auth()->check() && auth()->user()->can('applications.view')
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
            template: $template,
            vars: $vars,
        );
    }

    /**
     * Walk-in / physical assessment letter — no application record in the system.
     *
     * @param  array{
     *     addressee_full_name: string,
     *     academic_session_label: string,
     *     program_name: string,
     *     entry_level: int,
     *     award_type: string,
     *     admission_year?: int|null,
     *     email?: string|null,
     *     phone?: string|null,
     * }  $details
     * @return LetterArray
     */
    public static function forImpromptuNotification(Institution $institution, array $details): array
    {
        $institution->loadMissing([]);

        $template = AdmissionLetterTemplate::forInstitution($institution, AdmissionLetterTemplate::TYPE_NOTIFICATION);
        $ref = 'IMPROMPTU/'.now()->format('Ymd').'-'.strtoupper(Str::random(8));

        $entryLevel = (int) $details['entry_level'];
        $awardLabel = self::awardLabelFromType((string) $details['award_type']);
        $programMetaLine = 'Entry Level: '.$entryLevel.'L &nbsp;|&nbsp; Mode: Full-time &nbsp;|&nbsp; Award: '.$awardLabel;

        $sessionLabel = (string) $details['academic_session_label'];
        $programName = (string) $details['program_name'];
        $name = (string) $details['addressee_full_name'];

        $vars = [
            'applicant_name' => strtoupper($name),
            'student_name' => strtoupper($name),
            'program_name' => $programName,
            'academic_session' => $sessionLabel,
            'institution_name' => $institution->name,
            'matric_number' => '—',
            'application_number' => '—',
            'entry_level' => $entryLevel.'L',
            'award_type' => $awardLabel,
            'date' => now()->format('jS F, Y'),
            'ref' => $ref,
        ];

        $letterTitle = self::replacePlaceholders($template->letter_title, $vars);

        $qrLines = [
            $letterTitle,
            'Ref: '.$ref,
            'Candidate: '.$name,
            'Program: '.$programName,
            'Session: '.$sessionLabel,
            'Institution: '.$institution->name,
            'Date: '.now()->format('d/m/Y'),
        ];

        if (! empty($details['admission_year'])) {
            $qrLines[] = 'Admission year: '.(int) $details['admission_year'];
        }

        $qrData = implode("\n", array_filter($qrLines));

        return self::build(
            institution: $institution,
            letterTitle: $letterTitle,
            ref: $ref,
            letterDate: now(),
            addresseeFullName: $name,
            academicSessionLabel: $sessionLabel,
            programName: $programName,
            programMetaLine: $programMetaLine,
            isEnrolled: false,
            matricNumber: null,
            showFeeParagraph: true,
            detailsNameLabel: __('Candidate Name'),
            detailsNameValue: strtoupper($name),
            applicationNumber: null,
            email: isset($details['email']) && $details['email'] !== '' ? (string) $details['email'] : null,
            phone: isset($details['phone']) && $details['phone'] !== '' ? (string) $details['phone'] : null,
            sessionRowValue: $sessionLabel,
            qrData: $qrData,
            backUrl: route('cms.admissions.issue-notification'),
            backLabel: '← '.__('Back to form'),
            template: $template,
            vars: $vars,
        );
    }

    /**
     * @return LetterArray
     */
    public static function fromStudent(Student $student): array
    {
        $student->loadMissing(['institution', 'program']);

        $institution = $student->institution;
        $template = AdmissionLetterTemplate::forInstitution($institution, AdmissionLetterTemplate::TYPE_OFFER);

        $ref = $student->matric_number;
        $sessionLabel = self::academicSessionFromAdmissionYear((int) $student->admission_year);

        $awardLabel = match ($student->program->award_type ?? 'diploma') {
            'degree' => 'Degree',
            'diploma' => 'Diploma',
            'certificate' => 'Certificate',
            default => 'Certificate',
        };

        $programMetaLine = 'Entry Level: '.(int) $student->entry_level.'L &nbsp;|&nbsp; Mode: Full-time &nbsp;|&nbsp; Award: '.$awardLabel;

        $vars = [
            'applicant_name' => strtoupper($student->full_name),
            'student_name' => strtoupper($student->full_name),
            'program_name' => $student->program->name,
            'academic_session' => $sessionLabel,
            'institution_name' => $institution->name,
            'matric_number' => $student->matric_number,
            'application_number' => '—',
            'entry_level' => (int) $student->entry_level.'L',
            'award_type' => $awardLabel,
            'date' => now()->format('jS F, Y'),
            'ref' => $ref,
        ];

        $letterTitle = self::replacePlaceholders($template->letter_title, $vars);

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
            template: $template,
            vars: $vars,
        );
    }

    private static function awardLabelFromType(string $awardType): string
    {
        return match ($awardType) {
            'degree' => 'Degree',
            'diploma' => 'Diploma',
            'certificate' => 'Certificate',
            default => 'Certificate',
        };
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
        AdmissionLetterTemplate $template,
        array $vars,
    ): array {
        return [
            'institution_name' => $institution->name,
            'institution_meta' => $institution->meta,
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
            'salutation' => self::replacePlaceholders($template->salutation, $vars),
            'opening_text' => self::replacePlaceholders($template->opening_text, $vars),
            'body_text' => self::replacePlaceholders($template->body_text, $vars),
            'conditions_text' => self::replacePlaceholders($template->conditions_text, $vars),
            'closing_text' => self::replacePlaceholders($template->closing_text, $vars),
            'signatory_title' => self::replacePlaceholders($template->signatory_title, $vars),
            'signatory_subtitle' => self::replacePlaceholders($template->signatory_subtitle, $vars),
            'show_qr_code' => $template->show_qr_code,
            'logo_position' => $template->logo_position ?? 'left',
            'qr_position' => $template->qr_position ?? 'header_right',
        ];
    }
}
