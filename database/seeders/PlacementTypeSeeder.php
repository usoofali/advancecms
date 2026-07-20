<?php

namespace Database\Seeders;

use App\Models\PlacementType;
use Illuminate\Database\Seeder;

class PlacementTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'SIWES',
                'description' => 'Students Industrial Work Experience Scheme for practical field exposure.',
            ],
            [
                'name' => 'Clinical Posting',
                'description' => 'Hospital and clinical practice postings for health and medical students.',
            ],
            [
                'name' => 'Teaching Practice',
                'description' => 'Mandatory teaching exercise for education students in primary/secondary schools.',
            ],
            [
                'name' => 'Industrial Training (IT)',
                'description' => 'Industrial attachment for engineering, technical, and applied sciences students.',
            ],
            [
                'name' => 'Internship',
                'description' => 'General professional internship and corporate attachment program.',
            ],
        ];

        foreach ($types as $type) {
            PlacementType::firstOrCreate(
                ['name' => $type['name']],
                ['description' => $type['description'], 'is_active' => true]
            );
        }
    }
}
