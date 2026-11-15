<?php

namespace Database\Factories;

use App\Models\Institution;
use App\Models\PayrollCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollCategory>
 */
class PayrollCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'name' => $this->faker->randomElement(['Permanent Staff', 'Casual Lecturer', 'Clinical Instructor']),
            'pay_type' => 'fixed',
            'contact_formula' => null,
            'default_rate' => 0,
            'is_default' => false,
            'status' => 'active',
        ];
    }
}
