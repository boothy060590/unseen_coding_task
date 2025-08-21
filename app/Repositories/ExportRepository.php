<?php

namespace App\Repositories;

use App\Contracts\Repositories\ExportRepositoryInterface;
use App\Models\Export;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

/**
 * Repository for Export operations with user scoping and security
 */
class ExportRepository implements ExportRepositoryInterface
{
    /**
     * Get all exports for a specific user with optional filtering
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @return Collection<int, Export>
     */
    public function getAllForUser(User $user, array $filters = []): Collection
    {
        $query = $this->buildUserQuery($user);

        return $this->applyFilters($query, $filters)->get();
    }

    /**
     * Get paginated exports for a specific user with optional filtering
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
     * Find an export by ID for a specific user
     *
     * @param User $user
     * @param int $id
     * @return Export|null
     */
    public function findForUser(User $user, int $id): ?Export
    {
        return $this->buildUserQuery($user)->find($id);
    }

    /**
     * Create a new export for a specific user
     *
     * @param User $user
     * @param array<string, mixed> $data
     * @return Export
     */
    public function createForUser(User $user, array $data): Export
    {
        $data['user_id'] = $user->id;
        
        return Export::create($data);
    }

    /**
     * Update an export for a specific user
     *
     * @param User $user
     * @param Export $export
     * @param array<string, mixed> $data
     * @return Export
     */
    public function updateForUser(User $user, Export $export, array $data): Export
    {
        // Security check: ensure export belongs to user
        if ($export->user_id !== $user->id) {
            throw new \InvalidArgumentException('Export does not belong to the specified user');
        }

        $export->update($data);
        
        return $export->fresh();
    }

    /**
     * Get exports by status for a specific user
     *
     * @param User $user
     * @param string $status
     * @return Collection<int, Export>
     */
    public function getByStatusForUser(User $user, string $status): Collection
    {
        return $this->getAllForUser($user, ['status' => $status]);
    }

    /**
     * Get recent exports for a specific user
     *
     * @param User $user
     * @param int $limit
     * @return Collection<int, Export>
     */
    public function getRecentForUser(User $user, int $limit = 10): Collection
    {
        return $this->getAllForUser($user, ['limit' => $limit]);
    }

    /**
     * Get export count for a specific user
     *
     * @param User $user
     * @return int
     */
    public function getCountForUser(User $user): int
    {
        return $this->buildUserQuery($user)->count();
    }

    /**
     * Get downloadable exports for a specific user
     *
     * @param User $user
     * @return Collection<int, Export>
     */
    public function getDownloadableForUser(User $user): Collection
    {
        return $this->getAllForUser($user, ['downloadable' => true]);
    }

    /**
     * Get expired exports for cleanup
     *
     * @return Collection<int, Export>
     */
    public function getExpiredExports(): Collection
    {
        return Export::where('expires_at', '<=', now())
            ->whereNotNull('file_path')
            ->get();
    }

    /**
     * Clean up expired exports
     *
     * @return int Number of cleaned up exports
     */
    public function cleanupExpiredExports(): int
    {
        $expiredExports = $this->getExpiredExports();
        $cleanedCount = 0;

        foreach ($expiredExports as $export) {
            // Delete the physical file if it exists
            if ($export->file_path && file_exists(storage_path('app/' . $export->file_path))) {
                unlink(storage_path('app/' . $export->file_path));
            }

            // Update the export record to remove file references
            $export->update([
                'file_path' => null,
                'download_url' => null,
                'status' => 'failed',
            ]);

            $cleanedCount++;
        }

        return $cleanedCount;
    }

    /**
     * Build base query for user-scoped operations
     *
     * @param User $user
     * @return Builder
     */
    private function buildUserQuery(User $user): Builder
    {
        return Export::where('user_id', $user->id);
    }

    /**
     * Apply filters to the export query
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

        if (isset($filters['format']) && $filters['format']) {
            $query->where('format', $filters['format']);
        }

        if (isset($filters['downloadable']) && $filters['downloadable']) {
            $query->where('status', 'completed')
                ->where(function (Builder $subQuery) {
                    $subQuery->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->whereNotNull('download_url');
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

        $validSorts = ['created_at', 'updated_at', 'status', 'format', 'filename'];

        if (in_array($sortBy, $validSorts, true)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->latest();
        }

        return $query;
    }
}