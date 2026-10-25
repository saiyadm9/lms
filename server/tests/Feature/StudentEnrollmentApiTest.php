<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentEnrollmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_enroll_in_course_with_open_seats_returning_201(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create(['capacity' => 2, 'is_published' => true]);

        Sanctum::actingAs($student);

        $response = $this->postJson("/api/courses/{$course->id}/enrollment");

        $response->assertStatus(201)
            ->assertJsonPath('enrollment.status', Enrollment::STATUS_ENROLLED)
            ->assertJsonPath('enrollment.user_id', $student->id);

        $this->assertEquals(1, $course->fresh()->enrolled_count);
    }

    public function test_student_is_waitlisted_when_course_is_full_returning_201(): void
    {
        $course = Course::factory()->create(['capacity' => 1, 'is_published' => true]);
        $student1 = User::factory()->create();
        $student2 = User::factory()->create();

        Sanctum::actingAs($student1);
        $this->postJson("/api/courses/{$course->id}/enrollment")->assertStatus(201);

        Sanctum::actingAs($student2);
        $response = $this->postJson("/api/courses/{$course->id}/enrollment");

        $response->assertStatus(201)
            ->assertJsonPath('enrollment.status', Enrollment::STATUS_WAITLISTED)
            ->assertJsonPath('enrollment.user_id', $student2->id);

        $this->assertEquals(1, $course->fresh()->enrolled_count);
        $this->assertEquals(1, $course->fresh()->waitlisted_count);
    }

    public function test_repeated_enrollment_request_returns_200_without_duplication(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create(['capacity' => 2, 'is_published' => true]);

        Sanctum::actingAs($student);

        $this->postJson("/api/courses/{$course->id}/enrollment")->assertStatus(201);
        $repeatResponse = $this->postJson("/api/courses/{$course->id}/enrollment");

        $repeatResponse->assertStatus(200)
            ->assertJsonPath('enrollment.status', Enrollment::STATUS_ENROLLED);

        $this->assertEquals(1, Enrollment::where('course_id', $course->id)->count());
    }

    public function test_student_can_cancel_own_enrollment_returning_200(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create(['capacity' => 2, 'is_published' => true]);

        Sanctum::actingAs($student);
        $this->postJson("/api/courses/{$course->id}/enrollment");

        $cancelResponse = $this->deleteJson("/api/courses/{$course->id}/enrollment");

        $cancelResponse->assertStatus(200)
            ->assertJsonPath('enrollment.status', Enrollment::STATUS_CANCELLED);

        $this->assertEquals(0, $course->fresh()->enrolled_count);
    }

    public function test_student_cannot_cancel_unrelated_course_or_already_cancelled_record(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create(['capacity' => 2, 'is_published' => true]);

        Sanctum::actingAs($student);

        // Has never enrolled in course
        $response = $this->deleteJson("/api/courses/{$course->id}/enrollment");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['enrollment']);
    }

    public function test_student_can_list_own_enrollments(): void
    {
        $student = User::factory()->create();
        $otherStudent = User::factory()->create();

        $course1 = Course::factory()->create(['title' => 'Course 1', 'is_published' => true]);
        $course2 = Course::factory()->create(['title' => 'Course 2', 'is_published' => true]);

        Enrollment::create(['course_id' => $course1->id, 'user_id' => $student->id, 'status' => Enrollment::STATUS_ENROLLED]);
        Enrollment::create(['course_id' => $course2->id, 'user_id' => $otherStudent->id, 'status' => Enrollment::STATUS_ENROLLED]);

        Sanctum::actingAs($student);

        $response = $this->getJson('/api/me/enrollments');

        $response->assertStatus(200);
        $enrollments = $response->json('enrollments');

        $this->assertCount(1, $enrollments);
        $this->assertEquals($course1->id, $enrollments[0]['course_id']);
    }
}
