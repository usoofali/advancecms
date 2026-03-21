<x-mail::message>
# Attendance Submitted

A new attendance record has been submitted for the following course:

**Course:** {{ $attendance->courseAllocation->course->course_code }} - {{ $attendance->courseAllocation->course->title }}  
**Lecturer:** {{ $attendance->courseAllocation->user->name }}  
**Date:** {{ $attendance->date->format('l, M d, Y') }}  
**Session/Semester:** {{ $attendance->courseAllocation->academicSession->name }} / {{ ucfirst($attendance->courseAllocation->semester->name) }}

<x-mail::panel>
### Participation Summary
- **Total Present:** {{ $attendance->total_present }}
- **Total Absent:** {{ $attendance->total_absent }}
</x-mail::panel>

You are receiving this notification as a stakeholder in the {{ $attendance->courseAllocation->course->department->name ?? 'relevant' }} department/institution.

<x-mail::button :url="config('app.url')">
View Attendance History
</x-mail::button>

Thanks,  
{{ config('app.name') }}
</x-mail::message>
