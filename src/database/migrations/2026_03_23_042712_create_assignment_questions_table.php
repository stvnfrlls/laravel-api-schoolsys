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
        Schema::create('assignment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->enum('type', ['multiple_choice', 'checkbox', 'short_answer', 'paragraph']);
            $table->text('question_text');
            $table->decimal('points', 5, 2)->default(1);
            $table->unsignedSmallInteger('order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->index(['assignment_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_questions');
    }
};
