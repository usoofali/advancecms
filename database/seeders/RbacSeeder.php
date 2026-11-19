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
            'timetables', 'placements', 'placement_supervisors', 'projects', 'project_supervisors',
            'inventory_categories', 'inventory_items', 'inventory_locations',
        ];

        // Define Permissions with plain-language, self-describing explanations
        $permissions = [
            'dashboard.view' => 'View the main dashboard and summary statistics',
            'id_cards.request' => 'Request a digital or printed institutional ID card for yourself',
            'id_cards.manage' => 'Review, approve, and print student and staff ID cards',

            // Projects Granular Permissions
            'projects.view' => 'Open the academic projects overview and list',
            'projects.view_personal' => 'View your own project workspace and submit project topics',
            'projects.manage_sessions' => 'Create and configure academic project sessions and stages',
            'projects.assign_supervisors' => 'Assign project supervisors to eligible students',
            'projects.review_topics' => 'Review, approve, or request changes to student project topics',
            'projects.supervise' => 'View assigned student projects, review chapters, and give feedback',
            'projects.view_dept' => 'Monitor project progress and supervisor workloads in your department',
            'projects.reports' => 'Generate and print academic project summary reports',

            // Specialized Scopes
            'students.view_dept' => 'View and search students within your own department',
            'courses.view_dept' => 'View courses offered by your own department',
            'courses.view_assigned' => 'View courses assigned to you and their enrolled student lists',

            'results.view_dept' => 'View exam broadsheets and published results for your department',
            'results.enter' => 'Enter and save continuous assessment and exam scores for assigned courses',
            'results.modify' => 'Edit student scores before final departmental approval',
            'results.view_personal' => 'View your own semester exam results, GPA, and grade history',

            'registrations.view_personal' => 'View your registered courses and access the student course portal',
            'registration_status.update' => 'Approve, lock, or unlock student course registrations',

            'reports.generate' => 'Generate official student academic transcripts and summary reports',

            'applications.view' => 'View and filter incoming admission applications',
            'applications.approve' => 'Approve or decline candidate admission applications',
            'applicants.enroll' => 'Enroll admitted applicants as active registered students',

            'admission_status.update' => 'Open, close, and configure admission application periods',

            'attendance.take' => 'Mark and submit student class attendance for assigned courses',
            'attendance.view_history' => 'View past class attendance sessions and taught hours',
            'attendance.manage' => 'Review, edit, and manage all institutional attendance records',
            'attendance.view_own' => 'View your own class attendance record and percentage',
            'attendance.view_all' => 'View attendance records across all departments and programs',

            'attendance_payments.process' => 'Calculate and process lecturer attendance-based payments',

            'invoices.generate' => 'Generate student fee invoices in bulk from fee schedules',
            'invoices.cancel' => 'Cancel or void active student fee invoices',
            'invoices.print_report' => 'Export and print fee collection and invoice reports',
            'payments.verify' => 'Verify, approve, or reject manual student payment receipts',

            'cbt_data.sync' => 'Sync exam questions and import CBT exam scores from exam stations',
            'cbt_results.view' => 'View imported CBT exam score sheets and student results',
            'cbt_results.review' => 'Inspect a student\'s question-by-question CBT exam answers',
            'cbt_results.approve' => 'Approve an uploaded CBT exam score into official results',
            'cbt_results.reject' => 'Reject or invalidate an uploaded CBT exam submission',
            'cbt_results.mass_action' => 'Approve or reject all pending CBT exam scores at once',
            'cbt_sync.view' => 'View CBT exam station sync status and sync history',
            'cbt_sync.manage_tokens' => 'Create and revoke connection keys for CBT exam computers',
            'cbt_questions.import' => 'Import CBT exam questions from CSV files into question banks',
            'cbt_questions.export' => 'Export CBT exam questions and options to CSV files',

            'activity_logs.view' => 'View system activity and audit logs for your institution',
            'activity_logs.export' => 'Export activity log reports to CSV',
            'activity_logs.clear' => 'Clear or archive older activity log entries',

            // Granular Specialized Permissions
            'applications.notify' => 'Generate and send provisional admission notification slips',
            'applications.print_letter' => 'Download and print official admission letters',
            'applications.print_receipt' => 'Print official payment receipts for application fees',
            'students.change_status' => 'Update student status (Active, Suspended, Graduated, or Withdrawn)',
            'students.export' => 'Export student lists to CSV or Excel files',
            'students.import' => 'Bulk import student records from CSV files',
            'registrations.print_form' => 'Print official student course registration forms',
            'registrations.print_exam_card' => 'Print official student examination cards',
            'invoices.view_personal' => 'View your own fee invoices, payment receipts, and balance',
            'students.view_lecturers' => 'View names and contact details of lecturers for your courses',
            'timetables.view_personal' => 'View your personal class timetable schedule',

            'courses.allocate' => 'Assign departmental courses to lecturers for active sessions',
            'courses.revoke_allocation' => 'Remove course assignments from lecturers',
            'courses.export' => 'Export course lists and outlines to CSV or Excel',
            'courses.import' => 'Bulk import course records from CSV files',

            'results.export' => 'Export departmental result broadsheets to Excel or CSV',
            'results.import' => 'Bulk upload student exam scores from CSV spreadsheets',

            'invoices.manage_students' => 'Manage individual student fee accounts, adjustments, and balances',

            // Placements Granular Permissions
            'placements.view' => 'Open the industrial placements overview and list',
            'placements.view_personal' => 'View your placement status, choose a host organization, and upload acceptance letters',
            'placements.organizations' => 'Manage host companies and organizations for student placements',
            'placements.types' => 'Create and manage placement types (SIWES, Internship, Clinical, etc.)',
            'placements.manage' => 'Assign students to host organizations, review requests, and issue placement letters',
            'placements.supervisors' => 'Assign school and industry supervisors to student placements',
            'placements.supervise' => 'View assigned students, check logbooks, and submit placement scores',
            'placements.reports' => 'Generate and print placement supervision and performance reports',
            'placements.templates' => 'Create and edit official placement letter templates',

            'system.manage' => 'Access global website settings, system configuration, and maintenance tools',
            'system.view_all_data' => 'View and manage records across all institutions on the platform',

            // Scoped Role Assignment Permissions
            'institutions.assign_roles' => 'Assign staff roles for a specific institution',
            'departments.assign_roles' => 'Assign staff roles for a specific department',
            'courses.assign_roles' => 'Assign staff roles for a specific course',
            'cbt_exams.assign_roles' => 'Assign invigilator or examiner roles for a specific CBT exam',

            // Inventory Management Granular Permissions
            'inventory.view' => 'View the inventory overview, assigned room stock, and low-stock alerts',
            'inventory.view_all' => 'View stock balances across every room and store in the institution',
            'inventory.reconcile' => 'Record physical stock counts and update item quantities in assigned rooms',
            'inventory.assign_officers' => 'Assign or remove staff responsible for managing specific rooms or stores',
            'inventory.reports' => 'Generate printable room stock sheets, audit summaries, and CSV exports',

            // Payroll Management Permissions
            'payroll.view' => 'View the payroll overview and monthly salary summaries',
            'payroll.manage' => 'Set up staff pay categories, individual salary rules, allowances, and deductions',
            'payroll.create_run' => 'Prepare monthly payrolls, adjust staff pay items, and submit for approval',
            'payroll.approve' => 'Give final approval to monthly payrolls',
            'payroll.mark_paid' => 'Mark approved monthly payrolls as paid',
            'payroll.manage_loans' => 'Create, edit, pause, cancel, and record manual repayments for staff loans',
            'payroll.reports' => 'View and export departmental payroll summaries and bank payment schedules',
            'payroll.view_own_payslip' => 'View and print your own approved monthly payslips',
        ];

        // Plain-language labels for standard CRUD models
        $modelLabels = [
            'institutions' => 'institutions',
            'departments' => 'departments',
            'programs' => 'academic programs',
            'courses' => 'courses',
            'staff' => 'staff records',
            'roles' => 'user roles',
            'permissions' => 'permissions',
            'settings' => 'system settings',
            'application_forms' => 'admission application forms',
            'invoices' => 'student fee invoices',
            'payments' => 'student payment records',
            'cbt_exams' => 'CBT exams',
            'cbt_questions' => 'CBT exam questions',
            'students' => 'student records',
            'registrations' => 'course registrations',
            'grading_systems' => 'grading scales',
            'academic_sessions' => 'academic sessions',
            'ca_tests' => 'online C.A tests',
            'ca_results' => 'C.A test scores',
            'ca_attempts' => 'student C.A test attempts',
            'ca_questions' => 'C.A test questions',
            'ca_answers' => 'student C.A test answers',
            'student_coins' => 'student reward coin balances',
            'timetables' => 'class timetables',
            'placements' => 'student placements',
            'placement_supervisors' => 'placement supervisors',
            'projects' => 'student academic projects',
            'project_supervisors' => 'project supervisors',
            'inventory_categories' => 'inventory categories',
            'inventory_items' => 'inventory items',
            'inventory_locations' => 'rooms and storage locations',
        ];

        // Generate CRUD permissions with clear, natural descriptions
        foreach ($crudModels as $model) {
            $label = $modelLabels[$model] ?? str_replace('_', ' ', $model);
            $permissions["{$model}.view"] = "View and search {$label}";
            $permissions["{$model}.create"] = "Add new {$label}";
            $permissions["{$model}.edit"] = "Edit existing {$label}";
            $permissions["{$model}.delete"] = "Delete {$label}";
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
                'description' => 'Full platform access across all institutions and system settings',
                'permissions' => array_keys($permissions),
            ],
            'Institutional Admin' => [
                'description' => 'Full administrative control of academics, staff, students, finance, and operations within one institution',
                'permissions' => array_merge(
                    $crud(['staff', 'departments', 'programs', 'courses', 'application_forms', 'invoices', 'payments', 'cbt_exams', 'cbt_questions', 'students', 'registrations', 'grading_systems', 'ca_tests', 'placements', 'placement_supervisors', 'projects', 'project_supervisors', 'inventory_categories', 'inventory_items', 'inventory_locations']),
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
                        'projects.view', 'projects.manage_sessions', 'projects.assign_supervisors', 'projects.review_topics', 'projects.supervise', 'projects.view_dept', 'projects.reports',
                        'inventory.view', 'inventory.view_all', 'inventory.reconcile', 'inventory.assign_officers', 'inventory.reports',
                        'payroll.view', 'payroll.manage', 'payroll.create_run', 'payroll.approve', 'payroll.mark_paid', 'payroll.manage_loans', 'payroll.reports', 'payroll.view_own_payslip',
                        'dashboard.view', 'id_cards.request', 'id_cards.manage',
                        'activity_logs.view', 'activity_logs.export',
                        'institutions.assign_roles', 'departments.assign_roles', 'courses.assign_roles', 'cbt_exams.assign_roles',
                    ]
                ),
            ],
            'Inventory Officer' => [
                'description' => 'Manage stock and record physical counts for assigned rooms and stores',
                'permissions' => [
                    'inventory.view', 'inventory_categories.view', 'inventory_items.view', 'inventory_locations.view',
                    'inventory.reconcile', 'inventory.reports', 'dashboard.view', 'id_cards.request',
                ],
            ],
            'Inventory Auditor' => [
                'description' => 'Read-only review of stock balances, reports, and activity logs across all rooms',
                'permissions' => [
                    'inventory.view', 'inventory.view_all', 'inventory_categories.view', 'inventory_items.view',
                    'inventory_locations.view', 'inventory.reports', 'activity_logs.view', 'dashboard.view', 'id_cards.request',
                ],
            ],
            'Payroll Officer' => [
                'description' => 'Prepare monthly payrolls, manage staff pay rules, and export bank schedules (cannot approve)',
                'permissions' => [
                    'payroll.view', 'payroll.manage', 'payroll.create_run', 'payroll.manage_loans', 'payroll.reports', 'payroll.view_own_payslip',
                    'attendance.view_history', 'dashboard.view', 'id_cards.request',
                ],
            ],
            'Project Coordinator' => [
                'description' => 'Oversee academic project sessions, topic approvals, and supervisor assignments',
                'permissions' => [
                    'projects.view', 'projects.manage_sessions', 'projects.assign_supervisors', 'projects.review_topics',
                    'projects.supervise', 'projects.view_dept', 'projects.reports', 'dashboard.view', 'id_cards.request',
                ],
            ],
            'Head of Department (HOD)' => [
                'description' => 'Oversee departmental courses, lecturers, students, results, projects, and placements',
                'permissions' => array_merge(
                    $crud(['cbt_questions', 'cbt_exams', 'ca_tests']),
                    [
                        'students.view_dept', 'courses.view_dept', 'results.view_dept',
                        'registration_status.update', 'attendance.view_history', 'attendance.manage',
                        'cbt_results.view', 'cbt_results.review', 'cbt_results.approve', 'cbt_results.reject', 'cbt_results.mass_action',
                        'cbt_sync.view', 'cbt_data.sync', 'cbt_questions.import', 'cbt_questions.export', 'students.export',
                        'courses.allocate', 'courses.revoke_allocation', 'courses.export',
                        'results.export', 'invoices.manage_students', 'reports.generate',
                        'placements.view', 'placements.organizations', 'placements.types', 'placements.manage', 'placements.supervisors', 'placements.supervise', 'placements.reports', 'placements.templates',
                        'projects.view', 'projects.manage_sessions', 'projects.assign_supervisors', 'projects.review_topics', 'projects.supervise', 'projects.view_dept', 'projects.reports',
                        'inventory.view', 'inventory_categories.view', 'inventory_items.view', 'inventory_locations.view', 'inventory.reconcile', 'inventory.reports',
                        'payroll.view_own_payslip',
                        'activity_logs.view', 'activity_logs.export',
                        'dashboard.view', 'id_cards.request',
                    ]
                ),
            ],
            'Lecturer' => [
                'description' => 'Teach assigned courses, take attendance, enter results, and supervise students',
                'permissions' => array_merge(
                    $crud(['cbt_questions', 'ca_tests']),
                    ['cbt_exams.view', 'cbt_questions.import', 'cbt_questions.export', 'courses.view_assigned', 'results.enter', 'results.modify', 'attendance.take', 'attendance.view_history', 'timetables.view_personal', 'placements.supervise', 'projects.supervise', 'projects.view', 'payroll.view_own_payslip', 'dashboard.view', 'id_cards.request']
                ),
            ],
            'Academic Secretary' => [
                'description' => 'Manage student registrations, transcripts, exam cards, and academic records',
                'permissions' => array_merge(
                    $crud(['registrations']),
                    ['reports.generate', 'attendance.view_history', 'attendance.manage', 'cbt_data.sync', 'cbt_sync.view', 'cbt_sync.manage_tokens', 'cbt_results.view', 'cbt_results.review', 'students.change_status', 'registrations.print_form', 'registrations.print_exam_card', 'placements.reports', 'placements.view', 'projects.reports', 'projects.view', 'dashboard.view', 'id_cards.request']
                ),
            ],
            'Admission Officer' => [
                'description' => 'Manage application forms, review applicants, and enroll admitted students',
                'permissions' => array_merge(
                    $crud(['application_forms']),
                    ['students.create', 'applications.view', 'applications.approve', 'applicants.enroll', 'applications.notify', 'applications.print_letter', 'applications.print_receipt', 'dashboard.view', 'id_cards.request']
                ),
            ],
            'Exam Officer' => [
                'description' => 'Manage CBT exams, question banks, result imports/exports, and exam approvals',
                'permissions' => array_merge(
                    $crud(['cbt_exams', 'cbt_questions', 'ca_tests']),
                    [
                        'students.view_dept', 'courses.view_dept', 'results.view_dept', 'results.enter', 'results.modify',
                        'attendance.manage',
                        'cbt_data.sync', 'cbt_results.view', 'cbt_results.review', 'cbt_results.approve', 'cbt_results.reject', 'cbt_results.mass_action',
                        'cbt_sync.view', 'cbt_questions.import', 'cbt_questions.export', 'results.export', 'results.import',
                        'placements.reports',
                        'activity_logs.view', 'activity_logs.export',
                        'dashboard.view', 'id_cards.request',
                    ]
                ),
            ],
            'Accountant' => [
                'description' => 'Manage student invoices, payment verification, payroll disbursements, and financial reports',
                'permissions' => array_merge(
                    $crud(['payments', 'invoices']),
                    [
                        'attendance_payments.process', 'attendance.view_history', 'attendance.manage',
                        'invoices.generate', 'invoices.cancel', 'invoices.print_report', 'payments.verify', 'applications.print_receipt',
                        'inventory.view', 'inventory.view_all', 'inventory_categories.view', 'inventory_items.view', 'inventory_locations.view', 'inventory.reports',
                        'payroll.view', 'payroll.create_run', 'payroll.mark_paid', 'payroll.manage_loans', 'payroll.reports', 'payroll.view_own_payslip',
                        'dashboard.view', 'id_cards.request',
                    ]
                ),
            ],
            'Student' => [
                'description' => 'Access personal courses, timetable, results, invoices, C.A tests, projects, and placements',
                'permissions' => [
                    'results.view_personal', 'registrations.view_personal', 'attendance.view_own',
                    'applications.print_letter', 'applications.print_receipt',
                    'registrations.print_form', 'registrations.print_exam_card',
                    'invoices.view_personal', 'students.view_lecturers', 'timetables.view_personal',
                    'placements.view_personal', 'projects.view_personal',
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
