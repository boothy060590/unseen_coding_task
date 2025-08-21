<?php

namespace App\Contracts\Repositories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface for Customer repository operations with user scoping
 */
interface CustomerRepositoryInterface
{
    /**
     * Get all customers for a specific user with optional filtering
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @return Collection<int, Customer>
     */
    public function getAllForUser(User $user, array $filters = []): Collection;

    /**
     * Get paginated customers for a specific user with optional filtering
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedForUser(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Find a customer by ID for a specific user
     *
     * @param User $user
     * @param int $id
     * @return Customer|null
     */
    public function findForUser(User $user, int $id): ?Customer;

    /**
     * Find a customer by slug for a specific user
     *
     * @param User $user
     * @param string $slug
     * @return Customer|null
     */
    public function findBySlugForUser(User $user, string $slug): ?Customer;

    /**
     * Create a new customer for a specific user
     *
     * @param User $user
     * @param array<string, mixed> $data
     * @return Customer
     */
    public function createForUser(User $user, array $data): Customer;

    /**
     * Update a customer for a specific user
     *
     * @param User $user
     * @param Customer $customer
     * @param array<string, mixed> $data
     * @return Customer
     */
    public function updateForUser(User $user, Customer $customer, array $data): Customer;

    /**
     * Delete a customer for a specific user
     *
     * @param User $user
     * @param Customer $customer
     * @return bool
     */
    public function deleteForUser(User $user, Customer $customer): bool;

    /**
     * Get customer count for a specific user
     *
     * @param User $user
     * @return int
     */
    public function getCountForUser(User $user): int;

    /**
     * Get recent customers for a specific user
     *
     * @param User $user
     * @param int $limit
     * @return Collection<int, Customer>
     */
    public function getRecentForUser(User $user, int $limit = 10): Collection;
}
