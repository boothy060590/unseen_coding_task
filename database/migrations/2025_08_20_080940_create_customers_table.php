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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('organization')->nullable();
            $table->string('job_title')->nullable();
            $table->date('birthdate')->nullable();
            $table->text('notes')->nullable();
            $table->string('slug')->unique();
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'slug']);
            $table->index(['user_id', 'email']);
            $table->index(['user_id', 'organization']);
            $table->index(['user_id', 'created_at']);

            // Full-text search on notes
            $table->fullText(['notes', 'name', 'organization']);

            // Ensure unique slug per user
            $table->unique(['user_id', 'slug']);
            $table->unique(['user_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
