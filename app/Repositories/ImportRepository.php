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
     * Get all imports for a specific user
     *
     * @param User $user
     * @return Collection<int, Import>
     */
    public function getAllForUser(User $user): Collection
    {
        return $this->buildUserQuery($user)->latest()->get();
    }

    /**
     * Get paginated imports for a specific user
     *
     * @param User $user
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $this->buildUserQuery($user)->latest()->paginate($perPage);
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
        return $this->buildUserQuery($user)
            ->where('status', $status)
            ->latest()
            ->get();
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
        return $this->buildUserQuery($user)
            ->latest()
            ->limit($limit)
            ->get();
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
}