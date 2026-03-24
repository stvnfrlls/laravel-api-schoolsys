<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->foreignId('grading_component_id')
                ->nullable()
                ->after('teacher_id')
                ->constrained('grading_components')
                ->nullOnDelete();
            $table->tinyInteger('quarter')->nullable()->after('grading_component_id');

            $table->index('grading_component_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropForeign(['grading_component_id']);
            $table->dropIndex(['grading_component_id']);
            $table->dropColumn(['grading_component_id', 'quarter']);
        });
    }
};
