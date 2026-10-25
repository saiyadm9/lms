<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    /**
     * List published courses with calculated capacity, enrolled count, and available seats.
     */
    public function index(Request $request): JsonResponse
    {
        $courses = Course::published()
            ->withCount([
                'enrollments as enrolled_count' => function ($query) {
                    $query->where('status', Enrollment::STATUS_ENROLLED);
                },
                'enrollments as waitlisted_count' => function ($query) {
                    $query->where('status', Enrollment::STATUS_WAITLISTED);
                },
            ])
            ->latest()
            ->get()
            ->map(function (Course $course) {
                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'capacity' => $course->capacity,
                    'is_published' => (bool) $course->is_published,
                    'enrolled_count' => (int) $course->enrolled_count,
                    'waitlisted_count' => (int) $course->waitlisted_count,
                    'available_seats' => max(0, $course->capacity - (int) $course->enrolled_count),
                    'created_at' => $course->created_at,
                    'updated_at' => $course->updated_at,
                ];
            });

        return response()->json([
            'courses' => $courses,
        ], 200);
    }

    /**
     * View details of a single published course.
     */
    public function show(Course $course): JsonResponse
    {
        if (! $course->is_published) {
            abort(404, 'Course not found or is unpublished.');
        }

        $enrolledCount = $course->activeEnrollments()->count();
        $waitlistedCount = $course->waitlistedEnrollments()->count();

        return response()->json([
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'capacity' => $course->capacity,
                'is_published' => (bool) $course->is_published,
                'enrolled_count' => $enrolledCount,
                'waitlisted_count' => $waitlistedCount,
                'available_seats' => max(0, $course->capacity - $enrolledCount),
                'created_at' => $course->created_at,
                'updated_at' => $course->updated_at,
            ],
        ], 200);
    }
}
