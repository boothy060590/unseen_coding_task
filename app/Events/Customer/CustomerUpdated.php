<?php

namespace App\Events\Customer;

use App\Models\Customer;
use App\Models\User;

/**
 * Event fired when a customer is updated
 */
class CustomerUpdated extends CustomerEvent
{
    /**
     * Create a new event instance
     *
     * @param Customer $customer
     * @param User $user
     * @param array<string, mixed> $originalData The original data before update
     * @param array<string, mixed> $context Additional context data
     */
    public function __construct(
        public Customer $customer,
        public User $user,
        public array $originalData = [],
        public array $context = []
    ) {
        parent::__construct($customer, $user, $context);
    }
}