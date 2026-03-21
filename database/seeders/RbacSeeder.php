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
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define Permissions
        $permissions = [
            'manage_platform' => 'Global platform management',
            'manage_institutions' => 'Create and delete institutions',
            'manage_users' => 'Manage all platform users',
            'manage_configurations' => 'System-wide settings',
            'view_all_data' => 'Access data across all institutions',
            'manage_roles' => 'Manage system roles and their permissions',

            'manage_staff' => 'Manage institutional staff accounts',
            'assign_roles' => 'Assign roles to staff',
            'manage_departments' => 'Manage institutional structure',
            'manage_programs' => 'Manage academic programs',
            'manage_courses' => 'Manage institutional courses',
            'view_institutional_reports' => 'Access institutional data and reports',

            'view_dept_students' => 'View students in department',
            'view_dept_courses' => 'View departmental courses',
            'view_dept_results' => 'View departmental academic results',

            'view_assigned_courses' => 'View assigned courses and student lists',
            'enter_results' => 'Enter student marks for assigned courses',
            'modify_results' => 'Modify results before final approval',

            'manage_registrations' => 'Manage student course registrations',
            'view_academic_records' => 'View institutional academic records',
            'generate_reports' => 'Generate transcripts and academic reports',

            'view_applications' => 'View student admission applications',
            'approve_admissions' => 'Approve or reject student admissions',
            'create_student_records' => 'Create new student user accounts',
            'manage_application_forms' => 'Create, edit and delete application form templates',
            'enroll_applicants' => 'Manually enroll admitted applicants as registered students',

            'view_payments' => 'View dynamic financial records',
            'record_payments' => 'Record student fee payments',
            'manage_invoices' => 'Manage school fee templates and assignments',
            'generate_financial_reports' => 'Generate institutional financial statements',

            'view_personal_results' => 'View own academic results',
            'view_personal_registrations' => 'View own course registrations',
            'manage_registration_status' => 'Lock or unlock student course registrations',
            'manage_admission_status' => 'Open, close or schedule admission windows for an institution',
            'take_attendance' => 'Record student attendance for assigned courses',
            'view_attendance_history' => 'View past attendance records and session details',
            'view_own_attendance' => 'View personal attendance percentages and history',
            'view_all_attendance' => 'View attendance records for any student in the institution',
            'manage_attendance_payments' => 'Process and manage monthly lecturer attendance payments',
            'manage_system' => 'Manage production environment, migrations, and system-wide configurations',
        ];

        foreach ($permissions as $name => $desc) {
            Permission::updateOrCreate(
                ['permission_name' => $name]
            );
        }

        // Define Roles and their Permissions
        $roles = [
            'Super Admin' => [
                'description' => 'Global platform ownership',
                'permissions' => [
                    'manage_platform', 'manage_institutions', 'manage_users',
                    'manage_configurations', 'view_all_data', 'manage_roles',
                    'manage_invoices', 'view_payments', 'record_payments', 'generate_financial_reports',
                    'manage_application_forms', 'enroll_applicants', 'manage_admission_status',
                    'take_attendance', 'view_attendance_history', 'view_own_attendance', 'view_all_attendance', 'manage_attendance_payments',
                    'manage_system',
                ],
            ],
            'Institutional Admin' => [
                'description' => 'Full administrative control within one institution',
                'permissions' => [
                    // Core Admin
                    'manage_staff', 'assign_roles', 'manage_departments',
                    'manage_programs', 'manage_courses', 'view_institutional_reports',
                    // HOD Level
                    'view_dept_students', 'view_dept_courses', 'view_dept_results',
                    // Lecturer Level
                    'view_assigned_courses', 'enter_results', 'modify_results',
                    // Secretary Level
                    'manage_registrations', 'view_academic_records', 'generate_reports',
                    // Admission Level
                    'view_applications', 'approve_admissions', 'create_student_records', 'manage_application_forms', 'enroll_applicants',
                    // Finance Level
                    'view_payments', 'record_payments', 'generate_financial_reports', 'manage_invoices',
                    // Registration Control
                    'manage_registration_status', 'manage_admission_status',
                    // Attendance & Payments
                    'take_attendance', 'view_attendance_history', 'view_own_attendance', 'view_all_attendance', 'manage_attendance_payments',
                ],
            ],
            'Head of Department (HOD)' => [
                'description' => 'Manage departmental academic activities',
                'permissions' => [
                    'view_dept_students', 'view_dept_courses', 'view_dept_results',
                    'manage_registration_status',
                    'view_attendance_history',
                ],
            ],
            'Lecturer' => [
                'description' => 'Academic instruction and result entry',
                'permissions' => ['view_assigned_courses', 'enter_results', 'modify_results', 'take_attendance', 'view_attendance_history'],
            ],
            'Academic Secretary' => [
                'description' => 'Coordinate academic record keeping',
                'permissions' => ['manage_registrations', 'view_academic_records', 'generate_reports', 'view_attendance_history'],
            ],
            'Admission Officer' => [
                'description' => 'Process student admissions',
                'permissions' => ['view_applications', 'approve_admissions', 'create_student_records', 'manage_application_forms', 'enroll_applicants'],
            ],
            'Accountant' => [
                'description' => 'Institutional financial management',
                'permissions' => ['view_payments', 'record_payments', 'generate_financial_reports', 'manage_invoices', 'manage_attendance_payments', 'view_attendance_history'],
            ],
            'Student' => [
                'description' => 'Access personal academic features',
                'permissions' => ['view_personal_results', 'view_personal_registrations', 'view_own_attendance'],
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
