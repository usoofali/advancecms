<?php

use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\Institution;
use App\Models\User;
use App\Services\ActivityLogger;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('forbids users without activity_logs.view permission from accessing activity logs page', function (): void {
    $institution = Institution::factory()->create();
    $user = User::factory()
        ->for($institution)
        ->withRole('Student')
        ->create();

    $this->actingAs($user);

    $this->get(route('cms.activity-logs'))->assertForbidden();
});

it('allows authorized admins to view the activity logs page and see recorded logs', function (): void {
    $institution = Institution::factory()->create();
    $admin = User::factory()
        ->for($institution)
        ->withRole('Institutional Admin')
        ->create();

    ActivityLogger::log(
        action: 'created',
        module: 'Results',
        description: 'Entered test result for student',
        user: $admin,
        institutionId: $institution->id
    );

    $this->actingAs($admin);

    $this->get(route('cms.activity-logs'))->assertSuccessful();

    Livewire::test('pages::cms.activity-logs')
        ->assertOk()
        ->assertSee('Activity Audit Logs')
        ->assertSee('Entered test result for student')
        ->assertSee('Results');
});

it('can filter activity logs by module, action, and search keyword', function (): void {
    $institution = Institution::factory()->create();
    $admin = User::factory()
        ->for($institution)
        ->withRole('Super Admin')
        ->create();

    ActivityLogger::log(
        action: 'created',
        module: 'CBT',
        description: 'Created CBT question bank for CSC101',
        user: $admin,
        institutionId: $institution->id
    );

    ActivityLogger::log(
        action: 'updated',
        module: 'Finance',
        description: 'Verified student fee payment invoice #1002',
        user: $admin,
        institutionId: $institution->id
    );

    $this->actingAs($admin);

    Livewire::test('pages::cms.activity-logs')
        ->set('module', 'CBT')
        ->assertSee('Created CBT question bank for CSC101')
        ->assertDontSee('Verified student fee payment invoice #1002')
        ->set('module', '')
        ->set('search', 'invoice #1002')
        ->assertSee('Verified student fee payment invoice #1002')
        ->assertDontSee('Created CBT question bank for CSC101');
});

it('allows authorized users to export activity logs audit trail to CSV', function (): void {
    $institution = Institution::factory()->create();
    $admin = User::factory()
        ->for($institution)
        ->withRole('Super Admin')
        ->create();

    ActivityLogger::log(
        action: 'approved',
        module: 'CBT',
        description: 'Approved staged CBT exam scores',
        user: $admin,
        institutionId: $institution->id
    );

    $this->actingAs($admin);

    $response = Livewire::test('pages::cms.activity-logs')
        ->call('exportCsv');

    $response->assertFileDownloaded();
});

it('automatically logs activity when a course is created, updated, or deleted', function (): void {
    $institution = Institution::factory()->create();
    $admin = User::factory()
        ->for($institution)
        ->withRole('Super Admin')
        ->create();

    $this->actingAs($admin);

    $course = Course::factory()->create([
        'institution_id' => $institution->id,
        'course_code' => 'CSC201',
        'title' => 'Data Structures',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'module' => 'Courses',
        'action' => 'created',
        'subject_id' => $course->id,
    ]);

    $course->update(['title' => 'Advanced Data Structures']);

    $this->assertDatabaseHas('activity_logs', [
        'module' => 'Courses',
        'action' => 'updated',
        'subject_id' => $course->id,
    ]);

    $course->delete();

    $this->assertDatabaseHas('activity_logs', [
        'module' => 'Courses',
        'action' => 'deleted',
        'subject_id' => $course->id,
    ]);
});

it('correctly parses device name, browser, and OS from user agent strings', function (): void {
    $logMacChrome = new ActivityLog([
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
    ]);
    expect($logMacChrome->browser)->toBe('Chrome');
    expect($logMacChrome->device_os)->toBe('macOS');
    expect($logMacChrome->device_type)->toBe('Desktop');
    expect($logMacChrome->device_summary)->toBe('Chrome on macOS (Desktop)');

    $logIphoneSafari = new ActivityLog([
        'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/605.1.15',
    ]);
    expect($logIphoneSafari->browser)->toBe('Safari');
    expect($logIphoneSafari->device_os)->toBe('iOS');
    expect($logIphoneSafari->device_type)->toBe('Mobile');
    expect($logIphoneSafari->device_summary)->toBe('Safari on iOS (Mobile)');
});

it('allows authorized users to purge activity logs based on retention threshold', function (): void {
    $institution = Institution::factory()->create();
    $admin = User::factory()
        ->for($institution)
        ->withRole('Super Admin')
        ->create();

    $oldLog = ActivityLogger::log(
        action: 'deleted',
        module: 'Finance',
        description: 'Old invoice record',
        user: $admin,
        institutionId: $institution->id
    );
    $oldLog->created_at = now()->subDays(40);
    $oldLog->save();

    $recentLog = ActivityLogger::log(
        action: 'created',
        module: 'Courses',
        description: 'New course added',
        user: $admin,
        institutionId: $institution->id
    );
    $recentLog->created_at = now()->subDays(5);
    $recentLog->save();

    $this->actingAs($admin);

    Livewire::test('pages::cms.activity-logs')
        ->set('clearPeriod', '30_days')
        ->call('purgeLogs');

    $this->assertDatabaseMissing('activity_logs', ['id' => $oldLog->id]);
    $this->assertDatabaseHas('activity_logs', ['id' => $recentLog->id]);
});
