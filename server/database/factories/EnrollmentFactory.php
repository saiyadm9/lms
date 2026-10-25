<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'user_id' => User::factory(),
            'status' => Enrollment::STATUS_ENROLLED,
            'enrolled_at' => now(),
            'waitlisted_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function waitlisted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Enrollment::STATUS_WAITLISTED,
            'enrolled_at' => null,
            'waitlisted_at' => now(),
            'cancelled_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Enrollment::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }
}
