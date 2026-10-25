<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_courses_endpoint_only_returns_published_courses(): void
    {
        Course::factory()->create(['title' => 'Published Course A', 'is_published' => true]);
        Course::factory()->create(['title' => 'Published Course B', 'is_published' => true]);
        Course::factory()->create(['title' => 'Draft Course C', 'is_published' => false]);

        $response = $this->getJson('/api/courses');

        $response->assertStatus(200);
        $courses = $response->json('courses');

        $this->assertCount(2, $courses);
        $titles = collect($courses)->pluck('title')->all();
        $this->assertContains('Published Course A', $titles);
        $this->assertContains('Published Course B', $titles);
        $this->assertNotContains('Draft Course C', $titles);
    }

    public function test_public_courses_returns_correct_available_seats_and_counts(): void
    {
        $course = Course::factory()->create([
            'title' => 'Concurrency 101',
            'capacity' => 2,
            'is_published' => true,
        ]);

        $student1 = User::factory()->create();
        $student2 = User::factory()->create();
        $student3 = User::factory()->create();

        Enrollment::create(['course_id' => $course->id, 'user_id' => $student1->id, 'status' => Enrollment::STATUS_ENROLLED, 'enrolled_at' => now()]);
        Enrollment::create(['course_id' => $course->id, 'user_id' => $student2->id, 'status' => Enrollment::STATUS_ENROLLED, 'enrolled_at' => now()]);
        Enrollment::create(['course_id' => $course->id, 'user_id' => $student3->id, 'status' => Enrollment::STATUS_WAITLISTED, 'waitlisted_at' => now()]);

        $response = $this->getJson('/api/courses');

        $response->assertStatus(200);
        $courseData = collect($response->json('courses'))->firstWhere('id', $course->id);

        $this->assertEquals(2, $courseData['capacity']);
        $this->assertEquals(2, $courseData['enrolled_count']);
        $this->assertEquals(1, $courseData['waitlisted_count']);
        $this->assertEquals(0, $courseData['available_seats']);
    }

    public function test_viewing_published_course_details_succeeds(): void
    {
        $course = Course::factory()->create(['is_published' => true]);

        $response = $this->getJson("/api/courses/{$course->id}");

        $response->assertStatus(200)
            ->assertJsonPath('course.id', $course->id);
    }

    public function test_viewing_unpublished_course_details_returns_404(): void
    {
        $course = Course::factory()->unpublished()->create();

        $response = $this->getJson("/api/courses/{$course->id}");

        $response->assertStatus(404);
    }
}
