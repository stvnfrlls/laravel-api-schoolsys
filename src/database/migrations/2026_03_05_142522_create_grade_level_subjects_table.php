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
        Schema::create('grade_level_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_level_id')
                ->constrained('grade_levels')
                ->cascadeOnDelete();
            $table->foreignId('subject_id')
                ->constrained('subjects')
                ->cascadeOnDelete();
            $table->unsignedDecimal('units', 4, 1)->default(0);
            $table->unsignedSmallInteger('hours_per_week')->default(0);
            $table->timestamps();

            $table->unique(['grade_level_id', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_level_subjects');
    }
};
