<?php

namespace App\Actions\Placements;

use App\Enums\PlacementApprovalStatus;
use App\Enums\PlacementStatus;
use App\Enums\PlacementWorkflowStage;
use App\Models\DocumentTemplate;
use App\Models\StudentPlacement;
use App\Services\DocumentGenerationService;

class VerifyPlacementAcceptanceAction
{
    public function __construct(
        protected DocumentGenerationService $generator
    ) {}

    public function execute(StudentPlacement $placement): void
    {
        $placement->update([
            'workflow_stage' => PlacementWorkflowStage::POSTING_ISSUED->value,
            'status' => PlacementStatus::ACCEPTED->value,
            'approval_status' => PlacementApprovalStatus::GENERATED->value,
        ]);

        $postTemplate = DocumentTemplate::where('type', 'Posting Letter')->where('active', true)->first()
            ?? DocumentTemplate::where('active', true)->first();

        if ($postTemplate) {
            $this->generator->generateRecord($placement, $postTemplate, 'posting');
        }
    }
}
