<?php

namespace Database\Factories;

use App\Models\Institution;
use App\Models\PayrollCategory;
use App\Models\PayrollStaffProfile;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollStaffProfile>
 */
class PayrollStaffProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'staff_id' => Staff::factory(),
            'payroll_category_id' => PayrollCategory::factory(),
            'department_id' => null,
            'pay_type' => null,
            'contact_formula' => null,
            'base_salary' => $this->faker->numberBetween(60000, 350000),
            'custom_rate' => null,
            'status' => 'active',
        ];
    }
}
