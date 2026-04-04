<?php

namespace App\Services;

use App\Models\Result;
use Illuminate\Database\Eloquent\Builder;

class ResultsFilterService
{
    /**
     * @param  array{
     *     institution_id?: int|string|null,
     *     department_id?: int|string|null,
     *     program_id?: int|string|null,
     *     session_id?: int|string|null,
     *     level?: int|string|null,
     *     semester_id?: int|string|null,
     *     course_id?: int|string|null,
     *     search?: string|null,
     * }  $filters
     */
    public static function filterActive(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return (string) $value !== 'null';
    }

    /**
     * Apply hierarchical filters to a results query. Semester is optional for export legacy paths;
     * the index page gates loading before calling when semester is required.
     *
     * @param  array<string, mixed>  $filters
     */
    public static function applyToQuery(Builder $query, array $filters): Builder
    {
        if (self::filterActive($filters['institution_id'] ?? null)) {
            $query->where('institution_id', $filters['institution_id']);
        }

        if (self::filterActive($filters['department_id'] ?? null)) {
            $query->whereHas('course', fn ($cq) => $cq->where('department_id', $filters['department_id']));
        }

        if (self::filterActive($filters['program_id'] ?? null)) {
            $query->whereHas('course', fn ($cq) => $cq->where('program_id', $filters['program_id']));
        }

        if (self::filterActive($filters['session_id'] ?? null)) {
            $query->where('academic_session_id', $filters['session_id']);
        }

        if (self::filterActive($filters['level'] ?? null)) {
            $query->whereHas('course', fn ($cq) => $cq->where('level', (int) $filters['level']));
        }

        if (self::filterActive($filters['semester_id'] ?? null)) {
            $query->where('semester_id', $filters['semester_id']);
        }

        if (self::filterActive($filters['course_id'] ?? null)) {
            $query->where('course_id', $filters['course_id']);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($sq) use ($search) {
                $sq->whereHas('student', function ($ssq) use ($search) {
                    $ssq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('matric_number', 'like', "%{$search}%");
                })->orWhereHas('course', function ($cq) use ($search) {
                    $cq->where('course_code', 'like', "%{$search}%");
                });
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function newFilteredQuery(array $filters): Builder
    {
        return self::applyToQuery(Result::query(), $filters);
    }
}
