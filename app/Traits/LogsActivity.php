<?php

namespace App\Traits;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model) {
            $module = method_exists($model, 'getActivityModule') ? $model->getActivityModule() : class_basename($model);
            $label = method_exists($model, 'getActivityLogLabel') ? $model->getActivityLogLabel() : (string) ($model->title ?? $model->name ?? class_basename($model).' #'.$model->getKey());

            ActivityLogger::log(
                action: 'created',
                module: $module,
                description: "Created {$module}: {$label}",
                subject: $model,
                subjectLabel: $label,
                properties: ['attributes' => $model->getAttributes()]
            );
        });

        static::updated(function (Model $model) {
            $module = method_exists($model, 'getActivityModule') ? $model->getActivityModule() : class_basename($model);
            $label = method_exists($model, 'getActivityLogLabel') ? $model->getActivityLogLabel() : (string) ($model->title ?? $model->name ?? class_basename($model).' #'.$model->getKey());
            $changes = $model->getChanges();

            // Filter out timestamps and hidden fields
            unset($changes['updated_at']);

            if (empty($changes)) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $changes);

            ActivityLogger::log(
                action: 'updated',
                module: $module,
                description: "Updated {$module}: {$label}",
                subject: $model,
                subjectLabel: $label,
                properties: [
                    'old' => $original,
                    'new' => $changes,
                ]
            );
        });

        static::deleted(function (Model $model) {
            $module = method_exists($model, 'getActivityModule') ? $model->getActivityModule() : class_basename($model);
            $label = method_exists($model, 'getActivityLogLabel') ? $model->getActivityLogLabel() : (string) ($model->title ?? $model->name ?? class_basename($model).' #'.$model->getKey());

            ActivityLogger::log(
                action: 'deleted',
                module: $module,
                description: "Deleted {$module}: {$label}",
                subject: $model,
                subjectLabel: $label,
                properties: ['attributes' => $model->getAttributes()]
            );
        });
    }
}
