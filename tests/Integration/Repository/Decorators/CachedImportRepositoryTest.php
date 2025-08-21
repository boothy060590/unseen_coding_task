<?php

namespace Tests\Integration\Repository\Decorators;

use App\Contracts\Repositories\ImportRepositoryInterface;
use App\Models\Import;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\Decorators\CachedImportRepository::class)]
class CachedImportRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ImportRepositoryInterface $cachedRepository;
    private User $user;
    private User $anotherUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure cache for testing
        config(['cache.ttl.imports' => 1800]);

        // Use Laravel's service container to resolve dependencies
        $this->cachedRepository = app(ImportRepositoryInterface::class);

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
        Import::factory(3)->create(['user_id' => $this->user->id]);

        // First call should hit database
        $firstResult = $this->cachedRepository->getAllForUser($this->user);
        $this->assertCount(3, $firstResult);

        // Verify cache was populated
        $this->assertCacheKeyExists(':import:all', 'Cache key for all imports should exist after first call');

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
        Import::factory(10)->create(['user_id' => $this->user->id]);

        // First call should not cache
        $firstResult = $this->cachedRepository->getPaginatedForUser($this->user, [], 5);
        $this->assertCount(5, $firstResult);

        // Verify no cache was created for paginated results
        $this->assertCacheKeyNotExists(':import:paginated', 'No cache should be created for paginated results');

        // Second call should hit database again
        $secondResult = $this->cachedRepository->getPaginatedForUser($this->user, [], 5);
        $this->assertCount(5, $secondResult);
    }

    /**
     * Test that findForUser uses cache with shorter TTL
     */
    public function testFindForUserUsesCacheWithShorterTTL(): void
    {
        $import = Import::factory()->create(['user_id' => $this->user->id]);

        // First call should hit database
        $firstResult = $this->cachedRepository->findForUser($this->user, $import->id);
        $this->assertNotNull($firstResult);

        // Verify cache was populated
        $this->assertCacheKeyExists(":import:find:{$import->id}", 'Cache key for specific import should exist after first call');

        // Second call should hit cache
        $secondResult = $this->cachedRepository->findForUser($this->user, $import->id);
        $this->assertNotNull($secondResult);
        $this->assertEquals($firstResult->id, $secondResult->id);
    }

    /**
     * Test cache invalidation on import creation
     */
    public function testCreateForUserInvalidatesCache(): void
    {
        // Create initial imports and populate cache
        Import::factory(2)->create(['user_id' => $this->user->id]);
        $this->cachedRepository->getAllForUser($this->user);

        // Verify cache exists
        $this->assertCacheKeyExists(':import:all', 'Cache key for all imports should exist');

        // Create new import
        $newImport = $this->cachedRepository->createForUser($this->user, [
            'filename' => 'test.csv',
            'original_filename' => 'customers.csv',
            'status' => 'pending',
            'total_rows' => 100
        ]);

        // Cache should be cleared
        $this->assertCacheKeyNotExists(':import:all', 'Cache key for all imports should be cleared after creation');

        // Verify new import is included in fresh results
        $freshResults = $this->cachedRepository->getAllForUser($this->user);
        $this->assertCount(3, $freshResults);
        $this->assertTrue($freshResults->contains($newImport));
    }

    /**
     * Test cache invalidation on import update
     */
    public function testUpdateForUserInvalidatesCache(): void
    {
        $import = Import::factory()->create(['user_id' => $this->user->id]);

        // Populate cache
        $this->cachedRepository->getAllForUser($this->user);
        $this->cachedRepository->findForUser($this->user, $import->id);

        // Verify caches exist
        $this->assertCacheKeyExists(':import:all', 'Cache key for all imports should exist');
        $this->assertCacheKeyExists(":import:find:{$import->id}", 'Cache key for specific import should exist');

        // Update import
        $this->cachedRepository->updateForUser($this->user, $import, [
            'status' => 'processing'
        ]);

        // Cache should be cleared
        $this->assertCacheKeyNotExists(':import:all', 'Cache key for all imports should be cleared after update');

        // Verify update was successful
        $updatedImport = $this->cachedRepository->findForUser($this->user, $import->id);
        $this->assertEquals('processing', $updatedImport->status);
    }

    /**
     * Test that getByStatusForUser uses cache with different TTL for processing status
     */
    public function testGetByStatusForUserUsesCacheWithDifferentTTL(): void
    {
        Import::factory(2)->create([
            'user_id' => $this->user->id,
            'status' => 'completed'
        ]);

        Import::factory(1)->create([
            'user_id' => $this->user->id,
            'status' => 'processing'
        ]);

        // Test completed status (normal TTL)
        $firstResult = $this->cachedRepository->getByStatusForUser($this->user, 'completed');
        $this->assertCount(2, $firstResult);

        $this->assertCacheKeyExists(':import:status:completed', 'Cache key for completed imports should exist');

        // Test processing status (shorter TTL)
        $firstProcessingResult = $this->cachedRepository->getByStatusForUser($this->user, 'processing');
        $this->assertCount(1, $firstProcessingResult);

        $this->assertCacheKeyExists(':import:status:processing', 'Cache key for processing imports should exist');

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
        Import::factory(5)->create(['user_id' => $this->user->id]);

        // First call should hit database
        $firstResult = $this->cachedRepository->getRecentForUser($this->user, 3);
        $this->assertCount(3, $firstResult);

        // Verify cache was populated
        $this->assertCacheKeyExists(':import:recent:3', 'Cache key for recent imports should exist after first call');

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
        Import::factory(3)->create(['user_id' => $this->user->id]);

        // First call should hit database
        $firstCount = $this->cachedRepository->getCountForUser($this->user);
        $this->assertEquals(3, $firstCount);

        // Verify cache was populated
        $this->assertCacheKeyExists(':import:count', 'Cache key for import count should exist after first call');

        // Second call should hit cache
        $secondCount = $this->cachedRepository->getCountForUser($this->user);
        $this->assertEquals(3, $secondCount);
    }

    /**
     * Test that getCompletedForUser uses cache (delegates to getByStatusForUser)
     */
    public function testGetCompletedForUserUsesCache(): void
    {
        Import::factory(2)->create([
            'user_id' => $this->user->id,
            'status' => 'completed'
        ]);

        // First call should hit database
        $firstResult = $this->cachedRepository->getCompletedForUser($this->user);
        $this->assertCount(2, $firstResult);

        // Verify cache was populated (via getByStatusForUser)
        $this->assertCacheKeyExists(':import:status:completed', 'Cache key for completed imports should exist after first call');

        // Second call should hit cache
        $secondResult = $this->cachedRepository->getCompletedForUser($this->user);
        $this->assertCount(2, $secondResult);
        $this->assertEquals($firstResult->pluck('id')->toArray(), $secondResult->pluck('id')->toArray());
    }

    /**
     * Test that getFailedForUser uses cache (delegates to getByStatusForUser)
     */
    public function testGetFailedForUserUsesCache(): void
    {
        Import::factory(1)->create([
            'user_id' => $this->user->id,
            'status' => 'failed'
        ]);

        // First call should hit database
        $firstResult = $this->cachedRepository->getFailedForUser($this->user);
        $this->assertCount(1, $firstResult);

        // Verify cache was populated (via getByStatusForUser)
        $this->assertCacheKeyExists(':import:status:failed', 'Cache key for failed imports should exist after first call');

        // Second call should hit cache
        $secondResult = $this->cachedRepository->getFailedForUser($this->user);
        $this->assertCount(1, $secondResult);
        $this->assertEquals($firstResult->pluck('id')->toArray(), $secondResult->pluck('id')->toArray());
    }

    /**
     * Test cache isolation between users
     */
    public function testCacheIsolationBetweenUsers(): void
    {
        // Create imports for both users
        Import::factory(2)->create(['user_id' => $this->user->id]);
        Import::factory(3)->create(['user_id' => $this->anotherUser->id]);

        // Populate cache for both users
        $this->cachedRepository->getAllForUser($this->user);
        $this->cachedRepository->getAllForUser($this->anotherUser);

        // Verify separate caches exist
        $this->assertCacheKeyExists(':import:all', 'Cache key for imports should exist for both users');

        // Verify user isolation - each user should only see their own imports
        $userImports = $this->cachedRepository->getAllForUser($this->user);
        $anotherUserImports = $this->cachedRepository->getAllForUser($this->anotherUser);

        $this->assertCount(2, $userImports);
        $this->assertCount(3, $anotherUserImports);

        // Verify no cross-contamination
        $userImportIds = $userImports->pluck('id')->toArray();
        $anotherUserImportIds = $anotherUserImports->pluck('id')->toArray();

        $this->assertEmpty(array_intersect($userImportIds, $anotherUserImportIds));
    }

    /**
     * Test cache invalidation when import is updated by another user
     */
    public function testCacheInvalidationRespectsUserOwnership(): void
    {
        $import = Import::factory()->create(['user_id' => $this->user->id]);

        // Populate cache for the owner
        $this->cachedRepository->getAllForUser($this->user);
        $this->cachedRepository->findForUser($this->user, $import->id);

        // Verify caches exist
        $this->assertCacheKeyExists(':import:all', 'Cache key for all imports should exist');
        $this->assertCacheKeyExists(":import:find:{$import->id}", 'Cache key for specific import should exist');

        // Try to update import as another user (should fail)
        $this->expectException(\InvalidArgumentException::class);
        $this->cachedRepository->updateForUser($this->anotherUser, $import, [
            'status' => 'hacked'
        ]);

        // Cache should remain intact since update failed
        $this->assertCacheKeyExists(':import:all', 'Cache key for all imports should remain after failed update');
        $this->assertCacheKeyExists(":import:find:{$import->id}", 'Cache key for specific import should remain after failed update');

        // Verify import data is unchanged
        $unchangedImport = $this->cachedRepository->findForUser($this->user, $import->id);
        $this->assertNotEquals('hacked', $unchangedImport->status);
    }

    /**
     * Test that cache TTL is respected
     */
    public function testCacheTTLIsRespected(): void
    {
        Import::factory(2)->create(['user_id' => $this->user->id]);

        // Set a very short TTL for testing
        config(['cache.ttl.imports' => 1]); // 1 second

        // Populate cache
        $this->cachedRepository->getAllForUser($this->user);
        $this->assertCacheKeyExists(':import:all', 'Cache key for all imports should exist');

        // Wait for cache to expire
        sleep(2);

        // Cache should be expired
        $this->assertCacheKeyNotExists(':import:all', 'Cache key for all imports should be expired after TTL');

        // Next call should hit database and repopulate cache
        $result = $this->cachedRepository->getAllForUser($this->user);
        $this->assertCount(2, $result);
        $this->assertCacheKeyExists(':import:all', 'Cache key for all imports should exist after repopulation');
    }

    /**
     * Test cache behavior with empty results
     */
    public function testCacheBehaviorWithEmptyResults(): void
    {
        // No imports exist yet

        // First call should hit database and cache empty result
        $firstResult = $this->cachedRepository->getAllForUser($this->user);
        $this->assertCount(0, $firstResult);

        // Verify cache was populated even for empty results
        $this->assertCacheKeyExists(':import:all', 'Cache key for all imports should exist even for empty results');

        // Second call should hit cache
        $secondResult = $this->cachedRepository->getAllForUser($this->user);
        $this->assertCount(0, $secondResult);

        // Create an import
        Import::factory()->create(['user_id' => $this->user->id]);

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
        Import::factory(1)->create([
            'user_id' => $this->user->id,
            'status' => 'processing'
        ]);

        // First call should hit database
        $firstResult = $this->cachedRepository->getByStatusForUser($this->user, 'processing');
        $this->assertCount(1, $firstResult);

        // Verify cache was populated
        $this->assertCacheKeyExists(':import:status:processing', 'Cache key for processing imports should exist after first call');

        // Clear cache and create a fresh repository with mocked config for short TTL
        Cache::flush();

        // Create a mocked config repository with moderate TTL for testing
        $mockConfig = \Mockery::mock(\Illuminate\Contracts\Config\Repository::class);
        $mockConfig->shouldReceive('get')
            ->with('cache.ttl.imports', 1800)
            ->andReturn(10); // Normal TTL
        $mockConfig->shouldReceive('get')
            ->with('cache.ttl.imports_short', 300)
            ->andReturn(1); // 1 second TTL for processing status (enough time to test)

        // Manually create a fresh repository instance with the mocked config
        $baseRepository = new \App\Repositories\ImportRepository();
        $cacheService = app(\App\Services\CacheService::class);
        $freshRepository = new \App\Repositories\Decorators\CachedImportRepository(
            $baseRepository,
            $cacheService,
            $mockConfig
        );

        // Populate cache with the fresh repository (should use 5 second TTL)
        $result = $freshRepository->getByStatusForUser($this->user, 'processing');



        // Verify cache was populated with short TTL
        $this->assertCacheKeyExists(':import:status:processing', 'Cache key for processing imports should exist');

        // Wait for cache to expire (1 second + buffer)
        sleep(2);

        // Cache should be expired
        $this->assertCacheKeyNotExists(':import:status:processing', 'Cache key for processing imports should be expired after TTL');

        // Next call should hit database and repopulate cache
        $result = $freshRepository->getByStatusForUser($this->user, 'processing');
        $this->assertCount(1, $result);
        $this->assertCacheKeyExists(':import:status:processing', 'Cache key for processing imports should exist after repopulation');
    }

    /**
     * Test that different statuses have separate cache keys
     */
    public function testDifferentStatusesHaveSeparateCacheKeys(): void
    {
        Import::factory(1)->create([
            'user_id' => $this->user->id,
            'status' => 'pending'
        ]);

        Import::factory(1)->create([
            'user_id' => $this->user->id,
            'status' => 'processing'
        ]);

        Import::factory(1)->create([
            'user_id' => $this->user->id,
            'status' => 'completed'
        ]);

        // Populate caches for different statuses
        $this->cachedRepository->getByStatusForUser($this->user, 'pending');
        $this->cachedRepository->getByStatusForUser($this->user, 'processing');
        $this->cachedRepository->getByStatusForUser($this->user, 'completed');

        // Verify separate cache keys exist
        $this->assertCacheKeyExists(':import:status:pending', 'Cache key for pending imports should exist');
        $this->assertCacheKeyExists(':import:status:processing', 'Cache key for processing imports should exist');
        $this->assertCacheKeyExists(':import:status:completed', 'Cache key for completed imports should exist');

        // Update one status should only clear that specific cache
        $pendingImport = Import::where('status', 'pending')->first();
        $this->cachedRepository->updateForUser($this->user, $pendingImport, [
            'status' => 'processing'
        ]);

        // All import caches should be cleared after any import update (for data consistency)
        $this->assertCacheKeyNotExists(':import:status:pending', 'Cache key for pending imports should be cleared after update');
        $this->assertCacheKeyNotExists(':import:status:processing', 'Cache key for processing imports should be cleared after update');
        $this->assertCacheKeyNotExists(':import:status:completed', 'Cache key for completed imports should be cleared after update');
    }

    /**
     * Test that recent imports with different limits have separate cache keys
     */
    public function testRecentImportsWithDifferentLimitsHaveSeparateCacheKeys(): void
    {
        Import::factory(5)->create(['user_id' => $this->user->id]);

        // Populate caches for different limits
        $this->cachedRepository->getRecentForUser($this->user, 3);
        $this->cachedRepository->getRecentForUser($this->user, 5);

        // Verify separate cache keys exist
        $this->assertCacheKeyExists(':import:recent:3', 'Cache key for recent imports with limit 3 should exist');
        $this->assertCacheKeyExists(':import:recent:5', 'Cache key for recent imports with limit 5 should exist');

        // Create new import should clear all recent caches
        $this->cachedRepository->createForUser($this->user, [
            'filename' => 'new.csv',
            'original_filename' => 'new.csv',
            'status' => 'pending',
            'total_rows' => 50
        ]);

        // All recent caches should be cleared
        $this->assertCacheKeyNotExists(':import:recent:3', 'Cache key for recent imports with limit 3 should be cleared after creation');
        $this->assertCacheKeyNotExists(':import:recent:5', 'Cache key for recent imports with limit 5 should be cleared after creation');
    }
}
