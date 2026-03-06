<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('section_id')
                ->constrained('sections')
                ->cascadeOnDelete();

            $table->foreignId('subject_id')
                ->constrained('subjects')
                ->cascadeOnDelete();

            // Teacher is a user with the 'faculty' role
            $table->foreignId('teacher_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('day', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);
            $table->time('start_time');
            $table->time('end_time');

            // Scope schedules to a school year and semester
            $table->string('school_year');                          // e.g. "2025-2026"
            $table->enum('semester', ['1st', '2nd', 'summer']);

            $table->timestamps();

            // A section can only have one subject per day per overlapping time.
            // Overlap is checked at the application level (see ScheduleController).
            // This index speeds up conflict queries.
            $table->index(['section_id', 'day', 'school_year', 'semester']);
            $table->index(['teacher_id', 'day', 'school_year', 'semester']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
