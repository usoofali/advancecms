<?php

namespace Database\Seeders;

use App\Models\GradingSystem;
use Illuminate\Database\Seeder;

class GradingSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        GradingSystem::firstOrCreate(
            ['name' => 'Standard 5.0 Scale'],
            [
                'scale' => [
                    ['min' => 70, 'grade' => 'A', 'point' => 5.0],
                    ['min' => 60, 'grade' => 'B', 'point' => 4.0],
                    ['min' => 50, 'grade' => 'C', 'point' => 3.0],
                    ['min' => 45, 'grade' => 'D', 'point' => 2.0],
                    ['min' => 40, 'grade' => 'E', 'point' => 1.0],
                    ['min' => 0,  'grade' => 'F', 'point' => 0.0],
                ],
            ]
        );

        GradingSystem::firstOrCreate(
            ['name' => 'Standard 4.0 Scale'],
            [
                'scale' => [
                    ['min' => 70, 'grade' => 'A', 'point' => 4.0],
                    ['min' => 60, 'grade' => 'B', 'point' => 3.0],
                    ['min' => 50, 'grade' => 'C', 'point' => 2.0],
                    ['min' => 45, 'grade' => 'D', 'point' => 1.0],
                    ['min' => 0,  'grade' => 'F', 'point' => 0.0],
                ],
            ]
        );
    }
}
