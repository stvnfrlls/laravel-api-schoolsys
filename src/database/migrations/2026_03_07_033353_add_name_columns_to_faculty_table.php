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
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('first_name')->after('user_id');
            $table->string('last_name')->after('first_name');
            $table->string('middle_name')->nullable()->after('last_name');
            $table->string('suffix', 20)->nullable()->after('middle_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'middle_name', 'suffix']);
        });
    }
};
