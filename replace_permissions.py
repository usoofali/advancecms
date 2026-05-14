import os
import re

mappings = {
    'view_all_data': 'system.view_all_data',
    'view_dept_students': 'students.view_dept',
    'view_dept_courses': 'courses.view_dept',
    'view_dept_results': 'results.view_dept',
    'view_assigned_courses': 'courses.view_assigned',
    'enter_results': 'results.enter',
    'modify_results': 'results.modify',
    'generate_reports': 'reports.generate',
    'view_applications': 'applications.view',
    'approve_admissions': 'applications.approve',
    'create_student_records': 'students.create',
    'enroll_applicants': 'applicants.enroll',
    'view_personal_results': 'results.view_personal',
    'view_personal_registrations': 'registrations.view_personal',
    'manage_registration_status': 'registration_status.update',
    'manage_admission_status': 'admission_status.update',
    'take_attendance': 'attendance.take',
    'view_attendance_history': 'attendance.view_history',
    'view_own_attendance': 'attendance.view_own',
    'view_all_attendance': 'attendance.view_all',
    'manage_attendance_payments': 'attendance_payments.process',
    'sync_cbt_data': 'cbt_data.sync',
    'approve_cbt_results': 'cbt_results.approve',
}

# The catch-all 'manage_' ones need to be mapped to CRUD based on context.
# We can default to '.view' for general index pages, and '.edit' or '.create' where obvious.
# Since a script can't perfectly guess, let's just default to '.view' for route middlewares, 
# and for blade files we will map them manually or script them to `.view` as a safe fallback.
# For simplicity, let's map 'manage_X' -> 'X.view' globally, and we will fix creates/edits manually.

manage_mappings = {
    'manage_institutions': 'institutions.view',
    'manage_configurations': 'settings.view',
    'manage_roles': 'roles.view',
    'manage_staff': 'staff.view',
    'manage_departments': 'departments.view',
    'manage_programs': 'programs.view',
    'manage_courses': 'courses.view',
    'manage_registrations': 'registrations.view',
    'manage_application_forms': 'application_forms.view',
    'view_payments': 'payments.view', # this is view
    'record_payments': 'payments.create',
    'manage_invoices': 'invoices.view',
    'manage_system': 'system.manage',
    'manage_cbt_exams': 'cbt_exams.view',
    'manage_cbt_questions': 'cbt_questions.view',
}

mappings.update(manage_mappings)

def process_file(filepath):
    with open(filepath, 'r') as f:
        content = f.read()
    
    new_content = content
    for old, new in mappings.items():
        # Replace in @can('...')
        new_content = re.sub(rf"(['\"]){old}(['\"])", rf"\g<1>{new}\g<2>", new_content)
        # Replace in can:old (middleware)
        new_content = re.sub(rf"can:{old}\b", f"can:{new}", new_content)

    if content != new_content:
        with open(filepath, 'w') as f:
            f.write(new_content)
        print(f"Updated: {filepath}")

for root, dirs, files in os.walk('.'):
    if any(d in root for d in ['.git', 'vendor', 'node_modules', 'storage']):
        continue
    for file in files:
        if file.endswith('.php') or file.endswith('.blade.php'):
            process_file(os.path.join(root, file))

