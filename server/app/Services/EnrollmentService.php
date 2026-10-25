<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentService
{
    /**
     * Enroll a student in a course or place them on the waitlist.
     *
     * @return array{enrollment: Enrollment, was_created: bool}
     *
     * @throws ValidationException
     */
    public function enroll(User $user, Course $course): array
    {
        return DB::transaction(function () use ($user, $course) {
            // 1. Lock the course row first (Course-first lock order)
            $lockedCourse = Course::where('id', $course->id)->lockForUpdate()->firstOrFail();

            // 2. Recheck publication status inside the transaction
            if (! $lockedCourse->is_published) {
                throw ValidationException::withMessages([
                    'course' => ['Cannot enroll in an unpublished course.'],
                ]);
            }

            // 3. Recheck existing enrollment state
            $existing = Enrollment::where('course_id', $lockedCourse->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                // If student is already enrolled or waitlisted, return existing state without modifications
                if ($existing->status === Enrollment::STATUS_ENROLLED || $existing->status === Enrollment::STATUS_WAITLISTED) {
                    return [
                        'enrollment' => $existing,
                        'was_created' => false,
                    ];
                }

                // If existing record was cancelled, reactivate it
                $enrolledCount = Enrollment::where('course_id', $lockedCourse->id)
                    ->where('status', Enrollment::STATUS_ENROLLED)
                    ->count();

                if ($enrolledCount < $lockedCourse->capacity) {
                    $existing->update([
                        'status' => Enrollment::STATUS_ENROLLED,
                        'enrolled_at' => now(),
                        'waitlisted_at' => null,
                        'cancelled_at' => null,
                    ]);
                } else {
                    // Full course: assign fresh waitlisted_at timestamp so student joins end of queue
                    $existing->update([
                        'status' => Enrollment::STATUS_WAITLISTED,
                        'enrolled_at' => null,
                        'waitlisted_at' => now(),
                        'cancelled_at' => null,
                    ]);
                }

                return [
                    'enrollment' => $existing->fresh(),
                    'was_created' => true,
                ];
            }

            // 4. Calculate available seats and create record
            $enrolledCount = Enrollment::where('course_id', $lockedCourse->id)
                ->where('status', Enrollment::STATUS_ENROLLED)
                ->count();

            try {
                if ($enrolledCount < $lockedCourse->capacity) {
                    $enrollment = Enrollment::create([
                        'course_id' => $lockedCourse->id,
                        'user_id' => $user->id,
                        'status' => Enrollment::STATUS_ENROLLED,
                        'enrolled_at' => now(),
                        'waitlisted_at' => null,
                        'cancelled_at' => null,
                    ]);
                } else {
                    $enrollment = Enrollment::create([
                        'course_id' => $lockedCourse->id,
                        'user_id' => $user->id,
                        'status' => Enrollment::STATUS_WAITLISTED,
                        'waitlisted_at' => now(),
                        'enrolled_at' => null,
                        'cancelled_at' => null,
                    ]);
                }

                return [
                    'enrollment' => $enrollment,
                    'was_created' => true,
                ];
            } catch (UniqueConstraintViolationException $e) {
                // Handle concurrent duplicate insertion gracefully
                $current = Enrollment::where('course_id', $lockedCourse->id)
                    ->where('user_id', $user->id)
                    ->firstOrFail();

                return [
                    'enrollment' => $current,
                    'was_created' => false,
                ];
            }
        }, 3);
    }

    /**
     * Cancel a student's active enrollment or waitlist position.
     *
     * @throws ValidationException
     */
    public function cancel(User $user, Course $course): Enrollment
    {
        return DB::transaction(function () use ($user, $course) {
            // 1. Lock the course row first
            $lockedCourse = Course::where('id', $course->id)->lockForUpdate()->firstOrFail();

            // 2. Fetch the student's enrollment
            $enrollment = Enrollment::where('course_id', $lockedCourse->id)
                ->where('user_id', $user->id)
                ->first();

            if (! $enrollment || $enrollment->status === Enrollment::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'enrollment' => ['No active enrollment or waitlist record to cancel.'],
                ]);
            }

            $wasEnrolled = ($enrollment->status === Enrollment::STATUS_ENROLLED);

            // 3. Mark as cancelled
            $enrollment->update([
                'status' => Enrollment::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            // 4. Immediate FIFO waitlist promotion if an enrolled seat was freed
            if ($wasEnrolled) {
                $this->promoteWaitlistedStudents($lockedCourse);
            }

            return $enrollment->fresh();
        }, 3);
    }

    /**
     * Promote eligible waitlisted students in deterministic FIFO order.
     * Ordering: waitlisted_at ASC, id ASC.
     */
    public function promoteWaitlistedStudents(Course $course): int
    {
        $enrolledCount = Enrollment::where('course_id', $course->id)
            ->where('status', Enrollment::STATUS_ENROLLED)
            ->count();

        $availableSeats = max(0, $course->capacity - $enrolledCount);
        if ($availableSeats <= 0) {
            return 0;
        }

        // Select only records with status = waitlisted, ordered deterministically
        $eligibleWaiters = Enrollment::where('course_id', $course->id)
            ->where('status', Enrollment::STATUS_WAITLISTED)
            ->orderBy('waitlisted_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit($availableSeats)
            ->get();

        $promotedCount = 0;
        foreach ($eligibleWaiters as $waiter) {
            $waiter->update([
                'status' => Enrollment::STATUS_ENROLLED,
                'enrolled_at' => now(),
                'waitlisted_at' => null,
                'cancelled_at' => null,
            ]);
            $promotedCount++;
        }

        return $promotedCount;
    }

    /**
     * Dedicated capacity update operation with pessimistic locking and automatic promotion.
     *
     * @throws ValidationException
     */
    public function updateCapacity(Course $course, int $newCapacity): Course
    {
        if ($newCapacity < 1) {
            throw ValidationException::withMessages([
                'capacity' => ['Capacity must be a positive integer.'],
            ]);
        }

        return DB::transaction(function () use ($course, $newCapacity) {
            // 1. Lock the course row
            $lockedCourse = Course::where('id', $course->id)->lockForUpdate()->firstOrFail();

            // 2. Update capacity
            $lockedCourse->update([
                'capacity' => $newCapacity,
            ]);

            // 3. Promote eligible waitlisted students if new capacity permits
            $this->promoteWaitlistedStudents($lockedCourse);

            return $lockedCourse->fresh();
        }, 3);
    }
}
