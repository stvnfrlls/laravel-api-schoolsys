<?php

namespace Database\Seeders;

use App\Models\GradeLevel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GradeLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $grades = [
            ['name' => 'Grade 7', 'level' => 7],
            ['name' => 'Grade 8', 'level' => 8],
            ['name' => 'Grade 9', 'level' => 9],
            ['name' => 'Grade 10', 'level' => 10],
            ['name' => 'Grade 11', 'level' => 11],
            ['name' => 'Grade 12', 'level' => 12],
        ];

        foreach ($grades as $grade) {
            GradeLevel::firstOrCreate(['level' => $grade['level']], $grade);
        }
    }
}
