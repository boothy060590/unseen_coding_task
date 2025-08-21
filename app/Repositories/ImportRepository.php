<?php

namespace App\Repositories;

use App\Contracts\Repositories\ImportRepositoryInterface;
use App\Models\Import;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

/**
 * Repository for Import operations with user scoping and security
 */
class ImportRepository implements ImportRepositoryInterface
{
    /**
     * Get all imports for a specific user with optional filtering
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @return Collection<int, Import>
     */
    public function getAllForUser(User $user, array $filters = []): Collection
    {
        $query = $this->buildUserQuery($user);

        return $this->applyFilters($query, $filters)->get();
    }

    /**
     * Get paginated imports for a specific user with optional filtering
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedForUser(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->buildUserQuery($user);

        return $this->applyFilters($query, $filters)->paginate($perPage);
    }

    /**
     * Find an import by ID for a specific user
     *
     * @param User $user
     * @param int $id
     * @return Import|null
     */
    public function findForUser(User $user, int $id): ?Import
    {
        return $this->buildUserQuery($user)->find($id);
    }

    /**
     * Create a new import for a specific user
     *
     * @param User $user
     * @param array<string, mixed> $data
     * @return Import
     */
    public function createForUser(User $user, array $data): Import
    {
        $data['user_id'] = $user->id;
        
        return Import::create($data);
    }

    /**
     * Update an import for a specific user
     *
     * @param User $user
     * @param Import $import
     * @param array<string, mixed> $data
     * @return Import
     */
    public function updateForUser(User $user, Import $import, array $data): Import
    {
        // Security check: ensure import belongs to user
        if ($import->user_id !== $user->id) {
            throw new \InvalidArgumentException('Import does not belong to the specified user');
        }

        $import->update($data);
        
        return $import->fresh();
    }

    /**
     * Get imports by status for a specific user
     *
     * @param User $user
     * @param string $status
     * @return Collection<int, Import>
     */
    public function getByStatusForUser(User $user, string $status): Collection
    {
        return $this->getAllForUser($user, ['status' => $status]);
    }

    /**
     * Get recent imports for a specific user
     *
     * @param User $user
     * @param int $limit
     * @return Collection<int, Import>
     */
    public function getRecentForUser(User $user, int $limit = 10): Collection
    {
        return $this->getAllForUser($user, ['limit' => $limit]);
    }

    /**
     * Get import count for a specific user
     *
     * @param User $user
     * @return int
     */
    public function getCountForUser(User $user): int
    {
        return $this->buildUserQuery($user)->count();
    }

    /**
     * Get completed imports for a specific user
     *
     * @param User $user
     * @return Collection<int, Import>
     */
    public function getCompletedForUser(User $user): Collection
    {
        return $this->getByStatusForUser($user, 'completed');
    }

    /**
     * Get failed imports for a specific user
     *
     * @param User $user
     * @return Collection<int, Import>
     */
    public function getFailedForUser(User $user): Collection
    {
        return $this->getByStatusForUser($user, 'failed');
    }

    /**
     * Build base query for user-scoped operations
     *
     * @param User $user
     * @return Builder
     */
    private function buildUserQuery(User $user): Builder
    {
        return Import::where('user_id', $user->id);
    }

    /**
     * Apply filters to the import query
     *
     * @param Builder $query
     * @param array<string, mixed> $filters
     * @return Builder
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (isset($filters['status']) && $filters['status']) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['limit']) && is_numeric($filters['limit'])) {
            $query->limit((int) $filters['limit']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        $validSorts = ['created_at', 'updated_at', 'status', 'filename', 'total_rows', 'successful_rows', 'failed_rows'];

        if (in_array($sortBy, $validSorts, true)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->latest();
        }

        return $query;
    }
}