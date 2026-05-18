<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Result;
use App\Models\Student;
use App\Services\GradingService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RegradeResultsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'results:regrade
                            {--student= : Re-grade a specific student by their matric number}
                            {--department= : Re-grade all students in a specific department ID}
                            {--all : Re-grade all students in the entire institution}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Re-grade student results using their department's custom grading system or the default scale";

    /**
     * Execute the console command.
     */
    public function handle(GradingService $gradingService): int
    {
        $studentMatric = $this->option('student');
        $departmentId = $this->option('department');
        $all = $this->option('all');

        if (! $studentMatric && ! $departmentId && ! $all) {
            $this->error('Please specify a target option: --student, --department, or --all');

            return Command::FAILURE;
        }

        if ($studentMatric) {
            $student = Student::where('matric_number', $studentMatric)->first();
            if (! $student) {
                $this->error("Student with matric number '{$studentMatric}' not found.");

                return Command::FAILURE;
            }

            $this->info("Re-grading student: {$student->full_name} ({$student->matric_number})");

            $results = $student->results;
            if ($results->isEmpty()) {
                $this->warn('No results found for this student.');

                return Command::SUCCESS;
            }

            $this->regradeCollection($results, $gradingService);
        }

        if ($departmentId) {
            $department = Department::find($departmentId);
            if (! $department) {
                $this->error("Department with ID '{$departmentId}' not found.");

                return Command::FAILURE;
            }

            $this->info("Re-grading all students in department: {$department->name}");

            $results = Result::whereHas('student.program', function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId);
            })->get();

            if ($results->isEmpty()) {
                $this->warn('No results found for this department.');

                return Command::SUCCESS;
            }

            $this->regradeCollection($results, $gradingService);
        }

        if ($all) {
            if (! $this->confirm('Are you sure you want to re-grade ALL student results across the entire institution? This could take a while.')) {
                $this->info('Operation cancelled.');

                return Command::SUCCESS;
            }

            $this->info('Re-grading all student results in the institution...');

            $totalCount = Result::count();
            $bar = $this->output->createProgressBar($totalCount);
            $bar->start();

            Result::chunk(100, function ($results) use ($gradingService, $bar) {
                foreach ($results as $result) {
                    $gradingService->grade($result);
                    $bar->advance();
                }
            });

            $bar->finish();
            $this->newLine();
            $this->info('All institution records successfully re-graded!');
        }

        return Command::SUCCESS;
    }

    /**
     * Re-grade a collection of results and show progress bar.
     */
    protected function regradeCollection(Collection $results, GradingService $gradingService): void
    {
        $bar = $this->output->createProgressBar($results->count());
        $bar->start();

        foreach ($results as $result) {
            $gradingService->grade($result);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Successfully re-graded {$results->count()} result record(s).");
    }
}


// php artisan results:regrade --student=GUS/CHEW/2023/007
// php artisan results:regrade --department=1
// php artisan results:regrade --all

