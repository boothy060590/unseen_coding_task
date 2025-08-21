<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Export>
 */
class ExportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $timestamp = $this->faker->dateTimeBetween('-1 month', 'now');
        
        return [
            'user_id' => \App\Models\User::factory(),
            'filename' => $this->faker->regexify('export_[0-9]{8}_[0-9]{6}\.(csv|xlsx)'),
            'type' => $this->faker->randomElement(['all', 'filtered']),
            'format' => $this->faker->randomElement(['csv', 'xlsx']),
            'status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'failed']),
            'total_records' => $this->faker->numberBetween(0, 10000),
            'filters' => $this->faker->optional(0.3)->passthrough(['organization' => $this->faker->company]),
            'file_path' => $this->faker->optional(0.7)->filePath(),
            'download_url' => $this->faker->optional(0.7)->url(),
            'expires_at' => $this->faker->optional(0.8)->dateTimeBetween('now', '+30 days'),
            'started_at' => $this->faker->optional(0.6)->dateTimeBetween($timestamp, 'now'),
            'completed_at' => $this->faker->optional(0.5)->dateTimeBetween($timestamp, 'now'),
            'created_at' => $timestamp,
            'updated_at' => $this->faker->dateTimeBetween($timestamp, 'now'),
        ];
    }

    /**
     * Create an export with pending status
     *
     * @return static
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'file_path' => null,
            'download_url' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Create an export with completed status
     *
     * @return static
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'file_path' => 'exports/' . $this->faker->uuid() . '.csv',
            'download_url' => $this->faker->url(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Create an export with failed status
     *
     * @return static
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Create an export in CSV format
     *
     * @return static
     */
    public function csv(): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => 'csv',
            'filename' => 'export_' . now()->format('Ymd_His') . '.csv',
        ]);
    }

    /**
     * Create an export in XLSX format
     *
     * @return static
     */
    public function xlsx(): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => 'xlsx',
            'filename' => 'export_' . now()->format('Ymd_His') . '.xlsx',
        ]);
    }

    /**
     * Create an export that's downloadable (completed, not expired, with download URL)
     *
     * @return static
     */
    public function downloadable(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'file_path' => 'exports/' . $this->faker->uuid() . '.csv',
            'download_url' => $this->faker->url(),
            'expires_at' => now()->addDays(7),
            'completed_at' => now(),
        ]);
    }

    /**
     * Create an expired export
     *
     * @return static
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDays(1),
            'file_path' => 'exports/' . $this->faker->uuid() . '.csv',
        ]);
    }
}
