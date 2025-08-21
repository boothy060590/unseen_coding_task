<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add new first_name and last_name columns
            $table->string('first_name')->after('id');
            $table->string('last_name')->after('first_name');
        });

        // Migrate existing data if any exists
        // Split existing name field into first_name and last_name
        DB::statement("
            UPDATE users 
            SET 
                first_name = TRIM(SUBSTRING_INDEX(name, ' ', 1)),
                last_name = TRIM(SUBSTRING(name, LOCATE(' ', name) + 1))
            WHERE name IS NOT NULL AND name != ''
        ");

        // Handle cases where there's no space (single name)
        DB::statement("
            UPDATE users 
            SET 
                first_name = name,
                last_name = ''
            WHERE name IS NOT NULL AND name != '' AND LOCATE(' ', name) = 0
        ");

        Schema::table('users', function (Blueprint $table) {
            // Drop the old name column
            $table->dropColumn('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add back the name column
            $table->string('name')->after('id');
        });

        // Reconstruct name from first_name and last_name
        DB::statement("
            UPDATE users 
            SET name = TRIM(CONCAT(first_name, ' ', last_name))
            WHERE first_name IS NOT NULL
        ");

        Schema::table('users', function (Blueprint $table) {
            // Drop the split name columns
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
