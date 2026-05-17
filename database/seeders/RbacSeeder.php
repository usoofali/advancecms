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
            'grading_systems', 'academic_sessions',
        ];

        // Define Permissions
        $permissions = [
            'dashboard.view' => 'View the main application dashboard',
            'id_cards.request' => 'Submit requests for institutional ID cards',
            'id_cards.manage' => 'Approve, reject, and generate ID cards for students and staff',

            // Specialized Scopes
            'students.view_dept' => 'View students in department',
            'courses.view_dept' => 'View departmental courses',
            'courses.view_assigned' => 'View assigned courses and student lists',

            'results.view_dept' => 'View departmental academic results',
            'results.enter' => 'Enter student marks for assigned courses',
            'results.modify' => 'Modify results before final approval',
            'results.view_personal' => 'View own academic results',

            'registrations.view_personal' => 'View own course registrations',
            'registration_status.update' => 'Lock or unlock student course registrations',

            'reports.generate' => 'Generate transcripts and academic reports',

            'applications.view' => 'View student admission applications',
            'applications.approve' => 'Approve or reject student admissions',
            'applicants.enroll' => 'Manually enroll admitted applicants as registered students',

            'admission_status.update' => 'Open, close or schedule admission windows for an institution',

            'attendance.take' => 'Record student attendance for assigned courses',
            'attendance.view_history' => 'View past attendance records and session details',
            'attendance.view_own' => 'View personal attendance percentages and history',
            'attendance.view_all' => 'View attendance records for any student in the institution',

            'attendance_payments.process' => 'Process and manage monthly lecturer attendance payments',

            'invoices.generate' => 'Force generate bulk student invoices from template',
            'invoices.cancel' => 'Cancel issued student invoices',
            'invoices.print_report' => 'Generate and print detailed financial reports for invoices',
            'payments.verify' => 'Approve or reject student payment verification requests',

            'cbt_data.sync' => 'Export exam packages and import CBT results',
            'cbt_results.view' => 'View list of synchronized CBT results',
            'cbt_results.review' => 'Audit detailed student examination scripts',
            'cbt_results.approve' => 'Validate and finalize individual CBT results',
            'cbt_results.reject' => 'Discard or reject staged CBT results',
            'cbt_results.mass_action' => 'Perform batch approval or rejection of examination results',
            'cbt_sync.view' => 'Monitor CBT connectivity and sync logs',
            'cbt_sync.manage_tokens' => 'Generate and revoke Lab access tokens',
            'cbt_questions.import' => 'Bulk import questions from CSV into an examination bank',

            // New Granular Permissions
            'applications.notify' => 'Issue admission notifications for walk-in candidates',
            'applications.print_letter' => 'Generate and print admission letters',
            'applications.print_receipt' => 'Print application fee receipts',
            'students.change_status' => 'Update student administrative status (Active, Graduated, etc.)',
            'students.export' => 'Export student records to CSV/Excel',
            'students.import' => 'Bulk import student records from CSV',
            'registrations.print_form' => 'Generate and print student course registration forms',
            'registrations.print_exam_card' => 'Generate and print student examination cards',
            'invoices.view_personal' => 'View personal financial invoices and payment history',
            'students.view_lecturers' => 'View list of lecturers assigned to personal courses',

            'courses.allocate' => 'Assign courses to lecturers for specific academic sessions',
            'courses.revoke_allocation' => 'Remove course assignments from lecturers',
            'courses.export' => 'Export course lists to CSV/Excel',
            'courses.import' => 'Bulk import course records from CSV',

            'results.export' => 'Export examination results and broadsheets',
            'results.import' => 'Bulk import examination results from CSV',

            'invoices.manage_students' => 'View and manage individual student fee records and status',

            'system.manage' => 'Manage production environment, migrations, and system-wide configurations',
            'system.manage_addons' => 'Enable or disable specialized modules for institutions',
            'system.view_all_data' => 'Access data across all institutions',

            // Scoped Role Assignment Permissions
            'institutions.assign_roles' => 'Dynamically assign scoped roles strictly to institutions',
            'departments.assign_roles' => 'Dynamically assign scoped roles strictly to departments',
            'courses.assign_roles' => 'Dynamically assign scoped roles strictly to courses',
            'cbt_exams.assign_roles' => 'Dynamically assign scoped roles strictly to CBT exams',
        ];

        // Generate CRUD permissions
        foreach ($crudModels as $model) {
            $modelName = str_replace('_', ' ', $model);
            $permissions["{$model}.view"] = "View and list {$modelName} records";
            $permissions["{$model}.create"] = "Create new {$modelName} records";
            $permissions["{$model}.edit"] = "Modify existing {$modelName} records";
            $permissions["{$model}.delete"] = "Delete {$modelName} records from the system";
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
                    $crud(['staff', 'departments', 'programs', 'courses', 'application_forms', 'invoices', 'payments', 'cbt_exams', 'cbt_questions', 'students', 'registrations', 'grading_systems']),
                    [
                        'students.view_dept', 'courses.view_dept', 'results.view_dept',
                        'courses.view_assigned', 'results.enter', 'results.modify',
                        'reports.generate', 'applications.view', 'applications.approve', 'applicants.enroll',
                        'registration_status.update', 'admission_status.update',
                        'attendance.take', 'attendance.view_history', 'attendance.view_own', 'attendance.view_all', 'attendance_payments.process',
                        'invoices.generate', 'invoices.cancel', 'invoices.print_report', 'payments.verify',
                        'cbt_data.sync', 'cbt_results.view', 'cbt_results.review', 'cbt_results.approve', 'cbt_results.reject', 'cbt_results.mass_action',
                        'cbt_sync.view', 'cbt_sync.manage_tokens',
                        'applications.notify', 'applications.print_letter', 'applications.print_receipt',
                        'students.change_status', 'students.export', 'students.import',
                        'registrations.print_form', 'registrations.print_exam_card',
                        'courses.allocate', 'courses.revoke_allocation', 'courses.export', 'courses.import',
                        'results.export', 'results.import', 'invoices.manage_students',
                        'dashboard.view', 'id_cards.request',
                        'institutions.assign_roles', 'departments.assign_roles', 'courses.assign_roles', 'cbt_exams.assign_roles',
                    ]
                ),
            ],
            'Head of Department (HOD)' => [
                'description' => 'Manage departmental academic activities',
                'permissions' => array_merge(
                    $crud(['cbt_questions', 'cbt_exams']),
                    [
                        'students.view_dept', 'courses.view_dept', 'results.view_dept',
                        'registration_status.update', 'attendance.view_history',
                        'cbt_results.view', 'cbt_results.review', 'cbt_results.approve', 'cbt_results.reject', 'cbt_results.mass_action',
                        'cbt_sync.view', 'cbt_data.sync', 'cbt_questions.import', 'students.export',
                        'courses.allocate', 'courses.revoke_allocation', 'courses.export',
                        'results.export', 'invoices.manage_students',
                        'dashboard.view', 'id_cards.request',
                    ]
                ),
            ],
            'Lecturer' => [
                'description' => 'Academic instruction and result entry',
                'permissions' => array_merge(
                    $crud(['cbt_questions']),
                    ['cbt_exams.view', 'cbt_questions.import', 'courses.view_assigned', 'results.enter', 'results.modify', 'attendance.take', 'attendance.view_history', 'dashboard.view', 'id_cards.request']
                ),
            ],
            'Academic Secretary' => [
                'description' => 'Coordinate academic record keeping',
                'permissions' => array_merge(
                    $crud(['registrations']),
                    ['reports.generate', 'attendance.view_history', 'cbt_data.sync', 'cbt_sync.view', 'cbt_sync.manage_tokens', 'cbt_results.view', 'cbt_results.review', 'students.change_status', 'registrations.print_form', 'registrations.print_exam_card', 'dashboard.view', 'id_cards.request']
                ),
            ],
            'Admission Officer' => [
                'description' => 'Process student admissions',
                'permissions' => array_merge(
                    $crud(['application_forms']),
                    ['students.create', 'applications.view', 'applications.approve', 'applicants.enroll', 'applications.notify', 'applications.print_letter', 'applications.print_receipt', 'dashboard.view', 'id_cards.request']
                ),
            ],
            'Accountant' => [
                'description' => 'Institutional financial management',
                'permissions' => array_merge(
                    $crud(['payments', 'invoices']),
                    ['attendance_payments.process', 'attendance.view_history', 'invoices.generate', 'invoices.cancel', 'invoices.print_report', 'payments.verify', 'applications.print_receipt', 'dashboard.view', 'id_cards.request']
                ),
            ],
            'Student' => [
                'description' => 'Access personal academic features',
                'permissions' => [
                    'results.view_personal', 'registrations.view_personal', 'attendance.view_own',
                    'applications.print_letter', 'applications.print_receipt',
                    'registrations.print_form', 'registrations.print_exam_card',
                    'invoices.view_personal', 'students.view_lecturers',
                    'dashboard.view', 'id_cards.request',
                ],
            ],
        ];

        foreach ($roles as $name => $data) {
            $role = Role::updateOrCreate(
                ['role_name' => $name],
                ['description' => $data['description']]
            );

            // Sync Permissions
            $permissionIds = Permission::whereIn('permission_name', $data['permissions'])
                ->pluck('permission_id')
                ->toArray();

            $role->permissions()->sync($permissionIds);
        }
    }
}
