<?php

namespace App\Imports;

use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StaffImport
{
    /** @var array<int, string> */
    public array $failures = [];

    public int $imported = 0;

    public int|string $institutionId;

    public function __construct(int|string $institutionId)
    {
        $this->institutionId = $institutionId;
    }

    /**
     * Import staff from an uploaded file path (CSV or Excel).
     */
    public function import(string $filePath): void
    {
        @set_time_limit(300);

        try {
            try {
                $spreadsheet = IOFactory::load($filePath);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, true, false);
            } catch (\Throwable $e) {
                Log::error("Staff File Import Load Error: {$e->getMessage()}", ['exception' => $e]);
                $this->failures[] = 'Could not open or parse file. Please upload a valid CSV or Excel file.';

                return;
            }

            if (empty($rows) || count($rows) < 1) {
                $this->failures[] = 'The uploaded file is empty.';

                return;
            }

            $headerRow = array_shift($rows);
            $headings = array_map(fn ($h) => strtolower(trim((string) $h)), $headerRow);
            $rowNumber = 1;

            foreach ($rows as $rawValues) {
                $rowNumber++;

                if (empty(array_filter($rawValues, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                    continue;
                }

                if (count($rawValues) < count($headings)) {
                    $rawValues = array_pad($rawValues, count($headings), null);
                }

                $row = [];
                foreach ($headings as $index => $heading) {
                    $row[$heading] = $rawValues[$index] !== null ? trim((string) $rawValues[$index]) : '';
                }

                $missing = [];
                foreach (['first_name', 'last_name', 'email', 'designation', 'role_name'] as $field) {
                    if (empty($row[$field])) {
                        $missing[] = $field;
                    }
                }

                if (! empty($missing)) {
                    $this->failures[] = "Row {$rowNumber}: Missing required fields: ".implode(', ', $missing);

                    continue;
                }

                $email = strtolower($row['email']);
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->failures[] = "Row {$rowNumber}: Invalid email address '{$row['email']}'.";

                    continue;
                }

                if (Staff::where('email', $email)->exists() || User::where('email', $email)->exists()) {
                    $this->failures[] = "Row {$rowNumber}: Email '{$row['email']}' is already in use.";

                    continue;
                }

                $role = Role::query()
                    ->whereRaw('LOWER(role_name) = ?', [strtolower($row['role_name'])])
                    ->whereNotIn('role_name', ['Super Admin', 'Student'])
                    ->first();

                if (! $role) {
                    $this->failures[] = "Row {$rowNumber}: Role '{$row['role_name']}' not found or cannot be assigned.";

                    continue;
                }

                try {
                    DB::transaction(function () use ($row, $email, $role): void {
                        Staff::create([
                            'institution_id' => $this->institutionId,
                            'role_id' => $role->role_id,
                            'first_name' => $row['first_name'],
                            'last_name' => $row['last_name'],
                            'email' => $email,
                            'designation' => $row['designation'],
                            'status' => 'active',
                        ]);
                    });

                    $this->imported++;
                } catch (\Throwable $e) {
                    Log::error("Staff Import Row {$rowNumber} Error: {$e->getMessage()}");
                    $this->failures[] = "Row {$rowNumber}: Failed to save ({$e->getMessage()}).";
                }
            }
        } catch (\Throwable $e) {
            Log::error("Staff Import Fatal Error: {$e->getMessage()}");
            $this->failures[] = 'Import aborted: '.$e->getMessage();
        }
    }
}
