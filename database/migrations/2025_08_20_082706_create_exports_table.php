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
        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('filename');
            $table->enum('type', ['all', 'filtered']); // All customers or filtered results
            $table->json('filters')->nullable(); // Store the filters used for export
            $table->enum('format', ['csv', 'xlsx']); // Export format
            $table->enum('status', ['pending', 'processing', 'completed', 'failed']);
            $table->integer('total_records')->default(0);
            $table->string('file_path')->nullable(); // S3 path to generated file
            $table->string('download_url')->nullable(); // Temporary download URL
            $table->timestamp('expires_at')->nullable(); // When download expires
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
