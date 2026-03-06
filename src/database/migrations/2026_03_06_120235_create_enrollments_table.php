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
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->foreignId('section_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('grade_level_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('school_year');                              // e.g. "2025-2026"
            $table->enum('semester', ['1st', '2nd', 'summer']);

            $table->enum('status', ['active', 'dropped', 'completed'])
                ->default('active');

            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamps();

            $table->unique(
                ['student_id', 'school_year', 'semester'],
                'unique_student_per_period'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
