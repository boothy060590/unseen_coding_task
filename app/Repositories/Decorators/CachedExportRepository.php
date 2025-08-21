<?php

namespace App\Repositories\Decorators;

use App\Contracts\Repositories\ExportRepositoryInterface;
use App\Models\Export;
use App\Models\User;
use App\Services\CacheService;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cache decorator for ExportRepository to improve performance
 */
class CachedExportRepository implements ExportRepositoryInterface
{
    /**
     * Constructor
     *
     * @param ExportRepositoryInterface $repository
     * @param CacheService $cacheService
     * @param ConfigRepository $config
     */
    public function __construct(
        private ExportRepositoryInterface $repository,
        private CacheService $cacheService,
        private ConfigRepository $config
    ) {}

    /**
     * Get all exports for a specific user
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @return Collection<int, Export>
     */
    public function getAllForUser(User $user, array $filters = []): Collection
    {
        // Don't cache filtered results as they can be highly variable
        if (!empty($filters)) {
            return $this->repository->getAllForUser($user, $filters);
        }

        $cacheInfo = $this->cacheService->getExportCacheInfo($user->id, 'all');

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            $this->config->get('cache.ttl.exports', 1800),
            fn() => $this->repository->getAllForUser($user, $filters)
        );
    }

    /**
     * Get paginated exports for a specific user
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedForUser(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        // Don't cache paginated results as they change frequently and have many variations
        return $this->repository->getPaginatedForUser($user, $filters, $perPage);
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
        $cacheInfo = $this->cacheService->getExportCacheInfo($user->id, 'find', $id);

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            $this->config->get('cache.ttl.exports_short', 300), // Shorter TTL for status-changing data
            fn() => $this->repository->findForUser($user, $id)
        );
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
        $export = $this->repository->createForUser($user, $data);

        // Clear export cache using improved invalidation
        $this->cacheService->clearExportCache($user->id);

        return $export;
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
        $updatedExport = $this->repository->updateForUser($user, $export, $data);

        // Clear export cache using improved invalidation
        $this->cacheService->clearExportCache($user->id);

        return $updatedExport;
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
        $cacheInfo = $this->cacheService->getExportCacheInfo($user->id, 'status', $status);

        // Use shorter TTL for processing status as it changes frequently
        $ttl = $status === 'processing'
            ? $this->config->get('cache.ttl.exports_short', 300)  // 5 minutes default
            : $this->config->get('cache.ttl.exports', 1800);

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            $ttl,
            fn() => $this->repository->getByStatusForUser($user, $status)
        );
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
        $cacheInfo = $this->cacheService->getExportCacheInfo($user->id, 'recent', $limit);

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            $this->config->get('cache.ttl.exports_short', 300), // Shorter TTL for recent data
            fn() => $this->repository->getRecentForUser($user, $limit)
        );
    }

    /**
     * Get export count for a specific user
     *
     * @param User $user
     * @return int
     */
    public function getCountForUser(User $user): int
    {
        $cacheInfo = $this->cacheService->getExportCacheInfo($user->id, 'count');

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            $this->config->get('cache.ttl.exports', 1800),
            fn() => $this->repository->getCountForUser($user)
        );
    }

    /**
     * Get downloadable exports for a specific user
     *
     * @param User $user
     * @return Collection<int, Export>
     */
    public function getDownloadableForUser(User $user): Collection
    {
        $cacheInfo = $this->cacheService->getExportCacheInfo($user->id, 'downloadable');

        // Shorter cache for downloadable exports as availability can change due to expiration
        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            $this->config->get('cache.ttl.exports_short', 1800),
            fn() => $this->repository->getDownloadableForUser($user)
        );
    }

    /**
     * Get expired exports for cleanup
     *
     * @return Collection<int, Export>
     */
    public function getExpiredExports(): Collection
    {
        // Don't cache expired exports as this is typically used for cleanup operations
        return $this->repository->getExpiredExports();
    }

    /**
     * Clean up expired exports
     *
     * @return int Number of cleaned up exports
     * @throws \ReflectionException
     */
    public function cleanupExpiredExports(): int
    {
        $cleanedCount = $this->repository->cleanupExpiredExports();

        // Clear all export-related caches across users after cleanup
        $this->cacheService->clearOperationCache('export');

        return $cleanedCount;
    }
}
