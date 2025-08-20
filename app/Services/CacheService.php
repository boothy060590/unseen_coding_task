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
        // Use cache tags if the cache driver supports it (Redis, Memcached)
        if ($this->supportsTags()) {
            // Use the injected cache instance with tags
            return $this->cache->tags($tags)->remember($key, $ttl, $callback);
        }

        // Fallback to regular cache with tag-based key
        $taggedKey = $this->createTaggedKey($key, $tags);
        $result = $this->cache->remember($taggedKey, $ttl, $callback);

        // Store key associations for manual invalidation
        $this->storeKeyTagAssociations($key, $tags);

        return $result;
    }

    /**
     * Flush cache by tags
     *
     * @param array<string> $tags
     * @return void
     */
    public function flushByTags(array $tags): void
    {
        if ($this->supportsTags()) {
            // Use the injected cache instance with tags
            $this->cache->tags($tags)->flush();
            return;
        }

        // Manual invalidation for drivers that don't support tags
        $this->flushByTagsManually($tags);
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
        $this->flushByTags(["user:{$userId}"]);
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
        $this->flushByTags([
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
        $this->flushByTags([
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
        $this->flushByTags([
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
        $this->flushByTags(["operation:{$operation}"]);
    }

    /**
     * Check if the cache driver supports tags
     *
     * @return bool
     */
    private function supportsTags(): bool
    {
        try {
            // Use the injected cache instance to test for tag support
            $this->cache->tags(['test']);
            return true;
        } catch (\BadMethodCallException) {
            return false;
        }
    }

    /**
     * Create a tagged key for drivers that don't support tags
     *
     * @param string $key
     * @param array<string> $tags
     * @return string
     */
    private function createTaggedKey(string $key, array $tags): string
    {
        $tagHash = md5(implode('|', sort($tags)));
        return "tagged:{$tagHash}:{$key}";
    }

    /**
     * Store key-tag associations for manual invalidation
     *
     * @param string $key
     * @param array<string> $tags
     * @return void
     */
    private function storeKeyTagAssociations(string $key, array $tags): void
    {
        foreach ($tags as $tag) {
            $tagKey = "tag_keys:{$tag}";
            $keys = $this->cache->get($tagKey, []);

            if (!in_array($key, $keys, true)) {
                $keys[] = $key;
                $this->cache->put($tagKey, $keys, now()->addDay()); // Store for 1 day
            }
        }
    }

    /**
     * Flush cache by tags manually for drivers that don't support tags
     *
     * @param array<string> $tags
     * @return void
     */
    private function flushByTagsManually(array $tags): void
    {
        foreach ($tags as $tag) {
            $tagKey = "tag_keys:{$tag}";
            $keys = $this->cache->get($tagKey, []);

            // Delete all keys associated with this tag
            foreach ($keys as $key) {
                $this->cache->forget($key);
            }

            // Clear the tag association
            $this->cache->forget($tagKey);
        }
    }
}
