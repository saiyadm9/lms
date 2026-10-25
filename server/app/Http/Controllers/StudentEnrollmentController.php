<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentEnrollmentController extends Controller
{
    public function __construct(
        protected EnrollmentService $enrollmentService
    ) {}

    /**
     * List the authenticated student's current enrollments and waitlists.
     */
    public function index(Request $request): JsonResponse
    {
        $enrollments = Enrollment::with('course')
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [Enrollment::STATUS_ENROLLED, Enrollment::STATUS_WAITLISTED])
            ->latest()
            ->get();

        return response()->json([
            'enrollments' => $enrollments,
        ], 200);
    }

    /**
     * Enroll or join the waitlist for a course.
     */
    public function store(Request $request, Course $course): JsonResponse
    {
        $result = $this->enrollmentService->enroll($request->user(), $course);

        $status = $result['was_created'] ? 201 : 200;
        $message = $result['enrollment']->status === Enrollment::STATUS_ENROLLED
            ? ($result['was_created'] ? 'Successfully enrolled in course.' : 'Already enrolled in course.')
            : ($result['was_created'] ? 'Course is full. Added to waitlist.' : 'Already on the waitlist.');

        return response()->json([
            'message' => $message,
            'enrollment' => $result['enrollment']->load('course'),
        ], $status);
    }

    /**
     * Cancel the authenticated student's active enrollment or waitlist record.
     */
    public function destroy(Request $request, Course $course): JsonResponse
    {
        $cancelledEnrollment = $this->enrollmentService->cancel($request->user(), $course);

        return response()->json([
            'message' => 'Enrollment cancelled successfully.',
            'enrollment' => $cancelledEnrollment->load('course'),
        ], 200);
    }
}
