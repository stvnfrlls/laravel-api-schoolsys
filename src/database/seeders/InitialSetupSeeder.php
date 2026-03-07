<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;
use App\Models\Subject;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;

class InitialSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // -------------------
        // 1. Create Roles
        // -------------------
        $roles = [
            ['name' => 'unassigned', 'description' => 'No specific role assigned'],
            ['name' => 'admin', 'description' => 'Full access to all resources'],
            ['name' => 'sub-admin', 'description' => 'Limited admin access'],
            ['name' => 'faculty', 'description' => 'Access to faculty resources'],
            ['name' => 'student', 'description' => 'Access to student resources'],
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(
                ['name' => $roleData['name']],
                ['description' => $roleData['description']]
            );
        }

        // -------------------
        // 2. Create Grade Levels
        // -------------------
        $gradeLevels = [
            ['name' => 'Grade 7', 'level' => 7],
            ['name' => 'Grade 8', 'level' => 8],
            ['name' => 'Grade 9', 'level' => 9],
            ['name' => 'Grade 10', 'level' => 10],
            ['name' => 'Grade 11', 'level' => 11],
            ['name' => 'Grade 12', 'level' => 12],
        ];

        $gradeLevelInstances = [];
        foreach ($gradeLevels as $data) {
            $gradeLevelInstances[] = GradeLevel::firstOrCreate(
                ['name' => $data['name']],
                ['level' => $data['level'], 'is_active' => true]
            );
        }

        // -------------------
        // 3. Create Sections
        // -------------------
        foreach ($gradeLevelInstances as $gradeLevel) {
            foreach (['A', 'B', 'C'] as $sectionLetter) {
                Section::firstOrCreate([
                    'grade_level_id' => $gradeLevel->id,
                    'name' => 'Section ' . $sectionLetter,
                ], [
                    'room' => 'Room ' . rand(100, 500),
                    'capacity' => rand(30, 50),
                    'is_active' => true,
                ]);
            }
        }

        // -------------------
        // 4. Fetch Role Instances
        // -------------------
        $adminRole = Role::where('name', 'admin')->first();
        $subAdminRole = Role::where('name', 'sub-admin')->first();
        $facultyRole = Role::where('name', 'faculty')->first();
        $studentRole = Role::where('name', 'student')->first();

        // -------------------
        // 5. Create Users
        // -------------------
        // 1 Admin
        $adminUser = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $adminUser->roles()->attach($adminRole->id);

        // 2 Sub-Admins
        $subAdminUsers = User::factory()->count(2)->create();
        foreach ($subAdminUsers as $user) {
            $user->roles()->attach($subAdminRole->id);
        }

        // 10 Faculty
        User::factory()
            ->count(10)
            ->has(Teacher::factory())
            ->create()
            ->each(fn($user) => $user->roles()->attach($facultyRole->id));

        // // 25 Students
        User::factory()
            ->count(25)
            ->has(Student::factory())
            ->create()
            ->each(fn($user) => $user->roles()->attach($studentRole->id));

        // Alternative way to create students for existing users with student role but no student record
        // $users = User::whereHas('roles', fn($q) => $q->where('name', 'student'))
        //     ->doesntHave('student')
        //     ->get();

        // $users->each(function ($user) {
        //     Student::factory()->create([
        //         'user_id' => $user->id,
        //     ]);
        // });

        // -------------------
        // 6. Create Subjects
        // -------------------
        $subjects = [
            'Mathematics',
            'English',
            'Science',
            'History',
            'Geography',
            'Computer Science',
            'Physical Education',
            'Music',
            'Art',
            'Economics',
        ];

        foreach ($subjects as $subjectName) {
            $code = strtoupper(preg_replace('/\s+/', '', substr($subjectName, 0, 3))); // take first 3 letters, remove spaces
            Subject::firstOrCreate(
                ['name' => $subjectName],
                [
                    'code' => $code,
                    'is_active' => true,
                ]
            );
        }

        // -------------------
        // 7. Create Enrollments
        // -------------------
        $students = Student::all();
        $sections = Section::all();

        foreach ($students as $student) {

            $section = $sections->random();

            Enrollment::create([
                'student_id' => $student->id,
                'section_id' => $section->id,
                'grade_level_id' => $section->grade_level_id,
                'school_year' => '2023-2024',
                'semester' => '1st',
                'status' => 'active',
            ]);
        }
    }
}