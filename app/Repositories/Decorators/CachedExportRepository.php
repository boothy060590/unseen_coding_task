<?php

namespace App\Repositories\Decorators;

use App\Contracts\Repositories\ExportRepositoryInterface;
use App\Models\Export;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cache decorator for ExportRepository to improve performance
 */
class CachedExportRepository implements ExportRepositoryInterface
{
    /**
     * Cache TTL in seconds (30 minutes - shorter than customers due to frequent status changes)
     */
    private const CACHE_TTL = 1800;

    /**
     * Short cache TTL for frequently changing data (5 minutes)
     */
    private const SHORT_CACHE_TTL = 300;

    /**
     * Constructor
     *
     * @param ExportRepositoryInterface $repository
     * @param CacheRepository $cache
     */
    public function __construct(
        private ExportRepositoryInterface $repository,
        private CacheRepository $cache
    ) {}

    /**
     * Get all exports for a specific user
     *
     * @param User $user
     * @return Collection<int, Export>
     */
    public function getAllForUser(User $user): Collection
    {
        $cacheKey = $this->getCacheKey('all', $user->id);

        return $this->cache->remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            return $this->repository->getAllForUser($user);
        });
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
        // Don't cache paginated results as they change frequently and have many variations
        return $this->repository->getPaginatedForUser($user, $perPage);
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
        $cacheKey = $this->getCacheKey('find', $user->id, $id);

        return $this->cache->remember($cacheKey, self::SHORT_CACHE_TTL, function () use ($user, $id) {
            return $this->repository->findForUser($user, $id);
        });
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
        
        // Clear relevant cache entries
        $this->clearUserCache($user);
        
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
        
        // Clear relevant cache entries
        $this->clearExportCache($user, $export);
        
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
        $cacheKey = $this->getCacheKey('status', $user->id, $status);

        // Use shorter TTL for processing status as it changes frequently
        $ttl = $status === 'processing' ? self::SHORT_CACHE_TTL : self::CACHE_TTL;

        return $this->cache->remember($cacheKey, $ttl, function () use ($user, $status) {
            return $this->repository->getByStatusForUser($user, $status);
        });
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
        $cacheKey = $this->getCacheKey('recent', $user->id, $limit);

        return $this->cache->remember($cacheKey, self::SHORT_CACHE_TTL, function () use ($user, $limit) {
            return $this->repository->getRecentForUser($user, $limit);
        });
    }

    /**
     * Get export count for a specific user
     *
     * @param User $user
     * @return int
     */
    public function getCountForUser(User $user): int
    {
        $cacheKey = $this->getCacheKey('count', $user->id);

        return $this->cache->remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            return $this->repository->getCountForUser($user);
        });
    }

    /**
     * Get downloadable exports for a specific user
     *
     * @param User $user
     * @return Collection<int, Export>
     */
    public function getDownloadableForUser(User $user): Collection
    {
        $cacheKey = $this->getCacheKey('downloadable', $user->id);

        // Shorter cache for downloadable exports as availability can change due to expiration
        return $this->cache->remember($cacheKey, self::SHORT_CACHE_TTL, function () use ($user) {
            return $this->repository->getDownloadableForUser($user);
        });
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
     */
    public function cleanupExpiredExports(): int
    {
        $cleanedCount = $this->repository->cleanupExpiredExports();
        
        // Clear all downloadable caches as cleanup affects availability
        $this->clearDownloadableCaches();
        
        return $cleanedCount;
    }

    /**
     * Generate cache key for export operations
     *
     * @param string $operation
     * @param mixed ...$params
     * @return string
     */
    private function getCacheKey(string $operation, mixed ...$params): string
    {
        $keyParts = ['export', $operation, ...array_map('strval', $params)];
        
        return implode(':', $keyParts);
    }

    /**
     * Clear all cache entries for a user
     *
     * @param User $user
     * @return void
     */
    private function clearUserCache(User $user): void
    {
        $keysToForget = [
            $this->getCacheKey('all', $user->id),
            $this->getCacheKey('count', $user->id),
            $this->getCacheKey('recent', $user->id, 10),
            $this->getCacheKey('recent', $user->id, 5), // Common limits
            $this->getCacheKey('downloadable', $user->id),
            $this->getCacheKey('status', $user->id, 'pending'),
            $this->getCacheKey('status', $user->id, 'processing'),
            $this->getCacheKey('status', $user->id, 'completed'),
            $this->getCacheKey('status', $user->id, 'failed'),
        ];

        foreach ($keysToForget as $key) {
            $this->cache->forget($key);
        }
    }

    /**
     * Clear cache entries for a specific export
     *
     * @param User $user
     * @param Export $export
     * @return void
     */
    private function clearExportCache(User $user, Export $export): void
    {
        // Clear specific export cache
        $this->cache->forget($this->getCacheKey('find', $user->id, $export->id));
        
        // Clear user-wide caches that might be affected
        $this->clearUserCache($user);
    }

    /**
     * Clear downloadable caches across all users (used after cleanup operations)
     *
     * @return void
     */
    private function clearDownloadableCaches(): void
    {
        // Note: In a production environment, you might want to use cache tags
        // or implement a more sophisticated cache invalidation strategy
        // For now, this is a placeholder - specific user caches will be cleared
        // when their exports are individually updated
    }
}