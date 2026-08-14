<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use Illuminate\Database\Seeder;

class DocumentTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Hospital' => [
                'title' => 'Clinical Posting Letter (Hospital)',
                'salutation' => 'The Chief Medical Director / Head of Administration,',
                'body' => 'This is to introduce the under-listed student of our institution to your esteemed Hospital for their Clinical Posting / Attachment.',
                'request' => 'We kindly request you to accept them and expose them to the practical and clinical aspects of healthcare delivery in your facility.',
            ],
            'Primary Health Centre' => [
                'title' => 'Community Health Attachment Letter',
                'salutation' => 'The Officer in Charge / Medical Officer,',
                'body' => 'This is to introduce the under-listed student of our institution to your Primary Health Centre for their Community Health Attachment.',
                'request' => 'We kindly request you to accept them and expose them to primary healthcare practices and community health outreach.',
            ],
            'Laboratory' => [
                'title' => 'Laboratory Attachment Letter',
                'salutation' => 'The Laboratory Manager / Chief Scientist,',
                'body' => 'This is to introduce the under-listed student of our institution to your esteemed Laboratory for their Industrial Attachment.',
                'request' => 'We kindly request you to accept them and expose them to the practical aspects of laboratory diagnostics, safety, and equipment handling.',
            ],
            'School' => [
                'title' => 'Teaching Practice Letter',
                'salutation' => 'The Principal / Head Teacher,',
                'body' => 'This is to introduce the under-listed student of our institution to your esteemed School for their Teaching Practice.',
                'request' => 'We kindly request you to accept them and provide them with the opportunity to develop their pedagogical skills under the guidance of your experienced staff.',
            ],
            'NGO' => [
                'title' => 'NGO Attachment / Volunteer Letter',
                'salutation' => 'The Executive Director / HR Manager,',
                'body' => 'This is to introduce the under-listed student of our institution to your Non-Governmental Organization for their Field Attachment.',
                'request' => 'We kindly request you to accept them and expose them to the practical aspects of your outreach, project management, and community interventions.',
            ],
            'Government Agency' => [
                'title' => 'Government Agency Attachment Letter',
                'salutation' => 'The Director / Head of Department,',
                'body' => 'This is to introduce the under-listed student of our institution to your esteemed Agency for their Industrial Attachment.',
                'request' => 'We kindly request you to accept them and expose them to public administration and the practical applications of their field within your parastatal.',
            ],
            'Private Company' => [
                'title' => 'Corporate Industrial Attachment Letter',
                'salutation' => 'The Managing Director / HR Manager,',
                'body' => 'This is to introduce the under-listed student of our institution to your esteemed Company for their SIWES / Industrial Attachment.',
                'request' => 'We kindly request you to accept them and expose them to the corporate and practical aspects of their field of study.',
            ],
            'Logistics Company' => [
                'title' => 'Logistics & Supply Chain Attachment Letter',
                'salutation' => 'The Operations Manager / HR Manager,',
                'body' => 'This is to introduce the under-listed student of our institution to your esteemed Logistics Company for their Industrial Attachment.',
                'request' => 'We kindly request you to accept them and expose them to the practical aspects of supply chain management, warehousing, and transportation.',
            ],
            'Manufacturing Company' => [
                'title' => 'Manufacturing Industrial Attachment Letter',
                'salutation' => 'The Plant Manager / HR Manager,',
                'body' => 'This is to introduce the under-listed student of our institution to your esteemed Manufacturing Plant for their Industrial Attachment.',
                'request' => 'We kindly request you to accept them and expose them to production processes, quality control, and industrial safety.',
            ],
            'Agricultural Organization' => [
                'title' => 'Agricultural Extension Attachment Letter',
                'salutation' => 'The Farm Manager / Director of Agriculture,',
                'body' => 'This is to introduce the under-listed student of our institution to your esteemed Organization for their Farm / Extension Attachment.',
                'request' => 'We kindly request you to accept them and expose them to practical agriculture, extension services, and farm management.',
            ],
            'Media Organization' => [
                'title' => 'Media & Broadcasting Attachment Letter',
                'salutation' => 'The General Manager / Editor-in-Chief,',
                'body' => 'This is to introduce the under-listed student of our institution to your esteemed Media Organization for their Industrial Attachment.',
                'request' => 'We kindly request you to accept them and expose them to the practical aspects of journalism, broadcasting, and media production.',
            ],
            'ICT Company' => [
                'title' => 'ICT / Tech Hub Attachment Letter',
                'salutation' => 'The Technical Director / HR Manager,',
                'body' => 'This is to introduce the under-listed student of our institution to your esteemed ICT Company for their Industrial Attachment.',
                'request' => 'We kindly request you to accept them and expose them to software development, networking, and modern technological practices.',
            ],
            'Other' => [
                'title' => 'Standard Industrial Attachment Letter',
                'salutation' => 'The Managing Director / Head of HR,',
                'body' => 'This is to introduce the under-listed student of our institution to your esteemed Organization for their Industrial Attachment.',
                'request' => 'We kindly request you to accept them and expose them to the practical aspects of their field of study.',
            ],
        ];

        foreach ($categories as $category => $data) {
            $html = <<<HTML
<div class="space-y-3 text-[10.5pt] leading-relaxed text-justify">
    <p class="font-semibold">{$data['salutation']}</p>
    <p class="font-bold text-[#1a3c6b]">{organization_name},</p>
    <p>{organization_address}</p>

    <div class="text-center py-3 my-4 border-b border-dashed border-zinc-300">
        <h2 class="text-[11.5pt] font-extrabold uppercase tracking-widest text-[#1a3c6b] underline underline-offset-2">
            {template_title}
        </h2>
    </div>

    <p>
        {$data['body']}
    </p>

    <div class="flex items-center gap-3 bg-[#f0f5ff] border-l-4 border-[#1a3c6b] px-4 py-3 my-3">
        <div class="flex-1">
            <p class="text-[11pt] font-extrabold text-[#1a3c6b] uppercase">{student_name}</p>
            <p class="text-[9pt] font-bold text-zinc-700">Matric No: {matric_number}</p>
            <p class="text-[8.5pt] text-zinc-500">Department of {department}</p>
        </div>
    </div>

    <p>
        The student is expected to commence their attachment from <strong>{start_date}</strong> to <strong>{end_date}</strong>. This practical training is a mandatory requirement for the award of their degree/diploma.
    </p>

    <p>
        {$data['request']} Please complete the attached acceptance form or reply officially to confirm their acceptance.
    </p>

    <p>
        Thank you for your continuous support and cooperation with our institution.
    </p>
</div>
HTML;

            DocumentTemplate::updateOrCreate(
                ['type' => $category],
                [
                    'title' => $data['title'],
                    'template_content' => $html,
                    'active' => true,
                ]
            );
        }

        // Standard System Acceptance Form
        $acceptanceHtml = <<<'HTML'
