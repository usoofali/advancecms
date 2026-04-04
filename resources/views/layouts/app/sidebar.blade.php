<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:sidebar sticky collapsible="mobile"
        class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 print:hidden">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.group :heading="__('Overview')" class="grid">
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')"
                    wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            <flux:sidebar.group :heading="__('Administration')" class="grid" expandable expanded="false">
                @can('manage_institutions')
                <flux:sidebar.item icon="building-office-2" :href="route('cms.institutions.index')"
                    :current="request()->routeIs('cms.institutions.*')" wire:navigate>
                    {{ __('Institutions') }}
                </flux:sidebar.item>
                @endcan

                @can('manage_departments')
                <flux:sidebar.item icon="square-3-stack-3d" :href="route('cms.departments.index')"
                    :current="request()->routeIs('cms.departments.*')" wire:navigate>
                    {{ __('Departments') }}
                </flux:sidebar.item>
                @endcan

                @can('manage_programs')
                <flux:sidebar.item icon="academic-cap" :href="route('cms.programs.index')"
                    :current="request()->routeIs('cms.programs.*')" wire:navigate>
                    {{ __('Programs') }}
                </flux:sidebar.item>
                @endcan

                @can('view_applications')
                <flux:sidebar.item icon="document-magnifying-glass" :href="route('cms.admissions.index')"
                    :current="request()->routeIs('cms.admissions.index') || request()->routeIs('cms.admissions.show')" wire:navigate>
                    {{ __('Applications') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="document-text" :href="route('cms.admissions.issue-notification')"
                    :current="request()->routeIs('cms.admissions.issue-notification')" wire:navigate>
                    {{ __('Issue notification') }}
                </flux:sidebar.item>
                @endcan

                @can('manage_application_forms')
                <flux:sidebar.item icon="document-plus" :href="route('cms.admissions.forms.index')"
                    :current="request()->routeIs('cms.admissions.forms.*')" wire:navigate>
                    {{ __('Admission Forms') }}
                </flux:sidebar.item>
                @endcan

                @can('manage_staff')
                <flux:sidebar.item icon="user-group" :href="route('cms.staff.index')"
                    :current="request()->routeIs('cms.staff.*')" wire:navigate>
                    {{ __('Staff') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="identification" :href="route('cms.id-cards.manage')"
                    :current="request()->routeIs('cms.id-cards.manage')" wire:navigate>
                    {{ __('ID Card Management') }}
                </flux:sidebar.item>
                @endcan

                @can('manage_configurations')
                <flux:sidebar.item icon="calendar-date-range" :href="route('cms.sessions.index')"
                    :current="request()->routeIs('cms.sessions.*')" wire:navigate>
                    {{ __('Sessions') }}
                </flux:sidebar.item>
                @endcan

                @can('manage_roles')
                <flux:sidebar.item icon="shield-check" :href="route('cms.roles.index')"
                    :current="request()->routeIs('cms.roles.*')" wire:navigate>
                    {{ __('Roles & Permissions') }}
                </flux:sidebar.item>
                @endcan
            </flux:sidebar.group>

            <flux:sidebar.group :heading="__('Academic')" class="grid" expandable expanded="false">
                @canany(['view_dept_students', 'manage_staff', 'view_all_data'])
                <flux:sidebar.item icon="users" :href="route('cms.students.index')"
                    :current="request()->routeIs('cms.students.index') || request()->routeIs('cms.students.create') || request()->routeIs('cms.students.edit')"
                    wire:navigate>
                    {{ __('Students') }}
                </flux:sidebar.item>
                @endcanany

                @can('manage_registrations')
                <flux:sidebar.item icon="clipboard-document-check" :href="route('cms.students.registration')"
                    :current="request()->routeIs('cms.students.registration')" wire:navigate>
                    {{ __('Course Registration') }}
                </flux:sidebar.item>
                @endcan

                @can('manage_registration_status')
                <flux:sidebar.item icon="lock-closed" :href="route('cms.students.manage-registrations')"
                    :current="request()->routeIs('cms.students.manage-registrations')" wire:navigate>
                    {{ __('Manage Registrations') }}
                </flux:sidebar.item>
                @endcan

                @can('view_personal_registrations')
                <flux:sidebar.item icon="clipboard-document-check" :href="route('cms.students.portal-registration')"
                    :current="request()->routeIs('cms.students.portal-registration')" wire:navigate>
                    {{ __('My Courses') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="document-text" :href="route('cms.students.course-form')"
                    :current="request()->routeIs('cms.students.course-form')" wire:navigate>
                    {{ __('Course Form') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="identification" :href="route('cms.students.exam-card')"
                    :current="request()->routeIs('cms.students.exam-card')" wire:navigate>
                    {{ __('Examination Card') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="user-circle" :href="route('cms.students.my-lecturers')"
                    :current="request()->routeIs('cms.students.my-lecturers')" wire:navigate>
                    {{ __('My Lecturers') }}
                </flux:sidebar.item>
                @endcan

                @canany(['manage_courses', 'view_dept_courses'])
                <flux:sidebar.item icon="book-open" :href="route('cms.courses.index')"
                    :current="request()->routeIs('cms.courses.index') || request()->routeIs('cms.courses.create') || request()->routeIs('cms.courses.edit')"
                    wire:navigate>
                    {{ __('Courses') }}
                </flux:sidebar.item>
                @endcanany

                @can('manage_courses')
                <flux:sidebar.item icon="clipboard-document-check" :href="route('cms.courses.allocations')"
                    :current="request()->routeIs('cms.courses.allocations')" wire:navigate>
                    {{ __('Manage Allocations') }}
                </flux:sidebar.item>
                @endcan

                @can('view_assigned_courses')
                <flux:sidebar.item icon="clipboard-document-check" :href="route('cms.courses.my-allocations')"
                    :current="request()->routeIs('cms.courses.my-allocations')" wire:navigate>
                    {{ __('My Allocations') }}
                </flux:sidebar.item>
                @endcan

                @can('take_attendance')
                <flux:sidebar.item icon="check-badge" :href="route('cms.attendance.take')"
                    :current="request()->routeIs('cms.attendance.take')" wire:navigate>
                    {{ __('Take Attendance') }}
                </flux:sidebar.item>
                @endcan

                @can('view_assigned_courses')
                <flux:sidebar.item icon="clock" :href="route('cms.attendance.history')"
                    :current="request()->routeIs('cms.attendance.history')" wire:navigate>
                    {{ __('Attendance History') }}
                </flux:sidebar.item>
                @endcan
                <flux:sidebar.item icon="identification" :href="route('cms.id-cards.request')"
                    :current="request()->routeIs('cms.id-cards.request')" wire:navigate>
                    {{ __('Request ID Card') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            <flux:sidebar.group :heading="__('Results')" class="grid" expandable expanded="false">
                @can('enter_results')
                <flux:sidebar.item icon="pencil-square" :href="route('cms.results.entry')"
                    :current="request()->routeIs('cms.results.entry')" wire:navigate>
                    {{ __('Enter Results') }}
                </flux:sidebar.item>
                @endcan

                @canany(['view_dept_results', 'view_all_data'])
                <flux:sidebar.item icon="chart-bar" :href="route('cms.results.index')"
                    :current="request()->routeIs('cms.results.index')" wire:navigate>
                    {{ __('View Results') }}
                </flux:sidebar.item>
                @endcanany

                @can('generate_reports')
                <flux:sidebar.item icon="academic-cap" :href="route('cms.results.transcripts')"
                    :current="request()->routeIs('cms.results.transcripts')" wire:navigate>
                    {{ __('Transcript Manager') }}
                </flux:sidebar.item>
                @endcan

                @can('view_personal_results')
                <flux:sidebar.item icon="identification" :href="route('cms.results.portal')"
                    :current="request()->routeIs('cms.results.portal')" wire:navigate>
                    {{ __('Semester Results') }}
                </flux:sidebar.item>
                @endcan
            </flux:sidebar.group>

            <flux:sidebar.group :heading="__('Finance')" class="grid" expandable expanded="false">
                @can('manage_attendance_payments')
                <flux:sidebar.item icon="banknotes" :href="route('cms.attendance.payments')"
                    :current="request()->routeIs('cms.attendance.payments')" wire:navigate>
                    {{ __('Attendance Payments') }}
                </flux:sidebar.item>
                @endcan

                @can('view_payments')
                <flux:sidebar.item icon="banknotes" :href="route('cms.invoices.index')"
                    :current="request()->routeIs('cms.invoices.index') || request()->routeIs('cms.invoices.create') || request()->routeIs('cms.invoices.edit')"
                    wire:navigate>
                    {{ __('Invoice Management') }}
                </flux:sidebar.item>
                @endcan

                @can('record_payments')
                <flux:sidebar.item icon="check-badge" :href="route('cms.invoices.payments')"
                    :current="request()->routeIs('cms.invoices.payments')" wire:navigate>
                    {{ __('Verify Payments') }}
                </flux:sidebar.item>
                @endcan

                @can('view_personal_registrations')
                <flux:sidebar.item icon="credit-card" :href="route('cms.students.portal-invoices')"
                    :current="request()->routeIs('cms.students.portal-invoices')" wire:navigate>
                    {{ __('My Invoices') }}
                </flux:sidebar.item>
                @endcan
            </flux:sidebar.group>
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
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer" data-test="logout-button">
                        {{ __('Log out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    @include('components.partials.flash-alerts')
    @include('components.partials.livewire-notify-alerts')
    @stack('scripts')
    @fluxScripts
</body>

</html>
