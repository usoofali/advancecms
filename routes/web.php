<?php

use App\Http\Controllers\OPayController;
use App\Livewire\Pages\Admissions\ApplicantPortal;
use App\Livewire\Pages\Admissions\Apply;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

// OPay Webhook, Return & Cancel
Route::post('opay/callback', [OPayController::class, 'handleCallback'])->name('opay.callback');
Route::get('opay/return', [OPayController::class, 'handleReturn'])->name('opay.return');
Route::get('opay/cancel', [OPayController::class, 'handleCancel'])->name('opay.cancel');
Route::get('opay/applicant/return', [OPayController::class, 'handleApplicantReturn'])->name('opay.applicant.return');
Route::get('opay/applicant/cancel', [OPayController::class, 'handleApplicantCancel'])->name('opay.applicant.cancel');

// Public Guest Admission Flow
Route::get('/apply', Apply::class)->name('apply');
Route::get('/applicant/portal/{application_number}', ApplicantPortal::class)->name('applicant.portal');
Route::livewire('/applicant/admission-letter/{applicant:application_number}', 'pages::cms.admissions.print-admission-letter')->name('applicant.admission-letter');
Route::livewire('/applicant/invoice/{studentInvoice}/print', 'pages::cms.invoices.print-invoice')->name('applicant.invoice.print');
Route::livewire('/applicant/receipt/{receipt:receipt_number}/print', 'pages::cms.admissions.print-application-receipt')->name('applicant.receipt.print');
Route::livewire('/setup', 'pages::⚡setup')->name('setup');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    // Institution Management (Admin)
    Route::middleware('can:manage_institutions')->group(function () {
        Route::livewire('institutions', 'pages::cms.institutions.index')->name('cms.institutions.index');
        Route::livewire('institutions/create', 'pages::cms.institutions.create')->name('cms.institutions.create');
        Route::livewire('institutions/{institution}/edit', 'pages::cms.institutions.edit')->name('cms.institutions.edit');
    }
    );

    // Department Management
    Route::middleware('can:manage_departments')->group(function () {
        Route::livewire('departments', 'pages::cms.departments.index')->name('cms.departments.index');
        Route::livewire('departments/create', 'pages::cms.departments.create')->name('cms.departments.create');
        Route::livewire('departments/{department}/edit', 'pages::cms.departments.edit')->name('cms.departments.edit');
    }
    );

    // Program Management
    Route::middleware('can:manage_programs')->group(function () {
        Route::livewire('programs', 'pages::cms.programs.index')->name('cms.programs.index');
        Route::livewire('programs/create', 'pages::cms.programs.create')->name('cms.programs.create');
        Route::livewire('programs/{program}/edit', 'pages::cms.programs.edit')->name('cms.programs.edit');
    }
    );

    // Admissions (Staff & Admins)
    Route::middleware('can:view_applications')->group(function () {
        Route::livewire('admissions/applications', 'pages::cms.admissions.application-index')->name('cms.admissions.index');
        Route::livewire('admissions/applications/{applicant}', 'pages::cms.admissions.application-show')->name('cms.admissions.show');

        // Application Form Management
        Route::middleware('can:manage_application_forms')->group(function () {
            Route::livewire('admissions/forms', 'pages::cms.admissions.form-index')->name('cms.admissions.forms.index');
            Route::livewire('admissions/forms/create', 'pages::cms.admissions.form-create')->name('cms.admissions.forms.create');
            Route::livewire('admissions/forms/{form}/edit', 'pages::cms.admissions.form-create')->name('cms.admissions.forms.edit');
        });
    });

    // Student Management
    Route::livewire('students/create', 'pages::cms.students.create')->name('cms.students.create')->middleware('can:create_student_records');

    Route::middleware('can:view_dept_students')->group(function () {
        Route::livewire('students', 'pages::cms.students.index')->name('cms.students.index');
        Route::livewire('students/print', 'pages::cms.students.print-list')->name('cms.students.print');
        Route::livewire('students/{student}', 'pages::cms.students.show')->name('cms.students.show');
        Route::livewire('students/{student}/edit', 'pages::cms.students.edit')->name('cms.students.edit');
    }
    );
    Route::livewire('students/registration', 'pages::cms.students.registration')->name('cms.students.registration')->middleware('can:manage_registrations');
    Route::livewire('students/manage-registrations', 'pages::cms.students.manage-registrations')->name('cms.students.manage-registrations')->middleware('can:manage_registration_status');
    Route::livewire('portal/registration', 'pages::cms.students.portal-registration')->name('cms.students.portal-registration')->middleware('can:view_personal_registrations');
    Route::livewire('portal/course-form', 'pages::cms.students.course-form')->name('cms.students.course-form')->middleware('auth');
    Route::livewire('portal/exam-card', 'pages::cms.students.exam-card')->name('cms.students.exam-card')->middleware('auth');
    Route::livewire('portal/my-lecturers', 'pages::cms.students.my-lecturers')->name('cms.students.my-lecturers')->middleware('can:view_personal_registrations');
    Route::livewire('portal/invoices', 'pages::cms.students.portal-invoices')->name('cms.students.portal-invoices')->middleware('auth');

    // Course Management
    Route::middleware('can:manage_courses')->group(function () {
        Route::livewire('courses', 'pages::cms.courses.index')->name('cms.courses.index');
        Route::livewire('courses/create', 'pages::cms.courses.create')->name('cms.courses.create');
        Route::livewire('courses/allocations', 'pages::cms.courses.allocations')->name('cms.courses.allocations');
        Route::livewire('courses/{course}/edit', 'pages::cms.courses.edit')->name('cms.courses.edit');
    }
    );
    Route::livewire('courses/my-allocations', 'pages::cms.courses.my-allocations')->name('cms.courses.my-allocations')->middleware('can:view_assigned_courses');

    // Attendance
    Route::middleware('can:take_attendance')->group(function () {
        Route::livewire('attendance/take', 'pages::cms.attendance.take')->name('cms.attendance.take');
    });
    Route::middleware('can:view_attendance_history')->group(function () {
        Route::livewire('attendance/history', 'pages::cms.attendance.history')->name('cms.attendance.history');
    });

    Route::middleware('can:view_own_attendance')->group(function () {
        Route::livewire('attendance/my-participation', 'pages::cms.attendance.student-view')->name('cms.attendance.participation');
    });

    // Academic Sessions
    Route::livewire('sessions', 'pages::cms.sessions.index')->name('cms.sessions.index')->middleware('can:manage_configurations');

    // Staff Management
    Route::middleware('can:manage_staff')->group(function () {
        Route::livewire('staff', 'pages::cms.staff.index')->name('cms.staff.index');
        Route::livewire('staff/create', 'pages::cms.staff.create')->name('cms.staff.create');
        Route::livewire('staff/{staff}/edit', 'pages::cms.staff.edit')->name('cms.staff.edit');
    }
    );

    // Role & Permission Management
    Route::middleware('can:manage_roles')->group(function () {
        Route::livewire('roles', 'pages::cms.roles.index')->name('cms.roles.index');
        Route::livewire('roles/{role}/permissions', 'pages::cms.roles.permissions')->name('cms.roles.permissions');
    }
    );

    // Results
    Route::livewire('results', 'pages::cms.results.index')->name('cms.results.index')->middleware('can:view_dept_results');
    Route::livewire('results/export', 'pages::cms.results.export')->name('cms.results.export')->middleware('can:view_dept_results');
    Route::livewire('results/entry', 'pages::cms.results.entry')->name('cms.results.entry')->middleware('can:enter_results');
    Route::livewire('results/portal', 'pages::cms.results.portal')->name('cms.results.portal')->middleware('can:view_personal_results');
    Route::livewire('results/transcripts', 'pages::cms.results.transcript-manager')->name('cms.results.transcripts')->middleware('can:generate_reports');

    // Finance Management
    Route::prefix('invoices')->name('cms.invoices.')->group(function () {
        Route::livewire('/', 'pages::cms.invoices.invoice-index')->name('index')->middleware('can:view_payments');
        Route::livewire('/create', 'pages::cms.invoices.invoice-create')->name('create')->middleware('can:manage_invoices');
        Route::livewire('/{invoice}/edit', 'pages::cms.invoices.invoice-create')->name('edit')->middleware('can:manage_invoices');
        Route::livewire('/{invoice}/students', 'pages::cms.invoices.student-invoices')->name('students')->middleware('can:manage_invoices');
        Route::livewire('/{invoice}/print-report', 'pages::cms.invoices.print-invoice-report')->name('print-report')->middleware('can:manage_invoices');
        Route::livewire('/payments', 'pages::cms.invoices.payment-index')->name('payments')->middleware('can:record_payments');
        Route::livewire('/receipt/{receipt:receipt_number}/print', 'pages::cms.invoices.print-receipt')->name('receipt.print')->middleware('auth');
        Route::livewire('/{studentInvoice}/print', 'pages::cms.invoices.print-invoice')->name('print')->middleware('auth');
    }
    );

    // Lecturer Attendance Payments
    Route::middleware('can:manage_attendance_payments')->group(function () {
        Route::livewire('finance/attendance-payments', 'pages::cms.attendance.manage-payments')->name('cms.attendance.payments');
    });
    // ID Card Management
    Route::group(['prefix' => 'id-cards', 'as' => 'cms.id-cards.'], function () {
        Route::livewire('request', 'cms.id-cards.request-card')->name('request');
        Route::livewire('manage', 'cms.id-cards.manage-id-cards')->name('manage')->middleware('can:manage_staff');
        Route::livewire('print/{data}', 'cms.id-cards.print-id-cards')->name('print')->middleware('can:manage_staff');
    });
});

require __DIR__.'/settings.php';
