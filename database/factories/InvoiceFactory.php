<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\Institution;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'title' => $this->faker->words(3, true).' Invoice',
            'academic_session_id' => AcademicSession::factory(),
            'due_date' => $this->faker->dateTimeBetween('now', '+1 month'),
            'target_type' => 'student',
            'status' => 'published',
            'created_by' => User::factory(),
        ];
    }
}
