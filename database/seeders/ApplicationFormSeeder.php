<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\ApplicationForm;
use App\Models\Institution;
use Illuminate\Database\Seeder;

class ApplicationFormSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $institutions = Institution::all();
        $session = AcademicSession::first();

        if ($institutions->isEmpty() || ! $session) {
            $this->command->warn('No institutions or active sessions found. Skipping ApplicationForm seeder.');

            return;
        }

        foreach ($institutions as $institution) {
            ApplicationForm::firstOrCreate([
                'institution_id' => $institution->id,
                'name' => 'Undergraduate Application Form',
                'amount' => 10000.00,
                'academic_session_id' => $session->id,
                'is_active' => true,
            ]);

            ApplicationForm::firstOrCreate([
                'institution_id' => $institution->id,
                'name' => 'Postgraduate Application Form',
                'amount' => 20000.00,
                'academic_session_id' => $session->id,
                'is_active' => true,
            ]);
        }
    }
}
