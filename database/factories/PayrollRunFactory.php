<?php

namespace Database\Factories;

use App\Models\Institution;
use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'title' => date('F Y').' Payroll',
            'month' => (int) date('n'),
            'year' => (int) date('Y'),
            'status' => 'draft',
            'total_gross' => 0,
            'total_deductions' => 0,
            'total_net' => 0,
        ];
    }
}
