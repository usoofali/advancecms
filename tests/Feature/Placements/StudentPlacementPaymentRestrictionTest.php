<?php

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Invoice;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('locks placements portal and displays required invoice when placement fee is unpaid', function (): void {
    $institution = Institution::factory()->create();
    $session = AcademicSession::factory()->create(['status' => 'active', 'name' => '2025/2026']);
    $department = Department::factory()->create(['institution_id' => $institution->id]);
    $program = Program::factory()->create(['department_id' => $department->id, 'institution_id' => $institution->id]);

    $student = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'admission_year' => 2025,
        'entry_level' => 100,
    ]);

    $studentUser = User::where('email', $student->email)->first();

    $invoice = Invoice::factory()->create([
        'institution_id' => $institution->id,
        'academic_session_id' => $session->id,
        'target_type' => 'dept',
        'department_id' => $department->id,
        'title' => 'SIWES Placement Fee',
        'is_required_for_placements' => true,
        'required_percent_for_placements' => 100,
        'status' => 'published',
    ]);

    $this->actingAs($studentUser);

    // 1. Should render locked UI and show invoice title
    Livewire::test('student.placements.index')
        ->assertSee('Placement / SIWES Fee Payment Required', false)
        ->assertSee('SIWES Placement Fee', false);

    // 2. Action method should be guarded
    Livewire::test('student.placements.index')
        ->call('openSelectModal', 999)
        ->assertSet('selectPlaceModal', false)
        ->assertSet('selectedPlacementId', null);

    // 3. Mark invoice as paid
    $studentInvoice = StudentInvoice::where('student_id', $student->id)->where('invoice_id', $invoice->id)->first();
    $studentInvoice->update([
        'amount_paid' => $studentInvoice->total_amount,
        'balance' => 0,
        'status' => 'paid',
    ]);

    // 4. Should now be unlocked
    Livewire::test('student.placements.index')
        ->assertDontSee('Placement / SIWES Fee Payment Required', false)
        ->assertSee('My Placements', false);
});
