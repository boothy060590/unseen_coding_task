<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Import>
 */
class ImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $timestamp = $this->faker->dateTimeBetween('-1 month', 'now');
        $totalRows = $this->faker->numberBetween(10, 1000);
        $processedRows = $this->faker->numberBetween(0, $totalRows);
        $successfulRows = $this->faker->numberBetween(0, $processedRows);
        $failedRows = $processedRows - $successfulRows;
        
        return [
            'user_id' => \App\Models\User::factory(),
            'filename' => $this->faker->regexify('import_[0-9]{8}_[0-9]{6}\.csv'),
            'original_filename' => $this->faker->regexify('customers_[0-9]{4}\.csv'),
            'status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'failed']),
            'total_rows' => $totalRows,
            'processed_rows' => $processedRows,
            'successful_rows' => $successfulRows,
            'failed_rows' => $failedRows,
            'validation_errors' => $this->faker->optional(0.2)->passthrough([
                'file' => ['The file format is invalid.'],
                'headers' => ['Missing required header: email'],
            ]),
            'row_errors' => $this->faker->optional(0.3)->passthrough([
                '2' => ['Invalid email format'],
                '5' => ['Required field missing: first_name'],
            ]),
            'file_path' => $this->faker->optional(0.8)->filePath(),
            'started_at' => $this->faker->optional(0.6)->dateTimeBetween($timestamp, 'now'),
            'completed_at' => $this->faker->optional(0.5)->dateTimeBetween($timestamp, 'now'),
            'created_at' => $timestamp,
            'updated_at' => $this->faker->dateTimeBetween($timestamp, 'now'),
        ];
    }

    /**
     * Create an import with pending status
     *
     * @return static
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'total_rows' => 0,
            'processed_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Create an import with processing status
     *
     * @return static
     */
    public function processing(): static
    {
        $totalRows = $this->faker->numberBetween(100, 500);
        $processedRows = $this->faker->numberBetween(10, $totalRows - 10);
        
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'total_rows' => $totalRows,
            'processed_rows' => $processedRows,
            'successful_rows' => $this->faker->numberBetween(0, $processedRows),
            'started_at' => now()->subMinutes($this->faker->numberBetween(5, 60)),
            'completed_at' => null,
        ]);
    }

    /**
     * Create an import with completed status
     *
     * @return static
     */
    public function completed(): static
    {
        $totalRows = $this->faker->numberBetween(50, 300);
        $successfulRows = $this->faker->numberBetween(40, $totalRows);
        
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'total_rows' => $totalRows,
            'processed_rows' => $totalRows,
            'successful_rows' => $successfulRows,
            'failed_rows' => $totalRows - $successfulRows,
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subHour(),
        ]);
    }

    /**
     * Create an import with failed status
     *
     * @return static
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'validation_errors' => ['file' => ['Invalid file format']],
            'started_at' => now()->subHours(1),
            'completed_at' => now()->subMinutes(30),
        ]);
    }

    /**
     * Create an import with high success rate
     *
     * @return static
     */
    public function highSuccessRate(): static
    {
        $totalRows = $this->faker->numberBetween(100, 500);
        $successfulRows = $this->faker->numberBetween((int)($totalRows * 0.9), $totalRows);
        
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'total_rows' => $totalRows,
            'processed_rows' => $totalRows,
            'successful_rows' => $successfulRows,
            'failed_rows' => $totalRows - $successfulRows,
        ]);
    }

    /**
     * Create an import with validation errors
     *
     * @return static
     */
    public function withValidationErrors(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'validation_errors' => [
                'file' => ['File size exceeds maximum limit'],
                'format' => ['Unsupported file format'],
                'headers' => ['Missing required headers: email, first_name'],
            ],
        ]);
    }

    /**
     * Create an import with row-level errors
     *
     * @return static
     */
    public function withRowErrors(): static
    {
        return $this->state(fn (array $attributes) => [
            'row_errors' => [
                '2' => ['Invalid email format: not-an-email'],
                '5' => ['Missing required field: first_name'],
                '8' => ['Duplicate email address'],
                '12' => ['Invalid phone number format'],
            ],
        ]);
    }
}
