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
     * Get all exports for a specific user
     *
     * @param User $user
     * @return Collection<int, Export>
     */
    public function getAllForUser(User $user): Collection
    {
        return $this->buildUserQuery($user)->latest()->get();
    }

    /**
     * Get paginated exports for a specific user
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
        return $this->buildUserQuery($user)
            ->where('status', $status)
            ->latest()
            ->get();
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
        return $this->buildUserQuery($user)
            ->latest()
            ->limit($limit)
            ->get();
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
        return $this->buildUserQuery($user)
            ->where('status', 'completed')
            ->where(function (Builder $query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->whereNotNull('download_url')
            ->latest()
            ->get();
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
                'status' => 'expired',
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
}