<div class="space-y-4 text-[10.5pt] leading-relaxed text-justify">
    <div class="text-center py-3 my-2 border-b-2 border-zinc-800">
        <h2 class="text-[13pt] font-extrabold uppercase tracking-widest text-[#1a3c6b]">
            STANDARD PLACEMENT ACCEPTANCE FORM
        </h2>
        <p class="text-[9pt] text-zinc-600">To be completed by the Host Organization and returned by the student</p>
    </div>

    <div class="bg-[#f0f5ff] border border-[#1a3c6b] p-4 rounded mb-4">
        <h3 class="font-bold text-[#1a3c6b] uppercase text-[10pt] mb-2 border-b border-[#1a3c6b]/30 pb-1">SECTION A: STUDENT & PLACEMENT DETAILS</h3>
        <p><strong>Student Name:</strong> {student_name}</p>
        <p><strong>Matric Number:</strong> {matric_number}</p>
        <p><strong>Department / Program:</strong> {department}</p>
        <p><strong>Assigned Organization:</strong> {organization_name}</p>
        <p><strong>Proposed Duration:</strong> {start_date} to {end_date}</p>
    </div>

    <div class="border border-zinc-400 p-4 rounded">
        <h3 class="font-bold text-zinc-800 uppercase text-[10pt] mb-3 border-b border-zinc-300 pb-1">SECTION B: HOST ORGANIZATION CONFIRMATION</h3>
        <p class="mb-4">
            We confirm that the student named above has been formally <strong>ACCEPTED</strong> to undergo practical posting / industrial attachment in our facility for the specified duration.
        </p>
        <div class="grid grid-cols-2 gap-6 mt-6 pt-4 border-t border-dashed border-zinc-300">
            <div>
                <p class="text-[9pt] font-semibold text-zinc-500">Contact / Supervisor Name:</p>
                <div class="border-b border-zinc-400 h-6 mt-1"></div>
            </div>
            <div>
                <p class="text-[9pt] font-semibold text-zinc-500">Designation / Rank:</p>
                <div class="border-b border-zinc-400 h-6 mt-1"></div>
            </div>
            <div>
                <p class="text-[9pt] font-semibold text-zinc-500">Phone Number:</p>
                <div class="border-b border-zinc-400 h-6 mt-1"></div>
            </div>
            <div>
                <p class="text-[9pt] font-semibold text-zinc-500">Date & Signature:</p>
                <div class="border-b border-zinc-400 h-6 mt-1"></div>
            </div>
        </div>
        <div class="mt-8 text-center border-t border-zinc-300 pt-6">
            <div class="w-48 h-24 border-2 border-dashed border-zinc-400 mx-auto flex items-center justify-center text-zinc-400 text-[8.5pt] font-semibold">
                OFFICIAL STAMP / SEAL
            </div>
        </div>
    </div>
