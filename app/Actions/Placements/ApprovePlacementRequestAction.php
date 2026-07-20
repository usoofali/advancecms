<?php

namespace App\Actions\Placements;

use App\Enums\PlacementApprovalStatus;
use App\Enums\PlacementWorkflowStage;
use App\Models\DocumentTemplate;
use App\Models\StudentPlacement;
use App\Services\DocumentGenerationService;

class ApprovePlacementRequestAction
{
    public function __construct(
        protected DocumentGenerationService $generator
    ) {}

    public function execute(StudentPlacement $placement): void
    {
        $placement->update([
            'workflow_stage' => PlacementWorkflowStage::REQUEST_APPROVED->value,
            'approval_status' => PlacementApprovalStatus::ACADEMIC_APPROVED->value,
        ]);

        $reqTemplate = DocumentTemplate::where('type', 'Hospital')->where('active', true)->first()
            ?? DocumentTemplate::where('active', true)->first();

        if ($reqTemplate) {
            $this->generator->generateRecord($placement, $reqTemplate, 'request');
        }

        $accTemplate = DocumentTemplate::where('type', 'Acceptance Form')->where('active', true)->first();
        if ($accTemplate) {
            $this->generator->generateRecord($placement, $accTemplate, 'acceptance_form');
        }
    }
}
