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
        Schema::create('grading_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');               // e.g. Written Work
            $table->string('code');               // e.g. WW, PT, QA
            $table->decimal('weight', 5, 2);      // e.g. 25.00 (%)
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grading_components');
    }
};
