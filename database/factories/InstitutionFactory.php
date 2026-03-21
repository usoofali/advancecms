<?php

namespace Database\Factories;

use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Institution>
 */
class InstitutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word().' Institution',
            'acronym' => strtoupper($this->faker->lexify('???')),
            'address' => $this->faker->address(),
            'email' => $this->faker->email(),
            'phone' => $this->faker->phoneNumber(),
            'established_year' => $this->faker->year(),
            'status' => 'active',
        ];
    }
}
