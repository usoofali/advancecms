<?php

namespace App\Actions\Placements;

use App\Enums\PlacementStatus;
use App\Enums\PlacementWorkflowStage;
use App\Models\StudentPlacement;

class RejectPlacementAction
{
    public function execute(StudentPlacement $placement, string $rejectionType, string $rejectionReason): void
    {
        if ($rejectionType === 'organization') {
            $placement->update([
                'workflow_stage' => PlacementWorkflowStage::PENDING_SELECTION->value,
                'status' => PlacementStatus::PENDING->value,
                'organization_id' => null,
                'custom_organization_name' => null,
                'custom_organization_address' => null,
                'custom_organization_city' => null,
                'custom_organization_state' => null,
                'admin_remarks' => $rejectionReason,
            ]);
        } elseif ($rejectionType === 'acceptance') {
            $placement->update([
                'workflow_stage' => PlacementWorkflowStage::REQUEST_APPROVED->value,
                'status' => PlacementStatus::ASSIGNED->value,
                'admin_remarks' => $rejectionReason,
            ]);
            $placement->placementDocuments()->delete();
        }
    }
}
