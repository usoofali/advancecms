<?php

namespace App\Services;

use App\Models\GeneratedDocument;
use App\Models\StudentPlacement;

class DocumentNumberService
{
    /**
     * Generates a unique document number for a placement.
     * Format: [INST]/IT/YYYY/0001
     */
    public function generate(StudentPlacement $placement): string
    {
        $institution = $placement->student->institution;
        $instAcronym = $institution ? ($institution->acronym ?? 'CMS') : 'CMS';

        // Use the year of the start date
        $year = $placement->start_date->format('Y');

        $typeAcronym = strtoupper(substr($placement->placementType->name, 0, 2));
        if (str_contains(strtolower($placement->placementType->name), 'industrial')) {
            $typeAcronym = 'IT';
        } elseif (str_contains(strtolower($placement->placementType->name), 'siwes')) {
            $typeAcronym = 'SIWES';
        }

        $prefix = "{$instAcronym}/{$typeAcronym}/{$year}";

        $existingNumbers = GeneratedDocument::where('document_number', 'LIKE', "{$prefix}/%")
            ->pluck('document_number');

        $maxNumber = 0;
        foreach ($existingNumbers as $docNum) {
            $parts = explode('/', $docNum);
            $lastPart = end($parts);
            if (is_numeric($lastPart)) {
                $num = (int) $lastPart;
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }

        $nextNumber = $maxNumber + 1;

        while (GeneratedDocument::where('document_number', sprintf('%s/%04d', $prefix, $nextNumber))->exists()) {
            $nextNumber++;
        }

        return sprintf('%s/%04d', $prefix, $nextNumber);
    }
}
