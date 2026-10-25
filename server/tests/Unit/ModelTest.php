<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_roles_and_relationships(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isStudent());

        $this->assertTrue($student->isStudent());
        $this->assertFalse($student->isAdmin());
    }

    public function test_course_available_seats_calculation(): void
    {
        $course = Course::factory()->create(['capacity' => 3]);
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->assertEquals(3, $course->available_seats);

        Enrollment::create([
            'course_id' => $course->id,
            'user_id' => $user1->id,
            'status' => Enrollment::STATUS_ENROLLED,
            'enrolled_at' => now(),
        ]);

        $this->assertEquals(1, $course->enrolled_count);
        $this->assertEquals(2, $course->available_seats);

        Enrollment::create([
            'course_id' => $course->id,
            'user_id' => $user2->id,
            'status' => Enrollment::STATUS_WAITLISTED,
            'waitlisted_at' => now(),
        ]);

        // Waitlisted student does not consume an available seat
        $this->assertEquals(1, $course->enrolled_count);
        $this->assertEquals(1, $course->waitlisted_count);
        $this->assertEquals(2, $course->available_seats);
    }

    public function test_unique_enrollment_constraint_per_user_and_course(): void
    {
        $course = Course::factory()->create();
        $user = User::factory()->create();

        Enrollment::create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'status' => Enrollment::STATUS_ENROLLED,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Enrollment::create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'status' => Enrollment::STATUS_WAITLISTED,
        ]);
    }
}
