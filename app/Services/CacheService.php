<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Redis;

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
     */
    public function clearOperationCache(string $operation): void
    {
        $this->flushByKeys(["operation:{$operation}"]);
    }
    
    /**
     * Force clear all cache - use for debugging cache issues
     *
     * @return void
     */
    public function clearAllCache(): void
    {
        $this->cache->flush();
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
     */
    public function flushByKeys(array $patterns): void
    {
        $store = $this->cache->getStore();
        
        if ($store instanceof RedisStore) {
            // For Redis, use SCAN to find and delete matching keys
            $this->flushRedisKeys($patterns);
        } else {
            // For other stores (like array or file), we can't easily pattern match
            // so we'll just clear all cache - not ideal but safe
            $this->cache->flush();
        }
    }
    
    /**
     * Flush Redis keys using pattern matching
     *
     * @param array<string> $patterns
     * @return void
     */
    private function flushRedisKeys(array $patterns): void
    {
        $redis = Redis::connection();
        
        foreach ($patterns as $pattern) {
            // Use Redis SCAN to find keys matching the pattern
            // Look for our cache key format: "cache:*{pattern}*"
            $searchPattern = "cache:*{$pattern}*";
            $cursor = '0';
            $deletedCount = 0;
            
            do {
                $result = $redis->scan($cursor, ['match' => $searchPattern, 'count' => 100]);
                $cursor = $result[0];
                $keys = $result[1];
                
                if (!empty($keys)) {
                    // Delete the found keys
                    $redis->del($keys);
                    $deletedCount += count($keys);
                }
            } while ($cursor !== '0');
            
            // Also try with Laravel's cache prefix if it exists
            $cachePrefix = config('cache.prefix', '');
            if ($cachePrefix) {
                $prefixedPattern = "{$cachePrefix}:cache:*{$pattern}*";
                $cursor = '0';
                
                do {
                    $result = $redis->scan($cursor, ['match' => $prefixedPattern, 'count' => 100]);
                    $cursor = $result[0];
                    $keys = $result[1];
                    
                    if (!empty($keys)) {
                        $redis->del($keys);
                        $deletedCount += count($keys);
                    }
                } while ($cursor !== '0');
            }
        }
    }
}
