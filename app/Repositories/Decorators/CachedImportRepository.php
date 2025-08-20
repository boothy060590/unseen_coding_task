<?php

namespace App\Repositories\Decorators;

use App\Contracts\Repositories\ImportRepositoryInterface;
use App\Models\Import;
use App\Models\User;
use App\Services\CacheService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cache decorator for ImportRepository to improve performance
 */
class CachedImportRepository implements ImportRepositoryInterface
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
     * @param ImportRepositoryInterface $repository
     * @param CacheService $cacheService
     */
    public function __construct(
        private ImportRepositoryInterface $repository,
        private CacheService $cacheService
    ) {}

    /**
     * Get all imports for a specific user
     *
     * @param User $user
     * @return Collection<int, Import>
     */
    public function getAllForUser(User $user): Collection
    {
        $cacheInfo = $this->cacheService->getImportCacheInfo($user->id, 'all');

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            self::CACHE_TTL,
            fn() => $this->repository->getAllForUser($user)
        );
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
        // Don't cache paginated results as they change frequently and have many variations
        return $this->repository->getPaginatedForUser($user, $perPage);
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
        $cacheKey = $this->getCacheKey('find', $user->id, $id);

        return $this->cache->remember($cacheKey, self::SHORT_CACHE_TTL, function () use ($user, $id) {
            return $this->repository->findForUser($user, $id);
        });
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
        $import = $this->repository->createForUser($user, $data);
        
        // Clear relevant cache entries
        $this->clearUserCache($user);
        
        return $import;
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
        $updatedImport = $this->repository->updateForUser($user, $import, $data);
        
        // Clear relevant cache entries
        $this->clearImportCache($user, $import);
        
        return $updatedImport;
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
        $cacheKey = $this->getCacheKey('status', $user->id, $status);

        // Use shorter TTL for processing status as it changes frequently
        $ttl = $status === 'processing' ? self::SHORT_CACHE_TTL : self::CACHE_TTL;

        return $this->cache->remember($cacheKey, $ttl, function () use ($user, $status) {
            return $this->repository->getByStatusForUser($user, $status);
        });
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
        $cacheKey = $this->getCacheKey('recent', $user->id, $limit);

        return $this->cache->remember($cacheKey, self::SHORT_CACHE_TTL, function () use ($user, $limit) {
            return $this->repository->getRecentForUser($user, $limit);
        });
    }

    /**
     * Get import count for a specific user
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
     * Generate cache key for import operations
     *
     * @param string $operation
     * @param mixed ...$params
     * @return string
     */
    private function getCacheKey(string $operation, mixed ...$params): string
    {
        $keyParts = ['import', $operation, ...array_map('strval', $params)];
        
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
     * Clear cache entries for a specific import
     *
     * @param User $user
     * @param Import $import
     * @return void
     */
    private function clearImportCache(User $user, Import $import): void
    {
        // Clear specific import cache
        $this->cache->forget($this->getCacheKey('find', $user->id, $import->id));
        
        // Clear user-wide caches that might be affected
        $this->clearUserCache($user);
    }
}