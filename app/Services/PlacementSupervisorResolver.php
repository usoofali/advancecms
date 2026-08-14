<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Organization;
use App\Models\PlacementSupervisor;
use App\Models\StudentPlacement;
use Illuminate\Database\Eloquent\Collection;

class PlacementSupervisorResolver
{
    /**
     * Resolve the assigned supervisor for a single student placement.
     */
    public function resolveForPlacement(StudentPlacement $placement): ?PlacementSupervisor
    {
        // 1. Resolve Organization ID
        $orgId = $placement->organization_id;
        if (! $orgId && $placement->custom_organization_name) {
            $orgId = Organization::where('name', trim($placement->custom_organization_name))->value('id');
        }

        if (! $orgId) {
            return null;
        }

        // 2. Resolve Academic Session ID
        $sessionId = $placement->academic_session_id ?? null;
        if (! $sessionId && $placement->academic_session) {
            if (is_numeric($placement->academic_session)) {
                $sessionId = (int) $placement->academic_session;
            } else {
                $sessionId = AcademicSession::where('name', trim($placement->academic_session))->value('id');
            }
        }

        // 3. Resolve Student, Department, Program & Level
        $student = $placement->student;
        $deptId = $student?->department_id ?? $student?->program?->department_id;
        $programId = $student?->program_id;

        $rawLevel = (string) ($placement->level ?: ($student?->level ?: ($student?->entry_level ?: '')));
        $cleanLevel = preg_replace('/[^0-9]/', '', $rawLevel) ?: $rawLevel;

        // Fetch candidate supervisor rules for this organization
        $query = PlacementSupervisor::query()
            ->with(['supervisor', 'department', 'program', 'supervisable'])
            ->where('organization_id', $orgId);

        if ($sessionId) {
            $query->where(function ($q) use ($sessionId) {
                $q->where('academic_session_id', $sessionId)
                    ->orWhereNull('academic_session_id');
            });
        }

        $supervisors = $query->get();

        if ($supervisors->isEmpty()) {
            return null;
        }

        // Priority 1: Matches Dept, Program, and Level
        $match = $supervisors->first(function ($s) use ($deptId, $programId, $cleanLevel) {
            $supDeptId = $s->department_id ?: ($s->supervisable_type === 'App\Models\Department' ? $s->supervisable_id : null);
            $supProgramId = $s->program_id ?: ($s->supervisable_type === 'App\Models\Program' ? $s->supervisable_id : null);
            $supLevel = (string) ($s->level ?? '');
            $supCleanLevel = preg_replace('/[^0-9]/', '', $supLevel) ?: $supLevel;

            return $deptId && $supDeptId == $deptId
                && $programId && $supProgramId == $programId
                && ($cleanLevel && $supCleanLevel == $cleanLevel);
        });
        if ($match) {
            return $match;
        }

        // Priority 2: Matches Program and Level
        $match = $supervisors->first(function ($s) use ($programId, $cleanLevel) {
            $supProgramId = $s->program_id ?: ($s->supervisable_type === 'App\Models\Program' ? $s->supervisable_id : null);
            $supLevel = (string) ($s->level ?? '');
            $supCleanLevel = preg_replace('/[^0-9]/', '', $supLevel) ?: $supLevel;

            return $programId && $supProgramId == $programId && ($cleanLevel && $supCleanLevel == $cleanLevel);
        });
        if ($match) {
            return $match;
        }

        // Priority 3: Matches Department and Level
        $match = $supervisors->first(function ($s) use ($deptId, $cleanLevel) {
            $supDeptId = $s->department_id ?: ($s->supervisable_type === 'App\Models\Department' ? $s->supervisable_id : null);
            $supLevel = (string) ($s->level ?? '');
            $supCleanLevel = preg_replace('/[^0-9]/', '', $supLevel) ?: $supLevel;

            return $deptId && $supDeptId == $deptId && ($cleanLevel && $supCleanLevel == $cleanLevel);
        });
        if ($match) {
            return $match;
        }

        // Priority 4: Matches Department only
        $match = $supervisors->first(function ($s) use ($deptId) {
            $supDeptId = $s->department_id ?: ($s->supervisable_type === 'App\Models\Department' ? $s->supervisable_id : null);

            return $deptId && $supDeptId == $deptId;
        });
        if ($match) {
            return $match;
        }

        // Priority 5: Matches Program only
        $match = $supervisors->first(function ($s) use ($programId) {
            $supProgramId = $s->program_id ?: ($s->supervisable_type === 'App\Models\Program' ? $s->supervisable_id : null);

            return $programId && $supProgramId == $programId;
        });
        if ($match) {
            return $match;
        }

        // Priority 6: Matches Level only
        $match = $supervisors->first(function ($s) use ($cleanLevel) {
            $supLevel = (string) ($s->level ?? '');
            $supCleanLevel = preg_replace('/[^0-9]/', '', $supLevel) ?: $supLevel;

            return $cleanLevel && $supCleanLevel == $cleanLevel;
        });
        if ($match) {
            return $match;
        }

        // Priority 7: Organization-wide fallback supervisor
        return $supervisors->first();
    }

    /**
     * Get all StudentPlacements assigned to a given supervisor user.
     */
    public function getPlacementsForSupervisor(int $userId, ?int $sessionId = null): Collection
    {
        $supervisorRules = PlacementSupervisor::where('user_id', $userId)
            ->when($sessionId, fn ($q) => $q->where(fn ($sq) => $sq->where('academic_session_id', $sessionId)->orWhereNull('academic_session_id')))
            ->get();

        if ($supervisorRules->isEmpty()) {
            return new Collection;
        }

        $orgIds = $supervisorRules->pluck('organization_id')->filter()->unique();
        $orgNames = Organization::whereIn('id', $orgIds)->pluck('name')->filter()->unique();

        $allPlacements = StudentPlacement::query()
            ->with(['student.user', 'student.department', 'student.program', 'organization', 'evaluation'])
            ->where(function ($q) use ($orgIds, $orgNames) {
                $q->whereIn('organization_id', $orgIds);
                if ($orgNames->isNotEmpty()) {
                    $q->orWhereIn('custom_organization_name', $orgNames);
                }
            })
            ->get();

        return $allPlacements->filter(function ($placement) use ($userId) {
            $resolved = $this->resolveForPlacement($placement);

            return $resolved && $resolved->user_id == $userId;
        });
    }
}
