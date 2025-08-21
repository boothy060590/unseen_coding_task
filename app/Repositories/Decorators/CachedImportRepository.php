<?php

namespace App\Repositories\Decorators;

use App\Contracts\Repositories\ImportRepositoryInterface;
use App\Models\Import;
use App\Models\User;
use App\Services\CacheService;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cache decorator for ImportRepository to improve performance
 */
class CachedImportRepository implements ImportRepositoryInterface
{
    /**
     * Constructor
     *
     * @param ImportRepositoryInterface $repository
     * @param CacheService $cacheService
     * @param ConfigRepository $config
     */
    public function __construct(
        private ImportRepositoryInterface $repository,
        private CacheService $cacheService,
        private ConfigRepository $config
    ) {}

    /**
     * Get all imports for a specific user
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @return Collection<int, Import>
     */
    public function getAllForUser(User $user, array $filters = []): Collection
    {
        // Don't cache filtered results as they can be highly variable
        if (!empty($filters)) {
            return $this->repository->getAllForUser($user, $filters);
        }

        $cacheInfo = $this->cacheService->getImportCacheInfo($user->id, 'all');

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            $this->config->get('cache.ttl.imports', 1800),
            fn() => $this->repository->getAllForUser($user, $filters)
        );
    }

    /**
     * Get paginated imports for a specific user
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
     * Find an import by ID for a specific user
     *
     * @param User $user
     * @param int $id
     * @return Import|null
     */
    public function findForUser(User $user, int $id): ?Import
    {
        $cacheInfo = $this->cacheService->getImportCacheInfo($user->id, 'find', $id);

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            $this->config->get('cache.ttl.imports_short', 300), // Shorter TTL for status-changing data
            fn() => $this->repository->findForUser($user, $id)
        );
    }

    /**
     * Create a new import for a specific user
     *
     * @param User $user
     * @param array<string, mixed> $data
     * @return Import
     * @throws \ReflectionException
     */
    public function createForUser(User $user, array $data): Import
    {
        $import = $this->repository->createForUser($user, $data);

        // Clear import cache using improved invalidation
        $this->cacheService->clearImportCache($user->id);

        return $import;
    }

    /**
     * Update an import for a specific user
     *
     * @param User $user
     * @param Import $import
     * @param array<string, mixed> $data
     * @return Import
     * @throws \ReflectionException
     */
    public function updateForUser(User $user, Import $import, array $data): Import
    {
        $updatedImport = $this->repository->updateForUser($user, $import, $data);

        // Clear import cache using improved invalidation
        $this->cacheService->clearImportCache($user->id);

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
        $cacheInfo = $this->cacheService->getImportCacheInfo($user->id, 'status', $status);

        // Use shorter TTL for processing status as it changes frequently
        $ttl = $status === 'processing'
            ? $this->config->get('cache.ttl.imports_short', 300)  // 5 minutes default
            : $this->config->get('cache.ttl.imports', 1800);

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            $ttl,
            fn() => $this->repository->getByStatusForUser($user, $status)
        );
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
        $cacheInfo = $this->cacheService->getImportCacheInfo($user->id, 'recent', $limit);

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            $this->config->get('cache.ttl.imports_short', 300), // Shorter TTL for recent data
            fn() => $this->repository->getRecentForUser($user, $limit)
        );
    }

    /**
     * Get import count for a specific user
     *
     * @param User $user
     * @return int
     */
    public function getCountForUser(User $user): int
    {
        $cacheInfo = $this->cacheService->getImportCacheInfo($user->id, 'count');

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            $this->config->get('cache.ttl.imports', 1800),
            fn() => $this->repository->getCountForUser($user)
        );
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
}
