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
        Schema::create('student_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grading_component_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('quarter');                        // 1, 2, 3, 4
            $table->decimal('score', 5, 2);                       // raw percentage 0-100
            $table->decimal('weighted_score', 5, 2)->nullable();  // score * (weight/100)
            $table->decimal('final_grade', 5, 2)->nullable();     // sum of weighted scores per quarter
            $table->boolean('is_failing')->default(false);        // flagged if final_grade < 75
            $table->timestamps();

            $table->unique(['enrollment_id', 'subject_id', 'grading_component_id', 'quarter']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_grades');
    }
};
