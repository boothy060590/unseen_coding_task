<?php

namespace App\Contracts\Repositories;

use App\Models\Export;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface for Export repository operations with user scoping
 */
interface ExportRepositoryInterface
{
    /**
     * Get all exports for a specific user
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @return Collection<int, Export>
     */
    public function getAllForUser(User $user, array $filters = []): Collection;

    /**
     * Get paginated exports for a specific user
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedForUser(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Find an export by ID for a specific user
     *
     * @param User $user
     * @param int $id
     * @return Export|null
     */
    public function findForUser(User $user, int $id): ?Export;

    /**
     * Create a new export for a specific user
     *
     * @param User $user
     * @param array<string, mixed> $data
     * @return Export
     */
    public function createForUser(User $user, array $data): Export;

    /**
     * Update an export for a specific user
     *
     * @param User $user
     * @param Export $export
     * @param array<string, mixed> $data
     * @return Export
     */
    public function updateForUser(User $user, Export $export, array $data): Export;

    /**
     * Get exports by status for a specific user
     *
     * @param User $user
     * @param string $status
     * @return Collection<int, Export>
     */
    public function getByStatusForUser(User $user, string $status): Collection;

    /**
     * Get recent exports for a specific user
     *
     * @param User $user
     * @param int $limit
     * @return Collection<int, Export>
     */
    public function getRecentForUser(User $user, int $limit = 10): Collection;

    /**
     * Get export count for a specific user
     *
     * @param User $user
     * @return int
     */
    public function getCountForUser(User $user): int;

    /**
     * Get downloadable exports for a specific user
     *
     * @param User $user
     * @return Collection<int, Export>
     */
    public function getDownloadableForUser(User $user): Collection;

    /**
     * Get expired exports for cleanup
     *
     * @return Collection<int, Export>
     */
    public function getExpiredExports(): Collection;

    /**
     * Clean up expired exports
     *
     * @return int Number of cleaned up exports
     */
    public function cleanupExpiredExports(): int;
}
