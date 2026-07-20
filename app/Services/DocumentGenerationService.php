<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\StudentPlacement;

class DocumentGenerationService
{
    protected DocumentNumberService $numberService;

    public function __construct(DocumentNumberService $numberService)
    {
        $this->numberService = $numberService;
    }

    /**
     * Creates or updates the GeneratedDocument record in the database.
     */
    public function generateRecord(StudentPlacement $placement, DocumentTemplate $template, string $purpose = 'request', ?string $batchGroupId = null): GeneratedDocument
    {
        $existingDoc = GeneratedDocument::where('placement_id', $placement->id)
            ->where('purpose', $purpose)
            ->first();

        $documentNumber = $existingDoc ? $existingDoc->document_number : $this->numberService->generate($placement);

        return GeneratedDocument::updateOrCreate(
            [
                'placement_id' => $placement->id,
                'purpose' => $purpose,
            ],
            [
                'student_id' => $placement->student_id,
                'template_id' => $template->id,
                'document_number' => $documentNumber,
                'pdf_path' => '',
                'batch_group_id' => $batchGroupId,
                'generated_by' => auth()->id(),
                'generated_at' => now(),
            ]
        );
    }

    /**
     * Parses the template placeholders for a specific document.
     */
    public function parseContent(GeneratedDocument $document): string
    {
        $placement = $document->placement;
        $student = $placement->student;
        $template = $document->template;

        $departmentName = 'Department';
        if ($student->program && $student->program->department) {
            $departmentName = $student->program->department->name;
        }

        $groupTableHtml = '';
        if ($document->batch_group_id) {
            $groupDocs = GeneratedDocument::with(['placement.student.program'])
                ->where('batch_group_id', $document->batch_group_id)
                ->where('purpose', 'group_cover')
                ->get()
                ->sortBy(fn ($doc) => $doc->placement->student->matric_number ?? $doc->placement->student->first_name);

            $rows = '';
            $index = 1;
            foreach ($groupDocs as $gDoc) {
                $gStudent = $gDoc->placement->student;
                $gProg = $gStudent->program ? $gStudent->program->name : 'N/A';
                $rows .= <<<ROW
<tr class="border-b border-zinc-200">
    <td class="py-2 px-3 text-center">{$index}</td>
    <td class="py-2 px-3 font-semibold">{$gStudent->full_name}</td>
    <td class="py-2 px-3 font-mono">{$gStudent->matric_number}</td>
    <td class="py-2 px-3">{$gProg}</td>
</tr>
ROW;
                $index++;
            }

            $groupTableHtml = <<<TABLE
<table class="w-full text-left border-collapse border border-zinc-300 text-[9.5pt]">
    <thead>
        <tr class="bg-[#1a3c6b] text-white">
            <th class="py-2 px-3 text-center w-12">S/N</th>
            <th class="py-2 px-3">Student Name</th>
            <th class="py-2 px-3">Matric Number</th>
            <th class="py-2 px-3">Program / Course</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
    </tbody>
</table>
TABLE;
        }

        $placeholders = [
            '{template_title}' => $template->title,
            '{student_name}' => $student->full_name,
            '{matric_number}' => $student->matric_number,
            '{department}' => $departmentName,
            '{organization_name}' => $placement->organization_display_name,
            '{organization_address}' => $placement->organization_display_address,
            '{start_date}' => $placement->start_date->format('jS F, Y'),
            '{end_date}' => $placement->end_date->format('jS F, Y'),
            '{academic_session}' => $placement->academic_session,
            '{document_number}' => $document->document_number,
            '{group_table}' => $groupTableHtml,
        ];

        $content = $template->template_content;

        foreach ($placeholders as $key => $value) {
            $content = str_ireplace($key, $value ?? '', $content);
        }

        return $content;
    }
}
