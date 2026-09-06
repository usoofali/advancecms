<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /**
     * Log a system activity event.
     *
     * @param  array<string, mixed>|null  $properties
     */
    public static function log(
        string $action,
        string $module,
        string $description,
        ?Model $subject = null,
        ?string $subjectLabel = null,
        ?array $properties = null,
        ?User $user = null,
        ?int $institutionId = null
    ): ?ActivityLog {
        try {
            $currentUser = $user ?? auth()->user();
            $resolvedInstitutionId = $institutionId
                ?? $currentUser?->institution_id
                ?? ($subject && isset($subject->institution_id) ? $subject->institution_id : null);

            $label = $subjectLabel;
            if (! $label && $subject) {
                if (method_exists($subject, 'getActivityLogLabel')) {
                    $label = $subject->getActivityLogLabel();
                } elseif (isset($subject->title)) {
                    $label = (string) $subject->title;
                } elseif (isset($subject->name)) {
                    $label = (string) $subject->name;
                } elseif (isset($subject->code)) {
                    $label = (string) $subject->code;
                } else {
                    $label = class_basename($subject).' #'.$subject->getKey();
                }
            }

            return ActivityLog::create([
                'institution_id' => $resolvedInstitutionId,
                'user_id' => $currentUser?->id,
                'subject_type' => $subject ? $subject->getMorphClass() : null,
                'subject_id' => $subject?->getKey(),
                'subject_label' => $label,
                'action' => strtolower($action),
                'module' => $module,
                'description' => $description,
                'properties' => $properties,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Silently fail or log to Laravel error log so audit logging never crashes main business actions
            logger()->error('Failed to write activity log: '.$e->getMessage(), [
                'action' => $action,
                'module' => $module,
                'description' => $description,
            ]);

            return null;
        }
    }
}
