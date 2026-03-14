<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // enrollments — FKs and common filter columns
        Schema::table('enrollments', function (Blueprint $table) {
            $table->index('section_id');
            $table->index('grade_level_id');
            $table->index(['status', 'school_year']);
        });

        // grading_components — FK
        Schema::table('grading_components', function (Blueprint $table) {
            $table->index('subject_id');
        });

        // student_grades — FKs and filter columns
        Schema::table('student_grades', function (Blueprint $table) {
            $table->index('subject_id');
            $table->index('grading_component_id');
            $table->index(['quarter', 'is_failing']);
        });

        // attendances — range/filter columns
        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['date', 'status']);
        });

        // role_user — reverse lookup (role → users)
        Schema::table('role_user', function (Blueprint $table) {
            $table->index('role_id');
        });

        // users — filter column
        Schema::table('users', function (Blueprint $table) {
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex(['section_id']);
            $table->dropIndex(['grade_level_id']);
            $table->dropIndex(['status', 'school_year']);
        });

        Schema::table('grading_components', function (Blueprint $table) {
            $table->dropIndex(['subject_id']);
        });

        Schema::table('student_grades', function (Blueprint $table) {
            $table->dropIndex(['subject_id']);
            $table->dropIndex(['grading_component_id']);
            $table->dropIndex(['quarter', 'is_failing']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex(['date', 'status']);
        });

        Schema::table('role_user', function (Blueprint $table) {
            $table->dropIndex(['role_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });
    }
};