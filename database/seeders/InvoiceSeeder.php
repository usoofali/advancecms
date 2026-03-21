<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Institution;
use App\Models\Invoice;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $institution = Institution::first() ?? Institution::factory()->create();
        $session = AcademicSession::first() ?? AcademicSession::factory()->create();
        $user = User::first() ?? User::factory()->create();

        // Create a Published Invoice with 3 items
        $invoice = Invoice::create([
            'institution_id' => $institution->id,
            'title' => 'ND1 Registration Fees 2026',
            'academic_session_id' => $session->id,
            'due_date' => now()->addMonth(),
            'target_type' => 'program',
            'program_id' => Program::first()?->id ?? Program::factory()->create(['institution_id' => $institution->id])->id,
            'level' => '100',
            'status' => 'published',
            'created_by' => $user->id,
        ]);

        $invoice->items()->createMany([
            ['item_name' => 'Tuition Fee', 'amount' => 50000],
            ['item_name' => 'Registration Fee', 'amount' => 5000],
            ['item_name' => 'Library Fee', 'amount' => 3000],
        ]);
    }
}
