<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define standard CRUD models
        $crudModels = [
            'institutions', 'departments', 'programs', 'courses', 'staff',
            'roles', 'permissions', 'settings', 'application_forms', 'invoices',
            'payments', 'cbt_exams', 'cbt_questions', 'students', 'registrations',
            'grading_systems', 'academic_sessions', 'ca_tests', 'ca_results', 'ca_attempts', 'ca_questions', 'ca_answers', 'student_coins',
            'timetables', 'placements', 'placement_supervisors',
        ];

        // Define Permissions with explicit, clear descriptions
        $permissions = [
            'dashboard.view' => 'Access main institutional metrics dashboard and overview analytics',
            'id_cards.request' => 'Submit personal request for digital or printed student/staff ID card',
            'id_cards.manage' => 'Manage, approve, and print student and staff institutional ID cards',

            // Specialized Scopes
            'students.view_dept' => 'View and search list of registered students within own department',
            'courses.view_dept' => 'View list and curriculum of courses offered by own department',
            'courses.view_assigned' => 'View courses allocated to self as a lecturer and access assigned student rosters',

            'results.view_dept' => 'View broadsheets and published academic examination results for own department',
            'results.enter' => 'Input and save continuous assessment and examination marks for assigned courses',
            'results.modify' => 'Edit or update entered student scores prior to departmental approval',
            'results.view_personal' => 'Access personal semester exam results, GPA, and grade statements',

            'registrations.view_personal' => 'View personal registered courses and access student course portal',
            'registration_status.update' => 'Approve, lock, or unlock student course registrations',

            'reports.generate' => 'Generate official student academic transcripts and summary reports',

            'applications.view' => 'View and filter incoming candidate admission applications',
            'applications.approve' => 'Grant or deny admission approval for candidate applications',
            'applicants.enroll' => 'Convert admitted applicants into active registered students',

            'admission_status.update' => 'Configure and toggle institution admission application cycles',

            'attendance.take' => 'Mark and submit live student class attendance for assigned courses',
            'attendance.view_history' => 'View history of conducted attendance sessions and taught contact hours',
            'attendance.manage' => 'Manage, audit, and override all institutional attendance records and tallies',
            'attendance.view_own' => 'View personal class attendance record and percentage progress as a student',
            'attendance.view_all' => 'View complete attendance logs across all departments and programs',

            'attendance_payments.process' => 'Calculate and process monthly lecturer attendance lecture payouts',

            'invoices.generate' => 'Generate bulk student fee invoices based on institutional fee structures',
            'invoices.cancel' => 'Cancel or void active student fee invoices',
            'invoices.print_report' => 'Export and print financial revenue and invoice collection reports',
            'payments.verify' => 'Verify, approve, or reject manual student payment evidence',

            'cbt_data.sync' => 'Sync examination question packages and import CBT exam scores from lab nodes',
            'cbt_results.view' => 'Access synchronized CBT examination score sheets and student scores',
            'cbt_results.review' => 'Inspect student question-by-question CBT answer scripts',
            'cbt_results.approve' => 'Approve individual student CBT exam submissions into official results',
            'cbt_results.reject' => 'Reject or invalidate staged CBT exam submissions',
            'cbt_results.mass_action' => 'Batch approve or reject all staged CBT examination results',
            'cbt_sync.view' => 'View CBT node sync status, server connectivity, and sync history',
            'cbt_sync.manage_tokens' => 'Create, manage, and revoke CBT lab workstation access tokens',
            'cbt_questions.import' => 'Bulk import examination questions from CSV templates into question banks',

            // Granular Specialized Permissions
            'applications.notify' => 'Generate and issue provisional admission notification slips',
            'applications.print_letter' => 'Download and print official candidate admission letters',
            'applications.print_receipt' => 'Print official payment receipts for application fees',
            'students.change_status' => 'Change student administrative status (Active, Suspended, Graduated, Withdrawn)',
            'students.export' => 'Export student directories and registers to CSV or Excel files',
            'students.import' => 'Bulk import student bio-data and admission records from CSV files',
            'registrations.print_form' => 'Print official signed student course registration forms',
            'registrations.print_exam_card' => 'Print official student examination eligibility cards',
            'invoices.view_personal' => 'View personal student fee invoices, payment receipts, and balance',
            'students.view_lecturers' => 'View names and contact info of lecturers assigned to registered courses',
            'timetables.view_personal' => 'Access personal class timetable schedule',

            'courses.allocate' => 'Allocate departmental courses to lecturers for active academic sessions',
            'courses.revoke_allocation' => 'Deallocate or remove course assignments from lecturers',
            'courses.export' => 'Export course directory and syllabus outlines to CSV or Excel',
            'courses.import' => 'Bulk import course curriculum records from CSV files',

            'results.export' => 'Export departmental result broadsheets to Excel or CSV files',
            'results.import' => 'Bulk upload student exam marks from CSV spreadsheet files',

            'invoices.manage_students' => 'Manage individual student fee accounts, waivers, and invoice balances',

            // Placements Granular Permissions
            'placements.view' => 'Access placement analytics dashboard and management overview',
            'placements.view_personal' => 'Access personal placement status, select place of posting, and upload acceptance documents',
            'placements.organizations' => 'Manage host company/organization directories for student placements',
            'placements.types' => 'Create and manage placement program types (SIWES, Internship, Clinical, etc.)',
            'placements.manage' => 'Create, assign, and manage student placement postings and official letters',
            'placements.supervisors' => 'Assign institutional and industry supervisors to student placements',
            'placements.supervise' => 'Access assigned supervisee rosters, inspect logbooks, and submit evaluation scores',
            'placements.reports' => 'Generate and print comprehensive placement supervision performance reports',
            'placements.templates' => 'Design and edit official placement letter templates',

            'system.manage' => 'Access global website settings, system configurations, and environment maintenance',
            'system.manage_addons' => 'Toggle specialized institution add-on modules (CBT Exam, Online CA, etc.)',
            'system.view_all_data' => 'Bypass multi-tenancy filters to view data across all institutions',

            // Scoped Role Assignment Permissions
            'institutions.assign_roles' => 'Assign administrative roles scoped to specific institutions',
            'departments.assign_roles' => 'Assign administrative roles scoped to specific departments',
            'courses.assign_roles' => 'Assign roles scoped to specific course modules',
            'cbt_exams.assign_roles' => 'Assign invigilator or examiner roles scoped to specific CBT exams',
        ];

        // Human-friendly labels for standard CRUD models
        $modelLabels = [
            'institutions' => 'institution profiles',
            'departments' => 'department records',
            'programs' => 'degree program offerings',
            'courses' => 'course curriculum catalog',
            'staff' => 'staff employee accounts',
            'roles' => 'system access roles',
            'permissions' => 'permission definitions',
            'settings' => 'system settings',
            'application_forms' => 'admission application form templates',
            'invoices' => 'student fee invoices',
            'payments' => 'student fee payment records',
            'cbt_exams' => 'CBT examination schedules',
            'cbt_questions' => 'CBT exam question banks',
            'students' => 'student bio-data profiles',
            'registrations' => 'student course registration records',
            'grading_systems' => 'academic grading scale schemes',
            'academic_sessions' => 'academic calendar sessions',
            'ca_tests' => 'online continuous assessment tests',
            'ca_results' => 'CA test score submissions',
            'ca_attempts' => 'student CA test attempt logs',
            'ca_questions' => 'CA test question banks',
            'ca_answers' => 'student CA test question responses',
            'student_coins' => 'student gamification coin balances',
            'timetables' => 'lecture class timetable schedules',
            'placements' => 'student industrial placement postings',
            'placement_supervisors' => 'placement supervisor allocations',
        ];

        // Generate CRUD permissions with clear, human-readable descriptions
        foreach ($crudModels as $model) {
            $label = $modelLabels[$model] ?? str_replace('_', ' ', $model);
            $permissions["{$model}.view"] = "View and search list of {$label}";
            $permissions["{$model}.create"] = "Create new {$label} entries";
            $permissions["{$model}.edit"] = "Modify existing {$label} entries";
            $permissions["{$model}.delete"] = "Delete {$label} entries from the system";
        }

        foreach ($permissions as $name => $desc) {
            Permission::updateOrCreate(
                ['permission_name' => $name],
                ['description' => $desc]
            );
        }

        // Helper function to get all CRUD permissions for an array of models
        $crud = function (array $models) {
            $perms = [];
            foreach ($models as $model) {
                $perms[] = "{$model}.view";
                $perms[] = "{$model}.create";
                $perms[] = "{$model}.edit";
                $perms[] = "{$model}.delete";
            }

            return $perms;
        };

        // Define Roles and their Permissions
        $roles = [
            'Super Admin' => [
                'description' => 'Global platform ownership',
                'permissions' => array_keys($permissions), // Gets all permissions
            ],
            'Institutional Admin' => [
                'description' => 'Full administrative control within one institution',
                'permissions' => array_merge(
                    $crud(['staff', 'departments', 'programs', 'courses', 'application_forms', 'invoices', 'payments', 'cbt_exams', 'cbt_questions', 'students', 'registrations', 'grading_systems', 'ca_tests', 'placements', 'placement_supervisors']),
                    [
                        'students.view_dept', 'courses.view_dept', 'results.view_dept',
                        'courses.view_assigned', 'results.enter', 'results.modify',
                        'reports.generate', 'applications.view', 'applications.approve', 'applicants.enroll',
                        'registration_status.update', 'admission_status.update',
                        'attendance.take', 'attendance.view_history', 'attendance.manage', 'attendance.view_own', 'attendance.view_all', 'attendance_payments.process',
                        'invoices.generate', 'invoices.cancel', 'invoices.print_report', 'payments.verify',
                        'cbt_data.sync', 'cbt_results.view', 'cbt_results.review', 'cbt_results.approve', 'cbt_results.reject', 'cbt_results.mass_action',
                        'cbt_sync.view', 'cbt_sync.manage_tokens',
                        'applications.notify', 'applications.print_letter', 'applications.print_receipt',
                        'students.change_status', 'students.export', 'students.import',
                        'registrations.print_form', 'registrations.print_exam_card',
                        'courses.allocate', 'courses.revoke_allocation', 'courses.export', 'courses.import',
                        'results.export', 'results.import', 'invoices.manage_students',
                        'placements.view', 'placements.organizations', 'placements.types', 'placements.manage', 'placements.supervisors', 'placements.supervise', 'placements.reports', 'placements.templates',
                        'dashboard.view', 'id_cards.request', 'id_cards.manage',
                        'institutions.assign_roles', 'departments.assign_roles', 'courses.assign_roles', 'cbt_exams.assign_roles',
                    ]
                ),
            ],
            'Head of Department (HOD)' => [
                'description' => 'Manage departmental academic activities',
                'permissions' => array_merge(
                    $crud(['cbt_questions', 'cbt_exams', 'ca_tests']),
                    [
                        'students.view_dept', 'courses.view_dept', 'results.view_dept',
                        'registration_status.update', 'attendance.view_history', 'attendance.manage',
                        'cbt_results.view', 'cbt_results.review', 'cbt_results.approve', 'cbt_results.reject', 'cbt_results.mass_action',
                        'cbt_sync.view', 'cbt_data.sync', 'cbt_questions.import', 'students.export',
                        'courses.allocate', 'courses.revoke_allocation', 'courses.export',
                        'results.export', 'invoices.manage_students', 'reports.generate',
                        'placements.view', 'placements.organizations', 'placements.types', 'placements.manage', 'placements.supervisors', 'placements.supervise', 'placements.reports', 'placements.templates',
                        'dashboard.view', 'id_cards.request',
                    ]
                ),
            ],
            'Lecturer' => [
                'description' => 'Academic instruction and result entry',
                'permissions' => array_merge(
                    $crud(['cbt_questions', 'ca_tests']),
                    ['cbt_exams.view', 'cbt_questions.import', 'courses.view_assigned', 'results.enter', 'results.modify', 'attendance.take', 'attendance.view_history', 'timetables.view_personal', 'placements.supervise', 'dashboard.view', 'id_cards.request']
                ),
            ],
            'Academic Secretary' => [
                'description' => 'Coordinate academic record keeping',
                'permissions' => array_merge(
                    $crud(['registrations']),
                    ['reports.generate', 'attendance.view_history', 'attendance.manage', 'cbt_data.sync', 'cbt_sync.view', 'cbt_sync.manage_tokens', 'cbt_results.view', 'cbt_results.review', 'students.change_status', 'registrations.print_form', 'registrations.print_exam_card', 'placements.reports', 'placements.view', 'dashboard.view', 'id_cards.request']
                ),
            ],
            'Admission Officer' => [
                'description' => 'Process student admissions',
                'permissions' => array_merge(
                    $crud(['application_forms']),
                    ['students.create', 'applications.view', 'applications.approve', 'applicants.enroll', 'applications.notify', 'applications.print_letter', 'applications.print_receipt', 'dashboard.view', 'id_cards.request']
                ),
            ],
            'Exam Officer' => [
                'description' => 'Coordinate exams and academic results management',
                'permissions' => array_merge(
                    $crud(['cbt_exams', 'cbt_questions', 'ca_tests']),
                    [
                        'students.view_dept', 'courses.view_dept', 'results.view_dept', 'results.enter', 'results.modify',
                        'attendance.manage',
                        'cbt_data.sync', 'cbt_results.view', 'cbt_results.review', 'cbt_results.approve', 'cbt_results.reject', 'cbt_results.mass_action',
                        'cbt_sync.view', 'cbt_questions.import', 'results.export', 'results.import',
                        'placements.reports',
                        'dashboard.view', 'id_cards.request',
                    ]
                ),
            ],
            'Accountant' => [
                'description' => 'Institutional financial management',
                'permissions' => array_merge(
                    $crud(['payments', 'invoices']),
                    ['attendance_payments.process', 'attendance.view_history', 'attendance.manage', 'invoices.generate', 'invoices.cancel', 'invoices.print_report', 'payments.verify', 'applications.print_receipt', 'dashboard.view', 'id_cards.request']
                ),
            ],
            'Student' => [
                'description' => 'Access personal academic features',
                'permissions' => [
                    'results.view_personal', 'registrations.view_personal', 'attendance.view_own',
                    'applications.print_letter', 'applications.print_receipt',
                    'registrations.print_form', 'registrations.print_exam_card',
                    'invoices.view_personal', 'students.view_lecturers', 'timetables.view_personal',
                    'placements.view_personal',
                    'dashboard.view', 'id_cards.request',
                ],
            ],
        ];

        foreach ($roles as $name => $data) {
            $permissionIds = Permission::whereIn('permission_name', $data['permissions'])
                ->pluck('permission_id')
                ->toArray();

            if (app()->environment('local', 'testing')) {
                $role = Role::updateOrCreate(
                    ['role_name' => $name],
                    ['description' => $data['description']]
                );

                $role->permissions()->sync($permissionIds);
            } else {
                // Production environment: do not overwrite existing role metadata
                $role = Role::firstOrCreate(
                    ['role_name' => $name],
                    ['description' => $data['description']]
                );

                if ($name === 'Super Admin') {
                    $role->permissions()->sync($permissionIds);
                } else {
                    // Attach newly defined permissions without stripping custom production permissions
                    $role->permissions()->syncWithoutDetaching($permissionIds);
                }
            }
        }
    }
}
