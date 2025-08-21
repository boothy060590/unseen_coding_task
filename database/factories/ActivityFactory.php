<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for custom Activity Log model
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Activity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'log_name' => 'default',
            'description' => $this->faker->sentence(),
            'subject_type' => Customer::class,
            'subject_id' => Customer::factory(),
            'causer_type' => User::class,
            'causer_id' => User::factory(),
            'event' => $this->faker->randomElement(['created', 'updated', 'deleted', 'viewed']),
            'properties' => [],
            'batch_uuid' => null,
            'created_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'updated_at' => now(),
        ];
    }

    /**
     * Set the activity event
     *
     * @param string $event
     * @return static
     */
    public function event(string $event): static
    {
        return $this->state(fn (array $attributes) => [
            'event' => $event,
        ]);
    }

    /**
     * Set the activity description
     *
     * @param string $description
     * @return static
     */
    public function description(string $description): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => $description,
        ]);
    }

    /**
     * Set the causer (user who performed the action)
     *
     * @param User $user
     * @return static
     */
    public function causedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'causer_type' => User::class,
            'causer_id' => $user->id,
        ]);
    }

    /**
     * Set the subject (customer the action was performed on)
     *
     * @param Customer $customer
     * @return static
     */
    public function performedOn(Customer $customer): static
    {
        return $this->state(fn (array $attributes) => [
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
        ]);
    }

    /**
     * Set activity properties
     *
     * @param array<string, mixed> $properties
     * @return static
     */
    public function withProperties(array $properties): static
    {
        return $this->state(fn (array $attributes) => [
            'properties' => $properties,
        ]);
    }

    /**
     * Set the created_at timestamp
     *
     * @param \DateTimeInterface|string $createdAt
     * @return static
     */
    public function createdAt($createdAt): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $createdAt,
        ]);
    }

    /**
     * Create activity for customer creation event
     *
     * @return static
     */
    public function customerCreated(): static
    {
        return $this->event('created')->description('Customer was created');
    }

    /**
     * Create activity for customer update event
     *
     * @return static
     */
    public function customerUpdated(): static
    {
        return $this->event('updated')->description('Customer was updated');
    }

    /**
     * Create activity for customer deletion event
     *
     * @return static
     */
    public function customerDeleted(): static
    {
        return $this->event('deleted')->description('Customer was deleted');
    }

    /**
     * Create activity for customer view event
     *
     * @return static
     */
    public function customerViewed(): static
    {
        return $this->event('viewed')->description('Customer was viewed');
    }
}