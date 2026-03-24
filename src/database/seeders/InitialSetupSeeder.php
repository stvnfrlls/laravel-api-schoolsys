<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\AssignmentDetail;
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
            ['name' => 'Grade 1', 'level' => 1],
            ['name' => 'Grade 2', 'level' => 2],
            ['name' => 'Grade 3', 'level' => 3],
            ['name' => 'Grade 4', 'level' => 4],
            ['name' => 'Grade 5', 'level' => 5],
            ['name' => 'Grade 6', 'level' => 6],
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
            foreach (['1', '2'] as $sectionLetter) {
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

        // 10 Faculty
        // User::factory()
        //     ->count(10)
        //     ->has(Teacher::factory())
        //     ->create()
        //     ->each(fn($user) => $user->roles()->attach($facultyRole->id));

        // // 25 Students
        // User::factory()
        //     ->count(25)
        //     ->has(Student::factory())
        //     ->create()
        //     ->each(fn($user) => $user->roles()->attach($studentRole->id));

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
        // $subjects = [
        //     'Mathematics',
        //     'English',
        //     'Science',
        //     'History',
        //     'Geography',
        //     'Computer Science',
        //     'Physical Education',
        //     'Music',
        //     'Art',
        //     'Economics',
        // ];

        // foreach ($subjects as $subjectName) {
        //     $code = strtoupper(preg_replace('/\s+/', '', substr($subjectName, 0, 3))); // take first 3 letters, remove spaces
        //     Subject::firstOrCreate(
        //         ['name' => $subjectName],
        //         [
        //             'code' => $code,
        //             'is_active' => true,
        //         ]
        //     );
        // }

        // -------------------
        // 7. Create Enrollments
        // -------------------
        // $students = Student::all();
        // $sections = Section::all();

        // foreach ($students as $student) {

        //     $section = $sections->random();

        //     Enrollment::create([
        //         'student_id' => $student->id,
        //         'section_id' => $section->id,
        //         'grade_level_id' => $section->grade_level_id,
        //         'school_year' => '2023-2024',
        //         'semester' => '1st',
        //         'status' => 'active',
        //     ]);
        // }

        // -------------------
        // 8. Create Assignments
        // -------------------
        // $teacher = Teacher::first();
        // $subjects = Subject::all();

        // Create assignments for each subject
        // foreach ($subjects as $subject) {
        //     $gradeLevels = $subject->gradeLevels;

        //     // Assignment 1
        //     $assignment1 = Assignment::create([
        //         'gradelevel_id' => $gradeLevel->id,
        //         'subject_id' => $subject->id,
        //         'teacher_id' => $teacher->id,
        //         'title' => 'Chapter 5 Exercises',
        //         'total_points' => 100,
        //         'due_date' => now()->addDays(7),
        //         'is_published' => true,
        //     ]);

        //     AssignmentDetail::create([
        //         'assignment_id' => $assignment1->id,
        //         'description' => 'Complete all exercises from Chapter 5',
        //         'instructions' => json_encode([
        //             ['type' => 'text', 'content' => 'Follow these instructions carefully:'],
        //             ['type' => 'bullet', 'content' => 'Read Chapter 5 pages 100-150'],
        //             ['type' => 'bullet', 'content' => 'Answer all questions at the end of the chapter'],
        //             ['type' => 'bullet', 'content' => 'Show all your work'],
        //             ['type' => 'bullet', 'content' => 'Box your final answers'],
        //             ['type' => 'text', 'content' => 'Submit your work before the due date.'],
        //         ]),
        //     ]);

        //     // Assignment 2
        //     $assignment2 = Assignment::create([
        //         'gradelevel_id' => $gradeLevel->id,
        //         'subject_id' => $subject->id,
        //         'teacher_id' => $teacher->id,
        //         'title' => 'Quiz - Midterm Review',
        //         'total_points' => 50,
        //         'due_date' => now()->addDays(14),
        //         'is_published' => true,
        //     ]);

        //     AssignmentDetail::create([
        //         'assignment_id' => $assignment2->id,
        //         'description' => 'Midterm quiz review covering chapters 1-5',
        //         'instructions' => json_encode([
        //             ['type' => 'heading', 'content' => 'Quiz Instructions'],
        //             ['type' => 'bullet', 'content' => 'Answer all 25 multiple choice questions'],
        //             ['type' => 'bullet', 'content' => 'You have 1 hour to complete the quiz'],
        //             ['type' => 'bullet', 'content' => 'No external resources allowed'],
        //             ['type' => 'text', 'content' => 'Good luck!'],
        //         ]),
        //     ]);

        //     // Assignment 3 (unpublished)
        //     $assignment3 = Assignment::create([
        //         'gradelevel_id' => $gradeLevel->id,
        //         'subject_id' => $subject->id,
        //         'teacher_id' => $teacher->id,
        //         'title' => 'Final Project',
        //         'total_points' => 200,
        //         'due_date' => now()->addDays(30),
        //         'is_published' => false,
        //     ]);

        //     AssignmentDetail::create([
        //         'assignment_id' => $assignment3->id,
        //         'description' => 'Final comprehensive project (draft - not published yet)',
        //         'instructions' => json_encode([
        //             ['type' => 'heading', 'content' => 'Project Overview'],
        //             ['type' => 'text', 'content' => 'This is a comprehensive project that covers all material from the semester.'],
        //             ['type' => 'heading', 'content' => 'Requirements'],
        //             ['type' => 'bullet', 'content' => 'Create a 10-15 page report'],
        //             ['type' => 'bullet', 'content' => 'Include at least 5 credible sources'],
        //             ['type' => 'bullet', 'content' => 'Follow APA format'],
        //             ['type' => 'bullet', 'content' => 'Include original analysis and insights'],
        //         ]),
        //     ]);
        // }
    }
}