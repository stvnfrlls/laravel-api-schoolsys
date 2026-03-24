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
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->decimal('auto_score', 8, 2)->default(0)->after('status');
            $table->decimal('manual_score', 8, 2)->default(0)->after('auto_score');
            $table->decimal('total_score', 8, 2)->default(0)->after('manual_score');
            $table->boolean('pushed_to_gradebook')->default(false)->after('total_score');
            $table->timestamp('pushed_at')->nullable()->after('pushed_to_gradebook');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropColumn([
                'auto_score',
                'manual_score',
                'total_score',
                'pushed_to_gradebook',
                'pushed_at',
            ]);
        });
    }
};