</div>
HTML;

        DocumentTemplate::updateOrCreate(
            ['type' => 'Acceptance Form'],
            [
                'title' => 'Standard Placement Acceptance Form',
                'template_content' => $acceptanceHtml,
                'active' => true,
            ]
        );

        // Group Cover Letter
        $groupCoverHtml = <<<'HTML'
<div class="space-y-4 text-[10.5pt] leading-relaxed text-justify">
    <p class="font-semibold">The Chief Executive Officer / Head of Administration,</p>
    <p class="font-bold text-[#1a3c6b]">{organization_name},</p>
    <p>{organization_address}</p>

    <div class="text-center py-3 my-4 border-b border-dashed border-zinc-300">
        <h2 class="text-[12pt] font-extrabold uppercase tracking-widest text-[#1a3c6b] underline underline-offset-2">
            CONSOLIDATED REQUEST FOR PRACTICAL POSTING / INDUSTRIAL ATTACHMENT
        </h2>
    </div>

    <p>
        We write to officially introduce the under-listed students of our institution who have selected your esteemed facility for their mandatory practical posting / industrial attachment from <strong>{start_date}</strong> to <strong>{end_date}</strong>.
    </p>

    <div class="my-6 overflow-x-auto">
        {group_table}
    </div>

    <p>
        Attached herewith are their individual introduction letters and standard acceptance forms. We kindly request that you accord them the opportunity to gain practical industry experience in your organization.
    </p>

    <p>
        Thank you for your continuous support and cooperation with our institution.
    </p>
</div>
HTML;

        DocumentTemplate::updateOrCreate(
            ['type' => 'Group Cover'],
            [
                'title' => 'Consolidated Group Cover Request Letter',
                'template_content' => $groupCoverHtml,
                'active' => true,
            ]
        );

        // Official Posting Letter
        $postingHtml = <<<'HTML'
<div class="space-y-3 text-[10.5pt] leading-relaxed text-justify">
    <p class="font-semibold">The Chief Executive Officer / Head of Administration,</p>
    <p class="font-bold text-[#1a3c6b]">{organization_name},</p>
    <p>{organization_address}</p>

    <div class="text-center py-3 my-4 border-b border-dashed border-zinc-300">
        <h2 class="text-[11.5pt] font-extrabold uppercase tracking-widest text-[#1a3c6b] underline underline-offset-2">
            OFFICIAL PLACEMENT & POSTING AUTHORIZATION
        </h2>
    </div>

    <p>
        Further to your formal acceptance of our student for practical training, we hereby officially post and authorize the deployment of the student named below to your organization:
    </p>

    <div class="flex items-center gap-3 bg-[#f0f5ff] border-l-4 border-green-600 px-4 py-3 my-3">
        <div class="flex-1">
            <p class="text-[11pt] font-extrabold text-[#1a3c6b] uppercase">{student_name}</p>
            <p class="text-[9pt] font-bold text-zinc-700">Matric No: {matric_number}</p>
            <p class="text-[8.5pt] text-zinc-500">Department of {department}</p>
        </div>
    </div>

    <p>
        The student is deployed to commence training effective from <strong>{start_date}</strong> to <strong>{end_date}</strong>. During this period, they are subject to the rules, regulations, and discipline of your organization.
    </p>

    <p>
        We appreciate your partnership in training our students for professional excellence.
    </p>
</div>
HTML;

        DocumentTemplate::firstOrCreate(
            ['type' => 'Posting Letter'],
            [
                'title' => 'Official Placement & Posting Authorization Letter',
                'template_content' => $postingHtml,
                'active' => true,
            ]
        );
    }
}
