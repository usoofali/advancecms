<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen theme-textured-bg antialiased text-zinc-900 dark:text-zinc-100">
    <flux:sidebar sticky collapsible="mobile"
        class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 print:hidden">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            @canany(['dashboard.view'])
                <flux:sidebar.group :heading="__('Overview')" class="grid">
                    @can('dashboard.view')
                        <flux:sidebar.item icon="home" icon:class="text-sky-500 dark:text-sky-400" :href="route('dashboard')"
                            :current="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Dashboard') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcanany

            @canany(['institutions.view', 'departments.view', 'programs.view', 'staff.view', 'id_cards.manage', 'grading_systems.view', 'academic_sessions.view', 'system.manage', 'activity_logs.view'])
                <flux:sidebar.group :heading="__('Administration')" class="grid" expandable expanded="false">
                    @can('institutions.view')
                        <flux:sidebar.item icon="building-office-2" icon:class="text-indigo-500 dark:text-indigo-400"
                            :href="route('cms.institutions.index')" :current="request()->routeIs('cms.institutions.*')"
                            wire:navigate>
                            {{ __('Institutions') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('departments.view')
                        <flux:sidebar.item icon="square-3-stack-3d" icon:class="text-purple-500 dark:text-purple-400"
                            :href="route('cms.departments.index')" :current="request()->routeIs('cms.departments.*')"
                            wire:navigate>
                            {{ __('Departments') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('programs.view')
                        <flux:sidebar.item icon="academic-cap" icon:class="text-emerald-500 dark:text-emerald-400"
                            :href="route('cms.programs.index')" :current="request()->routeIs('cms.programs.*')" wire:navigate>
                            {{ __('Programs') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('staff.view')
                        <flux:sidebar.item icon="user-group" icon:class="text-teal-500 dark:text-teal-400"
                            :href="route('cms.staff.index')" :current="request()->routeIs('cms.staff.*')" wire:navigate>
                            {{ __('Staff') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('id_cards.manage')
                        <flux:sidebar.item icon="identification" icon:class="text-cyan-500 dark:text-cyan-400"
                            :href="route('cms.id-cards.manage')" :current="request()->routeIs('cms.id-cards.manage')"
                            wire:navigate>
                            {{ __('ID Card Management') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="swatch" icon:class="text-fuchsia-500 dark:text-fuchsia-400"
                            :href="route('cms.id-cards.templates')" :current="request()->routeIs('cms.id-cards.templates')"
                            wire:navigate>
                            {{ __('ID Card Templates') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('academic_sessions.view')
                        <flux:sidebar.item icon="calendar-date-range" icon:class="text-amber-500 dark:text-amber-400"
                            :href="route('cms.sessions.index')" :current="request()->routeIs('cms.sessions.*')" wire:navigate>
                            {{ __('Sessions') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('grading_systems.view')
                        <flux:sidebar.item icon="chart-bar" icon:class="text-violet-500 dark:text-violet-400"
                            :href="route('cms.grading-systems.index')" :current="request()->routeIs('cms.grading-systems.*')"
                            wire:navigate>
                            {{ __('Grading Systems') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('activity_logs.view')
                        <flux:sidebar.item icon="clock" icon:class="text-emerald-500 dark:text-emerald-400"
                            :href="route('cms.activity-logs')" :current="request()->routeIs('cms.activity-logs')" wire:navigate>
                            {{ __('Activity Logs') }}
                        </flux:sidebar.item>
                    @endcan

                </flux:sidebar.group>
            @endcanany

            @canany(['applications.view', 'application_forms.view', 'applications.notify', 'students.create'])
                <flux:sidebar.group :heading="__('Admissions')" class="grid" expandable expanded="false">
                    @can('students.create')
                        <flux:sidebar.item icon="user-plus" icon:class="text-blue-500 dark:text-blue-400"
                            :href="route('cms.students.create')" :current="request()->routeIs('cms.students.create')"
                            wire:navigate>
                            {{ __('Enrol Student') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('applications.view')
                        <flux:sidebar.item icon="document-magnifying-glass" icon:class="text-amber-500 dark:text-amber-400"
                            :href="route('cms.admissions.index')"
                            :current="request()->routeIs('cms.admissions.index') || request()->routeIs('cms.admissions.show')"
                            wire:navigate>
                            {{ __('Applications') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('applications.notify')
                        <flux:sidebar.item icon="document-text" icon:class="text-orange-500 dark:text-orange-400"
                            :href="route('cms.admissions.issue-notification')"
                            :current="request()->routeIs('cms.admissions.issue-notification')" wire:navigate>
                            {{ __('Admission Notification') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('application_forms.view')
                        <flux:sidebar.item icon="document-plus" icon:class="text-emerald-500 dark:text-emerald-400"
                            :href="route('cms.admissions.forms.index')" :current="request()->routeIs('cms.admissions.forms.*')"
                            wire:navigate>
                            {{ __('Admission Forms') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcanany

            @canany(['students.view_dept', 'registrations.view', 'registration_status.update', 'registrations.view_personal', 'registrations.print_form', 'registrations.print_exam_card', 'students.view_lecturers', 'placements.view_personal', 'id_cards.request'])
                <flux:sidebar.group :heading="__('Academics')" class="grid" expandable expanded="false">
                    @can('students.view_dept')
                        <flux:sidebar.item icon="users" icon:class="text-indigo-500 dark:text-indigo-400"
                            :href="route('cms.students.index')"
                            :current="request()->routeIs('cms.students.index') || request()->routeIs('cms.students.create') || request()->routeIs('cms.students.edit')"
                            wire:navigate>
                            {{ __('Students') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="academic-cap" icon:class="text-violet-500 dark:text-violet-400"
                            :href="route('cms.alumni.index')" :current="request()->routeIs('cms.alumni.index')" wire:navigate>
                            {{ __('Alumni') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('registrations.view')
                        <flux:sidebar.item icon="clipboard-document-check" icon:class="text-emerald-500 dark:text-emerald-400"
                            :href="route('cms.students.registration')"
                            :current="request()->routeIs('cms.students.registration')" wire:navigate>
                            {{ __('Course Registration') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('registration_status.update')
                        <flux:sidebar.item icon="lock-closed" icon:class="text-rose-500 dark:text-rose-400"
                            :href="route('cms.students.manage-registrations')"
                            :current="request()->routeIs('cms.students.manage-registrations')" wire:navigate>
                            {{ __('Manage Registrations') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('registrations.view_personal')
                        <flux:sidebar.item icon="clipboard-document-check" icon:class="text-teal-500 dark:text-teal-400"
                            :href="route('cms.students.portal-registration')"
                            :current="request()->routeIs('cms.students.portal-registration')" wire:navigate>
                            {{ __('My Courses') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('registrations.print_form')
                        <flux:sidebar.item icon="document-text" icon:class="text-sky-500 dark:text-sky-400"
                            :href="route('cms.students.course-form')" :current="request()->routeIs('cms.students.course-form')"
                            wire:navigate>
                            {{ __('Course Form') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('registrations.print_exam_card')
                        <flux:sidebar.item icon="identification" icon:class="text-purple-500 dark:text-purple-400"
                            :href="route('cms.students.exam-card')" :current="request()->routeIs('cms.students.exam-card')"
                            wire:navigate>
                            {{ __('Examination Card') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('students.view_lecturers')
                        <flux:sidebar.item icon="user-circle" icon:class="text-pink-500 dark:text-pink-400"
                            :href="route('cms.students.my-lecturers')"
                            :current="request()->routeIs('cms.students.my-lecturers')" wire:navigate>
                            {{ __('My Lecturers') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('placements.view_personal')
                        <flux:sidebar.item icon="briefcase" icon:class="text-cyan-500 dark:text-cyan-400"
                            :href="route('cms.placements.student.index')"
                            :current="request()->routeIs('cms.placements.student.index')" wire:navigate>
                            {{ __('My Placements') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('id_cards.request')
                        <flux:sidebar.item icon="identification" icon:class="text-lime-500 dark:text-lime-400"
                            :href="route('cms.id-cards.request')" :current="request()->routeIs('cms.id-cards.request')"
                            wire:navigate>
                            {{ __('Request ID Card') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcanany

            @canany(['courses.view', 'courses.view_dept', 'courses.allocate', 'courses.view_assigned'])
                <flux:sidebar.group :heading="__('Courses')" class="grid" expandable expanded="false">
                    @canany(['courses.view', 'courses.view_dept'])
                        <flux:sidebar.item icon="book-open" icon:class="text-blue-500 dark:text-blue-400"
                            :href="route('cms.courses.index')"
                            :current="request()->routeIs('cms.courses.index') || request()->routeIs('cms.courses.create') || request()->routeIs('cms.courses.edit')"
                            wire:navigate>
                            {{ __('Courses') }}
                        </flux:sidebar.item>
                    @endcanany

                    @can('courses.allocate')
                        <flux:sidebar.item icon="clipboard-document-check" icon:class="text-amber-500 dark:text-amber-400"
                            :href="route('cms.courses.allocations')" :current="request()->routeIs('cms.courses.allocations')"
                            wire:navigate>
                            {{ __('Manage Allocations') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('courses.view_assigned')
                        <flux:sidebar.item icon="clipboard-document-check" icon:class="text-fuchsia-500 dark:text-fuchsia-400"
                            :href="route('cms.courses.my-allocations')"
                            :current="request()->routeIs('cms.courses.my-allocations')" wire:navigate>
                            {{ __('My Allocations') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcanany

            @canany(['timetables.view', 'timetables.view_personal'])
                <flux:sidebar.group :heading="__('Timetables')" class="grid" expandable expanded="false">
                    @can('timetables.view')
                        <flux:sidebar.item icon="calendar" icon:class="text-indigo-500 dark:text-indigo-400"
                            :href="route('cms.timetables.index')" :current="request()->routeIs('cms.timetables.index')"
                            wire:navigate>
                            {{ __('Lecture Timetables') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('timetables.view_personal')
                        <flux:sidebar.item icon="clock" icon:class="text-amber-500 dark:text-amber-400"
                            :href="route('cms.timetables.my-timetable')"
                            :current="request()->routeIs('cms.timetables.my-timetable')" wire:navigate>
                            {{ __('My Timetable') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcanany

            @canany(['placements.view', 'placements.organizations', 'placements.types', 'placements.manage', 'placements.supervisors', 'placements.supervise', 'placements.reports', 'placements.templates'])
                <flux:sidebar.group :heading="__('Placements')" class="grid" expandable expanded="false">
                    @can('placements.view')
                        <flux:sidebar.item icon="chart-bar" icon:class="text-cyan-500 dark:text-cyan-400"
                            :href="route('cms.placements.index')" :current="request()->routeIs('cms.placements.index')"
                            wire:navigate>
                            {{ __('Placements Dashboard') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('placements.organizations')
                        <flux:sidebar.item icon="building-office" icon:class="text-indigo-500 dark:text-indigo-400"
                            :href="route('cms.placements.organizations')"
                            :current="request()->routeIs('cms.placements.organizations')" wire:navigate>
                            {{ __('Organizations') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('placements.types')
                        <flux:sidebar.item icon="tag" icon:class="text-pink-500 dark:text-pink-400"
                            :href="route('cms.placements.types')" :current="request()->routeIs('cms.placements.types')"
                            wire:navigate>
                            {{ __('Placement Types') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('placements.manage')
                        <flux:sidebar.item icon="briefcase" icon:class="text-amber-500 dark:text-amber-400"
                            :href="route('cms.placements.manage')" :current="request()->routeIs('cms.placements.manage')"
                            wire:navigate>
                            {{ __('Manage Placements') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('placements.supervisors')
                        <flux:sidebar.item icon="user-group" icon:class="text-teal-500 dark:text-teal-400"
                            :href="route('cms.placements.supervisors')"
                            :current="request()->routeIs('cms.placements.supervisors')" wire:navigate>
                            {{ __('Placement Supervisors') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('placements.supervise')
                        <flux:sidebar.item icon="clipboard-document-check" icon:class="text-emerald-500 dark:text-emerald-400"
                            :href="route('cms.placements.my-supervisions')"
                            :current="request()->routeIs('cms.placements.my-supervisions')" wire:navigate>
                            {{ __('My Supervisions') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('placements.reports')
                        <flux:sidebar.item icon="document-text" icon:class="text-sky-500 dark:text-sky-400"
                            :href="route('cms.placements.reports')" :current="request()->routeIs('cms.placements.reports')"
                            wire:navigate>
                            {{ __('Supervision Reports') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('placements.templates')
                        <flux:sidebar.item icon="document-duplicate" icon:class="text-purple-500 dark:text-purple-400"
                            :href="route('cms.placements.templates')" :current="request()->routeIs('cms.placements.templates')"
                            wire:navigate>
                            {{ __('Letter Templates') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcanany

            @if(auth()->user()->institution?->hasAddon('exam_module'))
                @canany(['cbt_exams.view', 'cbt_questions.view', 'cbt_sync.view', 'cbt_results.view'])
                    <flux:sidebar.group :heading="__('CBT Exams')" class="grid" expandable expanded="false">
                        @can('cbt_exams.view')
                            <flux:sidebar.item icon="clipboard-document-list" icon:class="text-rose-500 dark:text-rose-400"
                                :href="route('cms.cbt.exams')" :current="request()->routeIs('cms.cbt.exams*')" wire:navigate>
                                {{ __('Manage Exams') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="key" icon:class="text-amber-500 dark:text-amber-400"
                                :href="route('cms.cbt.pin-access')" :current="request()->routeIs('cms.cbt.pin-access*')"
                                wire:navigate>
                                {{ __('PIN Access Control') }}
                            </flux:sidebar.item>
                        @endcan

                        @can('cbt_questions.view')
                            <flux:sidebar.item icon="question-mark-circle" icon:class="text-sky-500 dark:text-sky-400"
                                :href="route('cms.cbt.questions')" :current="request()->routeIs('cms.cbt.questions*')"
                                wire:navigate>
                                {{ __('Question Bank') }}
                            </flux:sidebar.item>
                        @endcan

                        @can('cbt_sync.view')
                            <flux:sidebar.item icon="arrows-right-left" icon:class="text-indigo-500 dark:text-indigo-400"
                                :href="route('cms.cbt.sync')" :current="request()->routeIs('cms.cbt.sync*')" wire:navigate>
                                {{ __('Sync Hub') }}
                            </flux:sidebar.item>
                        @endcan

                        @can('cbt_results.view')
                            <flux:sidebar.item icon="check-badge" icon:class="text-emerald-500 dark:text-emerald-400"
                                :href="route('cms.cbt.results')" :current="request()->routeIs('cms.cbt.results*')" wire:navigate>
                                {{ __('Review Results') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcanany
            @endif

            @if(auth()->user()->institution?->hasAddon('ca_module'))
                @canany(['ca_tests.view', 'courses.view_assigned', 'courses.view_dept', 'registrations.view_personal'])
                    <flux:sidebar.group :heading="__('Online C.A')" class="grid" expandable expanded="false">
                        @canany(['ca_tests.view', 'courses.view_assigned', 'courses.view_dept'])
                            <flux:sidebar.item icon="document-text" icon:class="text-violet-500 dark:text-violet-400"
                                :href="route('cms.ca-tests.lecturer.index')"
                                :current="request()->routeIs('cms.ca-tests.lecturer.*') && !request()->routeIs('cms.ca-tests.lecturer.results')"
                                wire:navigate>
                                {{ __('Manage C.A Tests') }}
                            </flux:sidebar.item>

                            <flux:sidebar.item icon="chart-bar" icon:class="text-teal-500 dark:text-teal-400"
                                :href="route('cms.ca-tests.lecturer.results')"
                                :current="request()->routeIs('cms.ca-tests.lecturer.results')" wire:navigate>
                                {{ __('C.A Test Results') }}
                            </flux:sidebar.item>
                        @endcanany

                        @can('registrations.view_personal')
                            <flux:sidebar.item icon="pencil-square" icon:class="text-blue-500 dark:text-blue-400"
                                :href="route('cms.ca-tests.student.index')" :current="request()->routeIs('cms.ca-tests.student.*')"
                                wire:navigate>
                                {{ __('My C.A Tests') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcanany
            @endif

            @canany(['results.enter', 'results.view_dept', 'reports.generate', 'results.view_personal'])
                <flux:sidebar.group :heading="__('Results')" class="grid" expandable expanded="false">
                    @can('results.enter')
                        <flux:sidebar.item icon="pencil-square" icon:class="text-emerald-500 dark:text-emerald-400"
                            :href="route('cms.results.entry')" :current="request()->routeIs('cms.results.entry')" wire:navigate>
                            {{ __('Enter Results') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('results.view_dept')
                        <flux:sidebar.item icon="chart-bar" icon:class="text-blue-500 dark:text-blue-400"
                            :href="route('cms.results.index')" :current="request()->routeIs('cms.results.index')" wire:navigate>
                            {{ __('View Results') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('reports.generate')
                        <flux:sidebar.item icon="academic-cap" icon:class="text-purple-500 dark:text-purple-400"
                            :href="route('cms.results.transcripts')" :current="request()->routeIs('cms.results.transcripts')"
                            wire:navigate>
                            {{ __('Transcript') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('results.view_personal')
                        <flux:sidebar.item icon="identification" icon:class="text-amber-500 dark:text-amber-400"
                            :href="route('cms.results.portal')" :current="request()->routeIs('cms.results.portal')"
                            wire:navigate>
                            {{ __('Exam Results') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcanany

            @canany(['invoices.view', 'payments.view', 'payments.verify', 'invoices.view_personal'])
                <flux:sidebar.group :heading="__('Finance')" class="grid" expandable expanded="false">
                    @can('invoices.view')
                        <flux:sidebar.item icon="banknotes" icon:class="text-emerald-500 dark:text-emerald-400"
                            :href="route('cms.invoices.index')"
                            :current="request()->routeIs('cms.invoices.index') || request()->routeIs('cms.invoices.create') || request()->routeIs('cms.invoices.edit') || request()->routeIs('cms.invoices.students')"
                            wire:navigate>
                            {{ __('Invoice Management') }}
                        </flux:sidebar.item>
                    @endcan

                    @canany(['payments.view', 'payments.verify'])
                        <flux:sidebar.item icon="check-badge" icon:class="text-teal-500 dark:text-teal-400"
                            :href="route('cms.invoices.payments')" :current="request()->routeIs('cms.invoices.payments')"
                            wire:navigate>
                            {{ __('Verify Payments') }}
                        </flux:sidebar.item>
                    @endcanany

                    @can('invoices.view_personal')
                        <flux:sidebar.item icon="credit-card" icon:class="text-cyan-500 dark:text-cyan-400"
                            :href="route('cms.students.portal-invoices')"
                            :current="request()->routeIs('cms.students.portal-invoices')" wire:navigate>
                            {{ __('My Invoices') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcanany

            @canany(['attendance.take', 'attendance.manage', 'attendance.view_history', 'attendance.view_own'])
                <flux:sidebar.group :heading="__('Attendance')" class="grid" expandable expanded="false">
                    @can('attendance.take')
                        <flux:sidebar.item icon="check-badge" icon:class="text-blue-500 dark:text-blue-400"
                            :href="route('cms.attendance.take')" :current="request()->routeIs('cms.attendance.take')"
                            wire:navigate>
                            {{ __('Take Attendance') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('attendance.manage')
                        <flux:sidebar.item icon="calendar-days" icon:class="text-indigo-500 dark:text-indigo-400"
                            :href="route('cms.attendance.manage')" :current="request()->routeIs('cms.attendance.manage')"
                            wire:navigate>
                            {{ __('Manage Attendance') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('attendance.view_history')
                        <flux:sidebar.item icon="clock" icon:class="text-amber-500 dark:text-amber-400"
                            :href="route('cms.attendance.history')" :current="request()->routeIs('cms.attendance.history')"
                            wire:navigate>
                            {{ __('Attendance History') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('attendance.view_own')
                        <flux:sidebar.item icon="chart-bar" icon:class="text-rose-500 dark:text-rose-400"
                            :href="route('cms.attendance.participation')"
                            :current="request()->routeIs('cms.attendance.participation')" wire:navigate>
                            {{ __('My Attendance') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcanany
        </flux:sidebar.nav>

        <flux:spacer />


        <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden print:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <flux:dropdown position="top" align="end">
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('profile.edit')" icon="cog"
                        icon:class="text-indigo-500 dark:text-indigo-400" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                        icon:class="text-rose-500 dark:text-rose-400" class="w-full cursor-pointer"
                        data-test="logout-button">
                        {{ __('Log out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    @include('components.partials.livewire-notify-alerts')
    @stack('scripts')
    @fluxScripts
</body>

</html>