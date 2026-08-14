<?php

namespace App\Actions\Placements;

use App\Models\Department;
use App\Models\Organization;
use App\Models\PlacementSupervisor;
use App\Models\Program;

class AssignPlacementSupervisorAction
{
    public function execute(
        int $institutionId,
        int $sessionId,
        int $organizationId,
        int $userId,
        int $assignedBy,
        ?int $departmentId = null,
        ?int $programId = null,
        ?string $level = null,
        ?string $notes = null
    ): PlacementSupervisor {
        $supervisableType = $programId ? Program::class : ($departmentId ? Department::class : Organization::class);
        $supervisableId = $programId ?? ($departmentId ?? $organizationId);

        return PlacementSupervisor::updateOrCreate(
            [
                'institution_id' => $institutionId,
                'academic_session_id' => $sessionId,
                'organization_id' => $organizationId,
                'department_id' => $departmentId,
                'program_id' => $programId,
                'level' => $level,
            ],
            [
                'supervisable_type' => $supervisableType,
                'supervisable_id' => $supervisableId,
                'user_id' => $userId,
                'assigned_by' => $assignedBy,
                'notes' => $notes,
            ]
        );
    }
}
