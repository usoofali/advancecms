<?php

namespace App\Http\Controllers;

use App\Exports\FilteredResultsCsvExport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportFilteredResultsController extends Controller
{
    /**
     * Stream CSV matching the results index filters and view mode (matrix vs single course).
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $filters = [
            'institution_id' => $request->query('institution_id'),
            'department_id' => $request->query('department_id'),
            'program_id' => $request->query('program_id'),
            'session_id' => $request->query('session_id'),
            'level' => $request->query('level'),
            'semester_id' => $request->query('semester_id'),
            'course_id' => $request->query('course_id'),
            'search' => $request->query('search'),
        ];

        if ($request->user()->institution_id) {
            $filters['institution_id'] = $request->user()->institution_id;
        }

        return (new FilteredResultsCsvExport($filters))->download();
    }
}
