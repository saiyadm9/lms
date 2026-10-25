<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EnrollmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private EnrollmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EnrollmentService();
    }

    public function test_enrollment_when_seat_is_available(): void
    {
        $course = Course::factory()->create(['capacity' => 2, 'is_published' => true]);
        $user = User::factory()->create();

        $result = $this->service->enroll($user, $course);

        $this->assertTrue($result['was_created']);
        $this->assertEquals(Enrollment::STATUS_ENROLLED, $result['enrollment']->status);
        $this->assertNotNull($result['enrollment']->enrolled_at);
        $this->assertNull($result['enrollment']->waitlisted_at);
        $this->assertEquals(1, $course->fresh()->enrolled_count);
        $this->assertEquals(1, $course->fresh()->available_seats);
    }

    public function test_waitlisting_when_course_is_full(): void
    {
        $course = Course::factory()->create(['capacity' => 1, 'is_published' => true]);
        $student1 = User::factory()->create();
        $student2 = User::factory()->create();

        $this->service->enroll($student1, $course);
        $result = $this->service->enroll($student2, $course);

        $this->assertTrue($result['was_created']);
        $this->assertEquals(Enrollment::STATUS_WAITLISTED, $result['enrollment']->status);
        $this->assertNotNull($result['enrollment']->waitlisted_at);
        $this->assertNull($result['enrollment']->enrolled_at);
        $this->assertEquals(1, $course->fresh()->enrolled_count);
        $this->assertEquals(1, $course->fresh()->waitlisted_count);
        $this->assertEquals(0, $course->fresh()->available_seats);
    }

    public function test_duplicate_enrollment_request_preserves_existing_state(): void
    {
        $course = Course::factory()->create(['capacity' => 2, 'is_published' => true]);
        $student = User::factory()->create();

        $initial = $this->service->enroll($student, $course);
        $this->assertTrue($initial['was_created']);

        // Duplicate enrollment request
        $duplicate = $this->service->enroll($student, $course);
        $this->assertFalse($duplicate['was_created']);
        $this->assertEquals($initial['enrollment']->id, $duplicate['enrollment']->id);
        $this->assertEquals(1, Enrollment::where('course_id', $course->id)->count());
    }

    public function test_duplicate_waitlist_request_preserves_queue_position(): void
    {
        $course = Course::factory()->create(['capacity' => 1, 'is_published' => true]);
        $student1 = User::factory()->create();
        $student2 = User::factory()->create();

        $this->service->enroll($student1, $course);
        $waitlisted = $this->service->enroll($student2, $course);
        $originalWaitlistedAt = $waitlisted['enrollment']->waitlisted_at;

        // Duplicate request after some time
        $this->travel(5)->minutes();
        $duplicate = $this->service->enroll($student2, $course);

        $this->assertFalse($duplicate['was_created']);
        $this->assertEquals($originalWaitlistedAt->toDateTimeString(), $duplicate['enrollment']->waitlisted_at->toDateTimeString());
    }

    public function test_cancellation_of_enrolled_student_frees_seat_and_promotes_waitlist_fifo(): void
    {
        $course = Course::factory()->create(['capacity' => 1, 'is_published' => true]);
        $student1 = User::factory()->create();
        $student2 = User::factory()->create();
        $student3 = User::factory()->create();

        $this->service->enroll($student1, $course); // Enrolled

        $this->travel(2)->minutes();
        $this->service->enroll($student2, $course); // Waitlist 1st (earliest)

        $this->travel(2)->minutes();
        $this->service->enroll($student3, $course); // Waitlist 2nd

        // Student 1 cancels
        $cancelledEnrollment = $this->service->cancel($student1, $course);

        $this->assertEquals(Enrollment::STATUS_CANCELLED, $cancelledEnrollment->status);
        $this->assertNotNull($cancelledEnrollment->cancelled_at);

        // Student 2 should have been promoted automatically (deterministic FIFO)
        $enrollment2 = Enrollment::where('course_id', $course->id)->where('user_id', $student2->id)->first();
        $this->assertEquals(Enrollment::STATUS_ENROLLED, $enrollment2->status);
        $this->assertNotNull($enrollment2->enrolled_at);
        $this->assertNull($enrollment2->waitlisted_at);

        // Student 3 remains waitlisted
        $enrollment3 = Enrollment::where('course_id', $course->id)->where('user_id', $student3->id)->first();
        $this->assertEquals(Enrollment::STATUS_WAITLISTED, $enrollment3->status);
    }

    public function test_repeated_cancellation_fails_and_does_not_double_promote(): void
    {
        $course = Course::factory()->create(['capacity' => 1, 'is_published' => true]);
        $student1 = User::factory()->create();
        $student2 = User::factory()->create();
        $student3 = User::factory()->create();

        $this->service->enroll($student1, $course);
        $this->service->enroll($student2, $course);
        $this->service->enroll($student3, $course);

        // Cancel once: student2 promoted
        $this->service->cancel($student1, $course);
        $this->assertEquals(Enrollment::STATUS_ENROLLED, Enrollment::where('user_id', $student2->id)->value('status'));
        $this->assertEquals(Enrollment::STATUS_WAITLISTED, Enrollment::where('user_id', $student3->id)->value('status'));

        // Attempt second cancellation by student 1
        $this->expectException(ValidationException::class);
        $this->service->cancel($student1, $course);

        // Verify student 3 was NOT erroneously promoted
        $this->assertEquals(Enrollment::STATUS_WAITLISTED, Enrollment::where('user_id', $student3->id)->value('status'));
    }

    public function test_cancelling_waitlist_position_does_not_affect_enrolled_count(): void
    {
        $course = Course::factory()->create(['capacity' => 1, 'is_published' => true]);
        $student1 = User::factory()->create();
        $student2 = User::factory()->create();

        $this->service->enroll($student1, $course);
        $this->service->enroll($student2, $course);

        $this->assertEquals(1, $course->fresh()->enrolled_count);
        $this->assertEquals(1, $course->fresh()->waitlisted_count);

        // Waitlisted student cancels
        $cancelled = $this->service->cancel($student2, $course);

        $this->assertEquals(Enrollment::STATUS_CANCELLED, $cancelled->status);
        $this->assertEquals(1, $course->fresh()->enrolled_count);
        $this->assertEquals(0, $course->fresh()->waitlisted_count);
    }

    public function test_cancelled_student_rejoining_joins_end_of_waitlist_queue(): void
    {
        $course = Course::factory()->create(['capacity' => 1, 'is_published' => true]);
        $student1 = User::factory()->create();
        $student2 = User::factory()->create();

        $this->service->enroll($student1, $course);
        $this->service->enroll($student2, $course);

        // Student 2 cancels waitlist
        $this->service->cancel($student2, $course);

        // Student 3 joins waitlist
        $student3 = User::factory()->create();
        $this->travel(5)->minutes();
        $this->service->enroll($student3, $course);

        // Student 2 re-joins after student 3
        $this->travel(5)->minutes();
        $rejoined = $this->service->enroll($student2, $course);

        $this->assertTrue($rejoined['was_created']);
        $this->assertEquals(Enrollment::STATUS_WAITLISTED, $rejoined['enrollment']->status);

        // Verify student 3 is ahead of student 2
        $enrollment3 = Enrollment::where('user_id', $student3->id)->first();
        $this->assertTrue($enrollment3->waitlisted_at->lt($rejoined['enrollment']->waitlisted_at));
    }

    public function test_capacity_increase_promotes_waitlisted_students_fifo(): void
    {
        $course = Course::factory()->create(['capacity' => 1, 'is_published' => true]);
        $student1 = User::factory()->create();
        $student2 = User::factory()->create();
        $student3 = User::factory()->create();

        $this->service->enroll($student1, $course);

        $this->travel(1)->minutes();
        $this->service->enroll($student2, $course);

        $this->travel(1)->minutes();
        $this->service->enroll($student3, $course);

        $this->assertEquals(1, $course->fresh()->enrolled_count);
        $this->assertEquals(2, $course->fresh()->waitlisted_count);

        // Increase capacity from 1 to 2 -> should promote student2, leave student3 waitlisted
        $this->service->updateCapacity($course, 2);

        $this->assertEquals(Enrollment::STATUS_ENROLLED, Enrollment::where('user_id', $student2->id)->value('status'));
        $this->assertEquals(Enrollment::STATUS_WAITLISTED, Enrollment::where('user_id', $student3->id)->value('status'));
        $this->assertEquals(2, $course->fresh()->enrolled_count);
        $this->assertEquals(1, $course->fresh()->waitlisted_count);
        $this->assertEquals(0, $course->fresh()->available_seats);
    }

    public function test_capacity_decrease_preserves_existing_enrollments(): void
    {
        $course = Course::factory()->create(['capacity' => 3, 'is_published' => true]);
        $students = User::factory()->count(3)->create();

        foreach ($students as $student) {
            $this->service->enroll($student, $course);
        }

        $this->assertEquals(3, $course->fresh()->enrolled_count);

        // Decrease capacity from 3 to 1
        $this->service->updateCapacity($course, 1);

        $this->assertEquals(1, $course->fresh()->capacity);
        $this->assertEquals(3, $course->fresh()->enrolled_count);
        $this->assertEquals(0, $course->fresh()->available_seats);

        // None of the enrollments were deleted or cancelled
        $enrolledCount = Enrollment::where('course_id', $course->id)->where('status', Enrollment::STATUS_ENROLLED)->count();
        $this->assertEquals(3, $enrolledCount);
    }

    public function test_unpublished_course_rejects_enrollment(): void
    {
        $course = Course::factory()->unpublished()->create(['capacity' => 5]);
        $student = User::factory()->create();

        $this->expectException(ValidationException::class);
        $this->service->enroll($student, $course);
    }

    public function test_student_can_cancel_enrollment_in_unpublished_course(): void
    {
        $course = Course::factory()->create(['capacity' => 2, 'is_published' => true]);
        $student = User::factory()->create();

        $this->service->enroll($student, $course);

        // Unpublish course
        $course->update(['is_published' => false]);

        // Cancellation should still succeed
        $cancelled = $this->service->cancel($student, $course);
        $this->assertEquals(Enrollment::STATUS_CANCELLED, $cancelled->status);
    }

    public function test_invalid_capacity_rejects_with_validation_exception(): void
    {
        $course = Course::factory()->create(['capacity' => 5]);

        $this->expectException(ValidationException::class);
        $this->service->updateCapacity($course, 0);
    }
}
