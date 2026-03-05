<?php

namespace Database\Seeders;

use App\Models\GradeLevel;
use App\Models\Section;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sections = ['Section A', 'Section B', 'Section C'];

        GradeLevel::all()->each(function ($grade) use ($sections) {
            foreach ($sections as $name) {
                Section::firstOrCreate(
                    ['grade_level_id' => $grade->id, 'name' => $name],
                    ['capacity' => 40]
                );
            }
        });
    }
}
