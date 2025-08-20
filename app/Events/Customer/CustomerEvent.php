<?php

namespace App\Events\Customer;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Abstract base class for all customer events
 */
abstract class CustomerEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance
     *
     * @param Customer $customer
     * @param User $user
     * @param array<string, mixed> $context Additional context data
     */
    public function __construct(
        public Customer $customer,
        public User $user,
        public array $context = []
    ) {}
}