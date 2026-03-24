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
        Schema::create('assignment_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')
                ->constrained('assignment_submissions')
                ->cascadeOnDelete();
            $table->foreignId('question_id')
                ->constrained('assignment_questions')
                ->cascadeOnDelete();
            $table->longText('answer_text')->nullable();       // short_answer / paragraph
            $table->json('selected_option_ids')->nullable();   // multiple_choice / checkbox
            $table->decimal('auto_score', 5, 2)->default(0);
            $table->decimal('manual_score', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['submission_id', 'question_id']);
            $table->index('submission_id');
            $table->index('question_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_answers');
    }
};
