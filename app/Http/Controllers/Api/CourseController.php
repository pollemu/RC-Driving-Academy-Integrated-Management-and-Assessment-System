<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;

/**
 * Public course catalogue — read-only list of active courses with their
 * fees. Powers the landing page pricing cards so the advertised prices can
 * never drift from what enrollment actually charges.
 */
class CourseController extends Controller
{
    public function index()
    {
        $courses = Course::query()
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('fee')
            ->get(['id', 'name', 'type', 'fee']);

        return response()->json(['courses' => $courses]);
    }
}
