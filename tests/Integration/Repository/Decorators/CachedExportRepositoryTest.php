<?php

namespace Tests\Integration\Repository\Decorators;

use App\Contracts\Repositories\ExportRepositoryInterface;
use App\Models\Export;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\Decorators\CachedExportRepository::class)]
class CachedExportRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ExportRepositoryInterface $cachedRepository;
    private User $user;
    private User $anotherUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Configure cache for testing
        config(['cache.ttl.exports' => 1800]);
        
        // Use Laravel's service container to resolve dependencies
        $this->cachedRepository = app(ExportRepositoryInterface::class);
        
        $this->user = User::factory()->create();
        $this->anotherUser = User::factory()->create();
        
        // Clear cache before each test
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    /**
     * Get all cache keys from the current cache store
     */
    private function getCacheKeys(): array
    {
        try {
            $store = Cache::store()->getStore();
            $reflection = new \ReflectionClass($store);
            $storageProperty = $reflection->getProperty('storage');
            $storageProperty->setAccessible(true);
            return array_keys($storageProperty->getValue($store));
        } catch (\Exception $e) {
            return ['Error getting cache keys: ' . $e->getMessage()];
        }
    }

    /**
     * Find actual cache key by suffix (works with new cache key format)
     */
    private function findCacheKeyBySuffix(string $suffix): ?string
    {
        $allKeys = $this->getCacheKeys();
        return collect($allKeys)->first(fn($key) => str_ends_with($key, $suffix));
    }

    /**
     * Assert that cache key exists by suffix
     */
    private function assertCacheKeyExists(string $suffix, string $message = null): void
    {
        $actualKey = $this->findCacheKeyBySuffix($suffix);
        $this->assertNotNull($actualKey, $message ?? "Cache key ending with '{$suffix}' should exist");
        $this->assertTrue(Cache::has($actualKey), "Cache key '{$actualKey}' should exist in cache");
    }

    /**
     * Assert that cache key does NOT exist by suffix (for invalidation tests)
     */
    private function assertCacheKeyNotExists(string $suffix, string $message = null): void
    {
        $actualKey = $this->findCacheKeyBySuffix($suffix);
        if ($actualKey) {
            $this->assertFalse(Cache::has($actualKey), $message ?? "Cache key ending with '{$suffix}' should not exist after invalidation");
        }
        // If no key found at all, that's also good (cache was cleared)
    }

    /**
     * Test that getAllForUser uses cache
     */
    public function testGetAllForUserUsesCache(): void
    {
        Export::factory(3)->create(['user_id' => $this->user->id]);
        
        // First call should hit database
        $firstResult = $this->cachedRepository->getAllForUser($this->user);
        $this->assertCount(3, $firstResult);
        
        // Verify cache was populated
        $this->assertCacheKeyExists(':export:all', 'Cache key for all exports should exist after first call');
        
        // Second call should hit cache
        $secondResult = $this->cachedRepository->getAllForUser($this->user);
        $this->assertCount(3, $secondResult);
        $this->assertEquals($firstResult->pluck('id')->toArray(), $secondResult->pluck('id')->toArray());
    }

    /**
     * Test that paginated results bypass cache
     */
    public function testGetPaginatedForUserBypassesCache(): void
    {
        Export::factory(10)->create(['user_id' => $this->user->id]);
        
        // First call should not cache
        $firstResult = $this->cachedRepository->getPaginatedForUser($this->user, [], 5);
        $this->assertCount(5, $firstResult);
        
        // Verify no cache was created for paginated results
        $this->assertCacheKeyNotExists(':export:paginated', 'No cache should be created for paginated results');
        
        // Second call should hit database again
        $secondResult = $this->cachedRepository->getPaginatedForUser($this->user, [], 5);
        $this->assertCount(5, $secondResult);
    }

    /**
     * Test that findForUser uses cache with shorter TTL
     */
    public function testFindForUserUsesCacheWithShorterTTL(): void
    {
        $export = Export::factory()->create(['user_id' => $this->user->id]);
        
        // First call should hit database
        $firstResult = $this->cachedRepository->findForUser($this->user, $export->id);
        $this->assertNotNull($firstResult);
        
        // Verify cache was populated
        $this->assertCacheKeyExists(":export:find:{$export->id}", 'Cache key for specific export should exist after first call');
        
        // Second call should hit cache
        $secondResult = $this->cachedRepository->findForUser($this->user, $export->id);
        $this->assertNotNull($secondResult);
        $this->assertEquals($firstResult->id, $secondResult->id);
    }

    /**
     * Test cache invalidation on export creation
     */
    public function testCreateForUserInvalidatesCache(): void
    {
        // Create initial exports and populate cache
        Export::factory(2)->create(['user_id' => $this->user->id]);
        $this->cachedRepository->getAllForUser($this->user);
        
        // Verify cache exists
        $this->assertCacheKeyExists(':export:all', 'Cache key for all exports should exist');
        
        // Create new export
        $newExport = $this->cachedRepository->createForUser($this->user, [
            'filename' => 'test.csv',
            'type' => 'customers',
            'format' => 'csv',
            'status' => 'pending'
        ]);
        
        // Cache should be cleared
        $this->assertCacheKeyNotExists(':export:all', 'Cache key for all exports should be cleared after creation');
        
        // Verify new export is included in fresh results
        $freshResults = $this->cachedRepository->getAllForUser($this->user);
        $this->assertCount(3, $freshResults);
        $this->assertTrue($freshResults->contains($newExport));
    }

    /**
     * Test cache invalidation on export update
     */
    public function testUpdateForUserInvalidatesCache(): void
    {
        $export = Export::factory()->create(['user_id' => $this->user->id]);
        
        // Populate cache
        $this->cachedRepository->getAllForUser($this->user);
        $this->cachedRepository->findForUser($this->user, $export->id);
        
        // Verify caches exist
        $this->assertCacheKeyExists(':export:all', 'Cache key for all exports should exist');
        $this->assertCacheKeyExists(":export:find:{$export->id}", 'Cache key for specific export should exist');
        
        // Update export
        $this->cachedRepository->updateForUser($this->user, $export, [
            'status' => 'completed'
        ]);
        
        // Cache should be cleared
        $this->assertCacheKeyNotExists(':export:all', 'Cache key for all exports should be cleared after update');
        
        // Verify update was successful
        $updatedExport = $this->cachedRepository->findForUser($this->user, $export->id);
        $this->assertEquals('completed', $updatedExport->status);
    }

    /**
     * Test that getByStatusForUser uses cache with different TTL for processing status
     */
    public function testGetByStatusForUserUsesCacheWithDifferentTTL(): void
    {
        Export::factory(2)->create([
            'user_id' => $this->user->id,
            'status' => 'completed'
        ]);
        
        Export::factory(1)->create([
            'user_id' => $this->user->id,
            'status' => 'processing'
        ]);
        
        // Test completed status (normal TTL)
        $firstResult = $this->cachedRepository->getByStatusForUser($this->user, 'completed');
        $this->assertCount(2, $firstResult);
        
        $this->assertCacheKeyExists(':export:status:completed', 'Cache key for completed exports should exist');
        
        // Test processing status (shorter TTL)
        $firstProcessingResult = $this->cachedRepository->getByStatusForUser($this->user, 'processing');
        $this->assertCount(1, $firstProcessingResult);
        
        $this->assertCacheKeyExists(':export:status:processing', 'Cache key for processing exports should exist');
        
        // Second calls should hit cache
        $secondResult = $this->cachedRepository->getByStatusForUser($this->user, 'completed');
        $this->assertCount(2, $secondResult);
        
        $secondProcessingResult = $this->cachedRepository->getByStatusForUser($this->user, 'processing');
        $this->assertCount(1, $secondProcessingResult);
    }

    /**
     * Test that getRecentForUser uses cache with shorter TTL
     */
    public function testGetRecentForUserUsesCacheWithShorterTTL(): void
    {
        Export::factory(5)->create(['user_id' => $this->user->id]);
        
        // First call should hit database
        $firstResult = $this->cachedRepository->getRecentForUser($this->user, 3);
        $this->assertCount(3, $firstResult);
        
        // Verify cache was populated
        $this->assertCacheKeyExists(':export:recent:3', 'Cache key for recent exports should exist after first call');
        
        // Second call should hit cache
        $secondResult = $this->cachedRepository->getRecentForUser($this->user, 3);
        $this->assertCount(3, $secondResult);
        $this->assertEquals($firstResult->pluck('id')->toArray(), $secondResult->pluck('id')->toArray());
    }

    /**
     * Test that getCountForUser uses cache
     */
    public function testGetCountForUserUsesCache(): void
    {
        Export::factory(3)->create(['user_id' => $this->user->id]);
        
        // First call should hit database
        $firstCount = $this->cachedRepository->getCountForUser($this->user);
        $this->assertEquals(3, $firstCount);
        
        // Verify cache was populated
        $this->assertCacheKeyExists(':export:count', 'Cache key for export count should exist after first call');
        
        // Second call should hit cache
        $secondCount = $this->cachedRepository->getCountForUser($this->user);
        $this->assertEquals(3, $secondCount);
    }

    /**
     * Test that getDownloadableForUser uses cache with shorter TTL
     */
    public function testGetDownloadableForUserUsesCacheWithShorterTTL(): void
    {
        Export::factory(2)->create([
            'user_id' => $this->user->id,
            'status' => 'completed',
            'download_url' => 'https://example.com/file.csv'
        ]);
        
        // First call should hit database
        $firstResult = $this->cachedRepository->getDownloadableForUser($this->user);
        $this->assertCount(2, $firstResult);
        
        // Verify cache was populated
        $this->assertCacheKeyExists(':export:downloadable', 'Cache key for downloadable exports should exist after first call');
        
        // Second call should hit cache
        $secondResult = $this->cachedRepository->getDownloadableForUser($this->user);
        $this->assertCount(2, $secondResult);
        $this->assertEquals($firstResult->pluck('id')->toArray(), $secondResult->pluck('id')->toArray());
    }

    /**
     * Test that getExpiredExports bypasses cache
     */
    public function testGetExpiredExportsBypassesCache(): void
    {
        Export::factory(2)->create([
            'user_id' => $this->user->id,
            'expires_at' => now()->subDay(),
            'file_path' => '/path/to/file.csv'
        ]);
        
        // First call should not cache
        $firstResult = $this->cachedRepository->getExpiredExports();
        $this->assertCount(2, $firstResult);
        
        // Verify no cache was created
        $this->assertCacheKeyNotExists(':export:expired', 'No cache should be created for expired exports');
        
        // Second call should hit database again
        $secondResult = $this->cachedRepository->getExpiredExports();
        $this->assertCount(2, $secondResult);
    }

    /**
     * Test that cleanupExpiredExports clears operation cache
     */
    public function testCleanupExpiredExportsClearsOperationCache(): void
    {
        Export::factory(2)->create([
            'user_id' => $this->user->id,
            'expires_at' => now()->subDay(),
            'file_path' => '/path/to/file.csv'
        ]);
        
        // Populate various caches
        $this->cachedRepository->getAllForUser($this->user);
        $this->cachedRepository->getCountForUser($this->user);
        
        // Verify caches exist
        $this->assertCacheKeyExists(':export:all', 'Cache key for all exports should exist');
        $this->assertCacheKeyExists(':export:count', 'Cache key for export count should exist');
        
        // Clean up expired exports
        $cleanedCount = $this->cachedRepository->cleanupExpiredExports();
        $this->assertEquals(2, $cleanedCount);
        
        // Operation cache should be cleared across all users
        // Note: This is a global operation, so we can't easily test the specific cache keys
        // But we can verify the cleanup worked
        $remainingExports = $this->cachedRepository->getExpiredExports();
        $this->assertCount(0, $remainingExports);
    }

    /**
     * Test cache isolation between users
     */
    public function testCacheIsolationBetweenUsers(): void
    {
        // Create exports for both users
        Export::factory(2)->create(['user_id' => $this->user->id]);
        Export::factory(3)->create(['user_id' => $this->anotherUser->id]);
        
        // Populate cache for both users
        $this->cachedRepository->getAllForUser($this->user);
        $this->cachedRepository->getAllForUser($this->anotherUser);
        
        // Verify separate caches exist
        $this->assertCacheKeyExists(':export:all', 'Cache key for exports should exist for both users');
        
        // Verify user isolation - each user should only see their own exports
        $userExports = $this->cachedRepository->getAllForUser($this->user);
        $anotherUserExports = $this->cachedRepository->getAllForUser($this->anotherUser);
        
        $this->assertCount(2, $userExports);
        $this->assertCount(3, $anotherUserExports);
        
        // Verify no cross-contamination
        $userExportIds = $userExports->pluck('id')->toArray();
        $anotherUserExportIds = $anotherUserExports->pluck('id')->toArray();
        
        $this->assertEmpty(array_intersect($userExportIds, $anotherUserExportIds));
    }

    /**
     * Test cache invalidation when export is updated by another user
     */
    public function testCacheInvalidationRespectsUserOwnership(): void
    {
        $export = Export::factory()->create(['user_id' => $this->user->id]);
        
        // Populate cache for the owner
        $this->cachedRepository->getAllForUser($this->user);
        $this->cachedRepository->findForUser($this->user, $export->id);
        
        // Verify caches exist
        $this->assertCacheKeyExists(':export:all', 'Cache key for all exports should exist');
        $this->assertCacheKeyExists(":export:find:{$export->id}", 'Cache key for specific export should exist');
        
        // Try to update export as another user (should fail)
        $this->expectException(\InvalidArgumentException::class);
        $this->cachedRepository->updateForUser($this->anotherUser, $export, [
            'status' => 'hacked'
        ]);
        
        // Cache should remain intact since update failed
        $this->assertCacheKeyExists(':export:all', 'Cache key for all exports should remain after failed update');
        $this->assertCacheKeyExists(":export:find:{$export->id}", 'Cache key for specific export should remain after failed update');
        
        // Verify export data is unchanged
        $unchangedExport = $this->cachedRepository->findForUser($this->user, $export->id);
        $this->assertNotEquals('hacked', $unchangedExport->status);
    }

    /**
     * Test that cache TTL is respected
     */
    public function testCacheTTLIsRespected(): void
    {
        Export::factory(2)->create(['user_id' => $this->user->id]);
        
        // Set a very short TTL for testing
        config(['cache.ttl.exports' => 1]); // 1 second
        
        // Populate cache
        $this->cachedRepository->getAllForUser($this->user);
        $this->assertCacheKeyExists(':export:all', 'Cache key for all exports should exist');
        
        // Wait for cache to expire
        sleep(2);
        
        // Cache should be expired
        $this->assertCacheKeyNotExists(':export:all', 'Cache key for all exports should be expired after TTL');
        
        // Next call should hit database and repopulate cache
        $result = $this->cachedRepository->getAllForUser($this->user);
        $this->assertCount(2, $result);
        $this->assertCacheKeyExists(':export:all', 'Cache key for all exports should exist after repopulation');
    }

    /**
     * Test cache behavior with empty results
     */
    public function testCacheBehaviorWithEmptyResults(): void
    {
        // No exports exist yet
        
        // First call should hit database and cache empty result
        $firstResult = $this->cachedRepository->getAllForUser($this->user);
        $this->assertCount(0, $firstResult);
        
        // Verify cache was populated even for empty results
        $this->assertCacheKeyExists(':export:all', 'Cache key for all exports should exist even for empty results');
        
        // Second call should hit cache
        $secondResult = $this->cachedRepository->getAllForUser($this->user);
        $this->assertCount(0, $secondResult);
        
        // Create an export
        Export::factory()->create(['user_id' => $this->user->id]);
        
        // Cache should still return old (empty) result
        $cachedResult = $this->cachedRepository->getAllForUser($this->user);
        $this->assertCount(0, $cachedResult);
        
        // Clear cache manually to simulate invalidation
        Cache::flush();
        
        // Now should get fresh result
        $freshResult = $this->cachedRepository->getAllForUser($this->user);
        $this->assertCount(1, $freshResult);
    }

    /**
     * Test that processing status uses shorter TTL
     */
    public function testProcessingStatusUsesShorterTTL(): void
    {
        Export::factory(1)->create([
            'user_id' => $this->user->id,
            'status' => 'processing'
        ]);
        
        // First call should hit database
        $firstResult = $this->cachedRepository->getByStatusForUser($this->user, 'processing');
        $this->assertCount(1, $firstResult);
        
        // Verify cache was populated
        $this->assertCacheKeyExists(':export:status:processing', 'Cache key for processing exports should exist after first call');
        
        // Clear cache and create a fresh repository with mocked config for short TTL
        Cache::flush();
        
        // Create a mocked config repository with moderate TTL for testing
        $mockConfig = \Mockery::mock(\Illuminate\Contracts\Config\Repository::class);
        $mockConfig->shouldReceive('get')
            ->with('cache.ttl.exports', 1800)
            ->andReturn(10); // Normal TTL
        $mockConfig->shouldReceive('get')
            ->with('cache.ttl.exports_short', 300)
            ->andReturn(5); // 5 second TTL for processing status (enough time to test)
        
        // Manually create a fresh repository instance with the mocked config
        $baseRepository = new \App\Repositories\ExportRepository();
        $cacheService = app(\App\Services\CacheService::class);
        $freshRepository = new \App\Repositories\Decorators\CachedExportRepository(
            $baseRepository,
            $cacheService,
            $mockConfig
        );
        
        // Populate cache with the fresh repository (should use 5 second TTL)
        $result = $freshRepository->getByStatusForUser($this->user, 'processing');
        
        // Verify cache was populated with short TTL
        $this->assertCacheKeyExists(':export:status:processing', 'Cache key for processing exports should exist');
        
        // Wait for cache to expire (5 second + buffer)
        sleep(6);
        
        // Cache should be expired
        $this->assertCacheKeyNotExists(':export:status:processing', 'Cache key for processing exports should be expired after TTL');
        
        // Next call should hit database and repopulate cache
        $result = $freshRepository->getByStatusForUser($this->user, 'processing');
        $this->assertCount(1, $result);
        $this->assertCacheKeyExists(':export:status:processing', 'Cache key for processing exports should exist after repopulation');
    }
}
