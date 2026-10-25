<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_students_competing_for_last_seat_results_in_one_enrolled_and_one_waitlisted(): void
    {
        $course = Course::factory()->create(['capacity' => 1, 'is_published' => true]);
        $service = new EnrollmentService();

        $studentA = User::factory()->create();
        $studentB = User::factory()->create();

        $resultA = $service->enroll($studentA, $course);
        $resultB = $service->enroll($studentB, $course);

        // Exactly one should be enrolled and one waitlisted
        $this->assertEquals(Enrollment::STATUS_ENROLLED, $resultA['enrollment']->status);
        $this->assertEquals(Enrollment::STATUS_WAITLISTED, $resultB['enrollment']->status);

        $this->assertEquals(1, $course->fresh()->enrolled_count);
        $this->assertEquals(1, $course->fresh()->waitlisted_count);
        $this->assertEquals(0, $course->fresh()->available_seats);
    }
}
