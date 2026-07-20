<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\IdCardRequest;
use App\Models\IdCardTemplate;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class IdCardPrintController extends Controller
{
    public function show(string $data): View
    {
        Gate::authorize('staff.view');

        $decoded = json_decode(base64_decode($data), true);
        $selectedIds = $decoded['ids'] ?? [];
        $type = $decoded['type'] ?? 'student';
        $mode = $decoded['mode'] ?? 'requests';

        if (empty($selectedIds)) {
            abort(404);
        }

        if ($mode === 'requests') {
            $records = IdCardRequest::whereIn('id', $selectedIds)
                ->with(['user.student.program.department', 'user.staff', 'institution'])
                ->get();
        } else {
            if ($type === 'student') {
                $records = Student::whereIn('id', $selectedIds)
                    ->with(['program.department.institution', 'user', 'institution'])
                    ->get();
            } else {
                $records = Staff::whereIn('id', $selectedIds)
                    ->with(['institution', 'user'])
                    ->get();
            }
        }

        $adminSignatures = [];

        $items = $records->map(function ($item) use ($mode, $type, &$adminSignatures) {
            if ($mode === 'requests') {
                $user = $item->user;
                $profile = ($type === 'student') ? $user?->student : $user?->staff;
                $institution = $item->institution;
            } else {
                $profile = $item;
                $user = $item->user;
                $institution = $item->institution ?? $item->program?->department?->institution;
            }

            $name = $user?->name ?? trim(($profile?->first_name ?? '').' '.($profile?->last_name ?? ''));
            $idNumber = ($type === 'student') ? ($profile?->matric_number ?? 'N/A') : ($profile?->staff_number ?? 'N/A');
            $photo = ($profile?->photo_path) ? asset('storage/'.$profile->photo_path) : null;
            $signatureUrl = ($profile?->signature_path) ? asset('storage/'.$profile->signature_path) : null;
            $phone = $profile?->phone ?? 'N/A';
            $email = ($type === 'staff') ? ($profile?->email ?? $user?->email) : ($user?->email ?? 'N/A');
            $dept = ($type === 'student') ? ($profile?->program?->name ?? 'N/A') : ($profile?->designation ?? 'N/A');
            $yearOfEntry = ($type === 'student') ? ($profile?->admission_year ?? 'N/A') : null;
            $qrData = route('id-cards.verify', ['idNumber' => $idNumber]);

            $authorizedSignatureUrl = null;
            if ($institution) {
                if (!array_key_exists($institution->id, $adminSignatures)) {
                    $adminStaff = Staff::where('institution_id', $institution->id)
                        ->where('role_id', 2)
                        ->whereNotNull('signature_path')
                        ->first();
                    $adminSignatures[$institution->id] = $adminStaff?->signature_path ? asset('storage/'.$adminStaff->signature_path) : null;
                }
                $authorizedSignatureUrl = $adminSignatures[$institution->id];
            }

            $template = IdCardTemplate::where('is_active', true)
                ->where(function ($query) use ($institution) {
                    $query->where('institution_id', $institution?->id)
                        ->orWhereNull('institution_id');
                })
                ->whereIn('type', [$type, 'both'])
                ->orderByRaw('institution_id IS NOT NULL DESC')
                ->first();

            $bloodGroup = ($type === 'student') ? ($profile?->blood_group ?? null) : null;
            $duration = ($type === 'student') ? ($profile?->program?->duration_years ?? null) : null;

            return [
                'id' => $item->id,
                'name' => strtoupper($name ?: 'UNKNOWN'),
                'idNumber' => $idNumber,
                'photo' => $photo,
                'signatureUrl' => $signatureUrl,
                'phone' => $phone,
                'email' => strtolower($email ?? 'N/A'),
                'dept' => strtoupper($dept ?: 'N/A'),
                'yearOfEntry' => $yearOfEntry,
                'bloodGroup' => $bloodGroup,
                'duration' => $duration,
                'authorizedSignatureUrl' => $authorizedSignatureUrl,
                'nextOfKinName' => $profile?->next_of_kin_name,
                'nextOfKinRelationship' => $profile?->next_of_kin_relationship,
                'nextOfKinPhone' => $profile?->next_of_kin_phone,
                'nextOfKinAddress' => $profile?->next_of_kin_address,
                'qrUrl' => 'https://api.qrserver.com/v1/create-qr-code/?size=60x60&data='.urlencode($qrData),
                'institution' => [
                    'name' => strtoupper($institution?->name ?? 'INSTITUTION'),
                    'logo_url' => $institution?->logo_url ?? null,
                    'address' => $institution?->address ?? __('No address set.'),
                    'phone' => $institution?->phone ?? 'N/A',
                    'email' => $institution?->email ?? 'N/A',
                ],
                'template' => $template ? [
                    'layout' => $template->layout,
                    'orientation' => $template->orientation,
                    'primary_color' => $template->primary_color,
                    'secondary_color' => $template->secondary_color,
                    'text_color' => $template->text_color,
                    'accent_color' => $template->accent_color,
                    'header_text' => $template->header_text,
                    'footer_text' => $template->footer_text,
                    'font_family' => $template->font_family,
                    'font_weight' => $template->font_weight,
                    'font_style' => $template->font_style,
                    'text_align' => $template->text_align,
                    'disclaimer_text' => $template->disclaimer_text,
                    'show_signature_line' => $template->show_signature_line,
                    'back_background_color' => $template->back_background_color,
                    'back_text_color' => $template->back_text_color,
                    'show_qr' => $template->show_qr,
                    'show_barcode' => $template->show_barcode,
                    'show_blood_group' => $template->show_blood_group,
                    'background_url' => $template->background_url,
                ] : null,
            ];
        })->values()->toArray();

        $payload = [
            'type' => $type,
            'mode' => $mode,
            'items' => $items,
        ];

        return view('pages.cms.id-cards.print-vue', [
            'payload' => $payload,
        ]);
    }
}
