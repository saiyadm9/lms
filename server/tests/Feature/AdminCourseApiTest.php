<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCourseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_all_courses_including_unpublished(): void
    {
        $admin = User::factory()->admin()->create();

        Course::factory()->create(['title' => 'Published 1', 'is_published' => true]);
        Course::factory()->create(['title' => 'Draft 2', 'is_published' => false]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/courses');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('courses'));
    }

    public function test_student_cannot_access_admin_courses_returning_403(): void
    {
        $student = User::factory()->create();

        Sanctum::actingAs($student);

        $this->getJson('/api/admin/courses')->assertStatus(403);
        $this->postJson('/api/admin/courses', ['title' => 'Hacked', 'capacity' => 10])->assertStatus(403);
    }

    public function test_admin_can_create_new_course(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/courses', [
            'title' => 'Advanced Operating Systems',
            'capacity' => 25,
            'is_published' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('course.title', 'Advanced Operating Systems')
            ->assertJsonPath('course.capacity', 25)
            ->assertJsonPath('course.is_published', true);

        $this->assertDatabaseHas('courses', [
            'title' => 'Advanced Operating Systems',
            'capacity' => 25,
        ]);
    }

    public function test_course_creation_validates_positive_capacity(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/courses', [
            'title' => 'Bad Capacity',
            'capacity' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['capacity']);
    }

    public function test_admin_can_update_course_capacity_triggering_waitlist_promotion(): void
    {
        $admin = User::factory()->admin()->create();
        $course = Course::factory()->create(['capacity' => 1, 'is_published' => true]);

        $student1 = User::factory()->create();
        $student2 = User::factory()->create();

        Enrollment::create(['course_id' => $course->id, 'user_id' => $student1->id, 'status' => Enrollment::STATUS_ENROLLED, 'enrolled_at' => now()]);
        Enrollment::create(['course_id' => $course->id, 'user_id' => $student2->id, 'status' => Enrollment::STATUS_WAITLISTED, 'waitlisted_at' => now()]);

        Sanctum::actingAs($admin);

        // Increase capacity to 2
        $response = $this->patchJson("/api/admin/courses/{$course->id}", [
            'capacity' => 2,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('course.capacity', 2)
            ->assertJsonPath('course.enrolled_count', 2)
            ->assertJsonPath('course.waitlisted_count', 0);

        $this->assertEquals(Enrollment::STATUS_ENROLLED, Enrollment::where('user_id', $student2->id)->value('status'));
    }

    public function test_admin_can_toggle_course_publication(): void
    {
        $admin = User::factory()->admin()->create();
        $course = Course::factory()->unpublished()->create();

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/courses/{$course->id}", [
            'is_published' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('course.is_published', true);

        $this->assertTrue($course->fresh()->is_published);
    }

    public function test_admin_can_delete_course(): void
    {
        $admin = User::factory()->admin()->create();
        $course = Course::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/admin/courses/{$course->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Course deleted successfully.');

        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    public function test_student_cannot_delete_course(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();

        Sanctum::actingAs($student);

        $response = $this->deleteJson("/api/admin/courses/{$course->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }
}
