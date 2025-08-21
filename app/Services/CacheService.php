<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Service for managing cache with improved invalidation strategies
 */
class CacheService
{
    /**
     * Constructor
     *
     * @param CacheRepository $cache
     */
    public function __construct(
        private CacheRepository $cache
    ) {}

    /**
     * Remember with tags for better invalidation
     *
     * @param string $key
     * @param array<string> $tags
     * @param int $ttl
     * @param callable $callback
     * @return mixed
     */
    public function rememberWithTags(string $key, array $tags, int $ttl, callable $callback): mixed
    {
        // Create a prefixed cache key that includes tag information
        $cacheKey = $this->createCacheKey($key, $tags);

        return $this->cache->remember($cacheKey, $ttl, $callback);
    }

    /**
     * Get cache keys for user-specific operations
     *
     * @param int $userId
     * @param string $operation
     * @param mixed ...$params
     * @return array{key: string, tags: array<string>}
     */
    public function getUserCacheInfo(int $userId, string $operation, mixed ...$params): array
    {
        $keyParts = [$operation, ...array_map('strval', $params)];
        $key = implode(':', $keyParts);

        $tags = [
            "user:{$userId}",
            "operation:{$operation}",
        ];

        return [
            'key' => $key,
            'tags' => $tags,
        ];
    }

    /**
     * Get cache keys for customer-specific operations
     *
     * @param int $userId
     * @param int $customerId
     * @param string $operation
     * @param mixed ...$params
     * @return array{key: string, tags: array<string>}
     */
    public function getCustomerCacheInfo(int $userId, int $customerId, string $operation, mixed ...$params): array
    {
        $keyParts = ['customer', $operation, $customerId, ...array_map('strval', $params)];
        $key = implode(':', $keyParts);

        $tags = [
            "user:{$userId}",
            "customer:{$customerId}",
            "operation:{$operation}",
        ];

        return [
            'key' => $key,
            'tags' => $tags,
        ];
    }

    /**
     * Get cache keys for import-specific operations
     *
     * @param int $userId
     * @param string $operation
     * @param mixed ...$params
     * @return array{key: string, tags: array<string>}
     */
    public function getImportCacheInfo(int $userId, string $operation, mixed ...$params): array
    {
        $keyParts = ['import', $operation, ...array_map('strval', $params)];
        $key = implode(':', $keyParts);

        $tags = [
            "user:{$userId}",
            "import:user:{$userId}",
            "operation:{$operation}",
        ];

        return [
            'key' => $key,
            'tags' => $tags,
        ];
    }

    /**
     * Get cache keys for export-specific operations
     *
     * @param int $userId
     * @param string $operation
     * @param mixed ...$params
     * @return array{key: string, tags: array<string>}
     */
    public function getExportCacheInfo(int $userId, string $operation, mixed ...$params): array
    {
        $keyParts = ['export', $operation, ...array_map('strval', $params)];
        $key = implode(':', $keyParts);

        $tags = [
            "user:{$userId}",
            "export:user:{$userId}",
            "operation:{$operation}",
        ];

        return [
            'key' => $key,
            'tags' => $tags,
        ];
    }

    /**
     * Clear all cache for a user
     *
     * @param int $userId
     * @return void
     * @throws \ReflectionException
     */
    public function clearUserCache(int $userId): void
    {
        $this->flushByKeys(["user:{$userId}"]);
    }

    /**
     * Clear customer-specific cache
     *
     * @param int $userId
     * @param int $customerId
     * @return void
     * @throws \ReflectionException
     */
    public function clearCustomerCache(int $userId, int $customerId): void
    {
        $this->flushByKeys([
            "user:{$userId}",
            "customer:{$customerId}",
        ]);
    }

    /**
     * Clear import-specific cache for a user
     *
     * @param int $userId
     * @return void
     * @throws \ReflectionException
     */
    public function clearImportCache(int $userId): void
    {
        $this->flushByKeys([
            "import:user:{$userId}",
        ]);
    }

    /**
     * Clear export-specific cache for a user
     *
     * @param int $userId
     * @return void
     * @throws \ReflectionException
     */
    public function clearExportCache(int $userId): void
    {
        $this->flushByKeys([
            "export:user:{$userId}",
        ]);
    }

    /**
     * Clear operation-specific cache across all users
     *
     * @param string $operation
     * @return void
     * @throws \ReflectionException
     */
    public function clearOperationCache(string $operation): void
    {
        $this->flushByKeys(["operation:{$operation}"]);
    }



    /**
     * Create a cache key with tag prefixes for predictable invalidation
     *
     * @param string $key
     * @param array<string> $tags
     * @return string
     */
    private function createCacheKey(string $key, array $tags): string
    {
        // Sort tags for consistency
        sort($tags);

        // Create a key with tag prefixes for easy pattern matching
        $tagPrefixes = implode(':', $tags);

        return "cache:{$tagPrefixes}:{$key}";
    }

    /**
     * Flush cache by matching key patterns
     *
     * @param array<string> $patterns
     * @return void
     * @throws \ReflectionException
     */
    public function flushByKeys(array $patterns): void
    {
        $store = $this->cache->getStore();
        $reflection = new \ReflectionClass($store);
        $storageProperty = $reflection->getProperty('storage');
        $storageProperty->setAccessible(true);
        $storage = $storageProperty->getValue($store);

        $keysToDelete = [];

        foreach (array_keys($storage) as $cacheKey) {
            // Check if this cache key matches any of our key patterns
            foreach ($patterns as $pattern) {
                if (str_contains($cacheKey, "cache:") && str_contains($cacheKey, $pattern)) {
                    $keysToDelete[] = $cacheKey;
                    break;
                }
            }
        }

        // Delete all matching keys
        foreach ($keysToDelete as $key) {
            $this->cache->forget($key);
        }
    }
}
