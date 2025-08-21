<?php

namespace App\Contracts\Repositories;

use App\Models\Import;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface for Import repository operations with user scoping
 */
interface ImportRepositoryInterface
{
    /**
     * Get all imports for a specific user with optional filtering
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @return Collection<int, Import>
     */
    public function getAllForUser(User $user, array $filters = []): Collection;

    /**
     * Get paginated imports for a specific user with optional filtering
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedForUser(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Find an import by ID for a specific user
     *
     * @param User $user
     * @param int $id
     * @return Import|null
     */
    public function findForUser(User $user, int $id): ?Import;

    /**
     * Create a new import for a specific user
     *
     * @param User $user
     * @param array<string, mixed> $data
     * @return Import
     */
    public function createForUser(User $user, array $data): Import;

    /**
     * Update an import for a specific user
     *
     * @param User $user
     * @param Import $import
     * @param array<string, mixed> $data
     * @return Import
     */
    public function updateForUser(User $user, Import $import, array $data): Import;

    /**
     * Get imports by status for a specific user
     *
     * @param User $user
     * @param string $status
     * @return Collection<int, Import>
     */
    public function getByStatusForUser(User $user, string $status): Collection;

    /**
     * Get recent imports for a specific user
     *
     * @param User $user
     * @param int $limit
     * @return Collection<int, Import>
     */
    public function getRecentForUser(User $user, int $limit = 10): Collection;

    /**
     * Get import count for a specific user
     *
     * @param User $user
     * @return int
     */
    public function getCountForUser(User $user): int;

    /**
     * Get completed imports for a specific user
     *
     * @param User $user
     * @return Collection<int, Import>
     */
    public function getCompletedForUser(User $user): Collection;

    /**
     * Get failed imports for a specific user
     *
     * @param User $user
     * @return Collection<int, Import>
     */
    public function getFailedForUser(User $user): Collection;
}