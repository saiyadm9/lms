<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Demo Administrator (Do not expose public admin registration)
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Demo Administrator',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
            ]
        );

        // 2. Create Demo Student & Cohort Students
        $demoStudent = User::firstOrCreate(
            ['email' => 'student@example.com'],
            [
                'name' => 'Demo Student',
                'password' => Hash::make('password'),
                'role' => User::ROLE_STUDENT,
            ]
        );

        $studentAlex = User::firstOrCreate(
            ['email' => 'alex@example.com'],
            [
                'name' => 'Alex Rivera',
                'password' => Hash::make('password'),
                'role' => User::ROLE_STUDENT,
            ]
        );

        $studentSamantha = User::firstOrCreate(
            ['email' => 'samantha@example.com'],
            [
                'name' => 'Samantha Lee',
                'password' => Hash::make('password'),
                'role' => User::ROLE_STUDENT,
            ]
        );

        $studentMarcus = User::firstOrCreate(
            ['email' => 'marcus@example.com'],
            [
                'name' => 'Marcus Chen',
                'password' => Hash::make('password'),
                'role' => User::ROLE_STUDENT,
            ]
        );

        // 3. Create Sample Courses
        $course1 = Course::firstOrCreate(
            ['title' => 'Full-Stack React & Laravel'],
            ['capacity' => 3, 'is_published' => true]
        );

        $course2 = Course::firstOrCreate(
            ['title' => 'Database Design & Concurrency'],
            ['capacity' => 2, 'is_published' => true]
        );

        $course3 = Course::firstOrCreate(
            ['title' => 'Cloud Infrastructure & DevOps'],
            ['capacity' => 5, 'is_published' => true]
        );

        $course4 = Course::firstOrCreate(
            ['title' => 'Algorithms & System Design'],
            ['capacity' => 2, 'is_published' => true]
        );

        $course5 = Course::firstOrCreate(
            ['title' => 'Internal Systems Architecture (Draft)'],
            ['capacity' => 10, 'is_published' => false]
        );

        // 4. Sample Enrollments
        // Course 1: 2/3 enrolled
        Enrollment::firstOrCreate(
            ['course_id' => $course1->id, 'user_id' => $demoStudent->id],
            ['status' => Enrollment::STATUS_ENROLLED, 'enrolled_at' => now()->subDays(2)]
        );
        Enrollment::firstOrCreate(
            ['course_id' => $course1->id, 'user_id' => $studentAlex->id],
            ['status' => Enrollment::STATUS_ENROLLED, 'enrolled_at' => now()->subDay()]
        );

        // Course 2: 2/2 enrolled (FULL), 1 waitlisted (Marcus)
        Enrollment::firstOrCreate(
            ['course_id' => $course2->id, 'user_id' => $studentAlex->id],
            ['status' => Enrollment::STATUS_ENROLLED, 'enrolled_at' => now()->subDays(3)]
        );
        Enrollment::firstOrCreate(
            ['course_id' => $course2->id, 'user_id' => $studentSamantha->id],
            ['status' => Enrollment::STATUS_ENROLLED, 'enrolled_at' => now()->subDays(2)]
        );
        Enrollment::firstOrCreate(
            ['course_id' => $course2->id, 'user_id' => $studentMarcus->id],
            ['status' => Enrollment::STATUS_WAITLISTED, 'waitlisted_at' => now()->subDay()]
        );

        // Course 3: 1/5 enrolled
        Enrollment::firstOrCreate(
            ['course_id' => $course3->id, 'user_id' => $studentSamantha->id],
            ['status' => Enrollment::STATUS_ENROLLED, 'enrolled_at' => now()->subHours(12)]
        );

        // Course 4: 2/2 enrolled (FULL), demo student waitlisted
        Enrollment::firstOrCreate(
            ['course_id' => $course4->id, 'user_id' => $studentMarcus->id],
            ['status' => Enrollment::STATUS_ENROLLED, 'enrolled_at' => now()->subDays(2)]
        );
        Enrollment::firstOrCreate(
            ['course_id' => $course4->id, 'user_id' => $studentSamantha->id],
            ['status' => Enrollment::STATUS_ENROLLED, 'enrolled_at' => now()->subDay()]
        );
        Enrollment::firstOrCreate(
            ['course_id' => $course4->id, 'user_id' => $demoStudent->id],
            ['status' => Enrollment::STATUS_WAITLISTED, 'waitlisted_at' => now()->subHours(4)]
        );
    }
}
