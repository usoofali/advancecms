<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\User;
use Illuminate\Console\Command;

class SyncStudentUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'students:sync-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync and create missing user login accounts for all existing student records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for student records without linked user accounts...');

        Student::$suppressEnrollmentNotification = true;

        $missingStudents = Student::whereNotNull('email')
            ->whereNotIn('email', User::pluck('email'))
            ->get();

        if ($missingStudents->isEmpty()) {
            $this->info('All students already have linked user accounts.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($missingStudents as $student) {
            $student->save();
            $count++;
        }

        $this->info("Successfully created {$count} missing student user accounts.");

        return self::SUCCESS;
    }
}
