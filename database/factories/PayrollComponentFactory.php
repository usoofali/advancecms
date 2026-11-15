<?php

namespace Database\Factories;

use App\Models\Institution;
use App\Models\PayrollComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollComponent>
 */
class PayrollComponentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'name' => $this->faker->words(2, true),
            'type' => 'allowance',
            'calculation_type' => 'fixed',
            'amount_or_rate' => $this->faker->numberBetween(5000, 30000),
            'applicable_type' => null,
            'applicable_id' => null,
            'is_active' => true,
        ];
    }
}
