<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCourseController extends Controller
{
    public function __construct(
        protected EnrollmentService $enrollmentService
    ) {}

    /**
     * List all courses, including unpublished drafts, with enrollment statistics.
     */
    public function index(Request $request): JsonResponse
    {
        $courses = Course::withCount([
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
     * Create a new course.
     */
    public function store(StoreCourseRequest $request): JsonResponse
    {
        $course = Course::create([
            'title' => $request->title,
            'capacity' => $request->capacity,
            'is_published' => $request->boolean('is_published', false),
        ]);

        return response()->json([
            'message' => 'Course created successfully.',
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'capacity' => $course->capacity,
                'is_published' => (bool) $course->is_published,
                'enrolled_count' => 0,
                'waitlisted_count' => 0,
                'available_seats' => $course->capacity,
                'created_at' => $course->created_at,
                'updated_at' => $course->updated_at,
            ],
        ], 201);
    }

    /**
     * Update course details, capacity, or publication status.
     * Capacity updates trigger automatic FIFO waitlist promotion via EnrollmentService.
     */
    public function update(UpdateCourseRequest $request, Course $course): JsonResponse
    {
        // 1. If capacity is being updated, use EnrollmentService dedicated method
        if ($request->has('capacity') && (int) $request->capacity !== $course->capacity) {
            $course = $this->enrollmentService->updateCapacity($course, (int) $request->capacity);
        }

        // 2. Update remaining fields
        $attributesToUpdate = [];
        if ($request->has('title')) {
            $attributesToUpdate['title'] = $request->title;
        }
        if ($request->has('is_published')) {
            $attributesToUpdate['is_published'] = $request->boolean('is_published');
        }

        if (! empty($attributesToUpdate)) {
            $course->update($attributesToUpdate);
        }

        $enrolledCount = $course->activeEnrollments()->count();
        $waitlistedCount = $course->waitlistedEnrollments()->count();

        return response()->json([
            'message' => 'Course updated successfully.',
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

    /**
     * Delete a course and cascade delete its associated enrollments.
     */
    public function destroy(Course $course): JsonResponse
    {
        $course->delete();

        return response()->json([
            'message' => 'Course deleted successfully.',
        ], 200);
    }
}
