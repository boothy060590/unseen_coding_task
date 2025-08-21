<?php

namespace App\Contracts\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Interface for User repository operations
 */
interface UserRepositoryInterface
{
    /**
     * Get all users with optional filtering
     *
     * @param array<string, mixed> $filters
     * @return Collection<int, User>
     */
    public function getAllWithFilters(array $filters = []): Collection;

    /**
     * Get paginated users with optional filtering
     *
     * @param array<string, mixed> $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Find a user by ID
     *
     * @param int $id
     * @return User|null
     */
    public function find(int $id): ?User;

    /**
     * Find a user by email
     *
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User;

    /**
     * Create a new user
     *
     * @param array<string, mixed> $data
     * @return User
     */
    public function create(array $data): User;

    /**
     * Update a user
     *
     * @param User $user
     * @param array<string, mixed> $data
     * @return User
     */
    public function update(User $user, array $data): User;

    /**
     * Delete a user
     *
     * @param User $user
     * @return bool
     */
    public function delete(User $user): bool;

    /**
     * Get all users
     *
     * @return Collection<int, User>
     */
    public function getAll(): Collection;

    /**
     * Get users with customer counts
     *
     * @return Collection<int, User>
     */
    public function getUsersWithCustomerCounts(): Collection;

    /**
     * Get recently registered users
     *
     * @param int $limit
     * @return Collection<int, User>
     */
    public function getRecentUsers(int $limit = 10): Collection;

    /**
     * Get users by verification status
     *
     * @param bool $verified
     * @return Collection<int, User>
     */
    public function getUsersByVerificationStatus(bool $verified = true): Collection;

    /**
     * Get user count
     *
     * @return int
     */
    public function getCount(): int;

    /**
     * Search users by name or email
     *
     * @param string $query
     * @param int $limit
     * @return Collection<int, User>
     */
    public function search(string $query, int $limit = 50): Collection;

    /**
     * Check if email exists
     *
     * @param string $email
     * @param int|null $excludeId
     * @return bool
     */
    public function emailExists(string $email, ?int $excludeId = null): bool;
}