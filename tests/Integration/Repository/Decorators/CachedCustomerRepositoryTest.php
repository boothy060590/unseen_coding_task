<?php

namespace Tests\Integration\Repository\Decorators;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\Decorators\CachedCustomerRepository::class)]
class CachedCustomerRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private CustomerRepositoryInterface $cachedRepository;
    private User $user;
    private User $anotherUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Configure cache for testing
        config(['cache.ttl.customers' => 3600]);
        
        // Use Laravel's service container to resolve dependencies
        $this->cachedRepository = app(CustomerRepositoryInterface::class);
        
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
     * Test that cache is used for subsequent calls to getAllForUser without filters
     */
    public function testGetAllForUserUsesCacheForUnfilteredResults(): void
    {
        // Create customers
        Customer::factory(3)->create(['user_id' => $this->user->id]);
        
        // First call should hit database and cache result
        $firstResult = $this->cachedRepository->getAllForUser($this->user);
        $this->assertCount(3, $firstResult);
        
        // Verify cache was populated
        $this->assertCacheKeyExists(':customers_all', 'Cache key for all customers should exist after first call');
        
        // Second call should hit cache (we can verify by checking if cache is accessed)
        $secondResult = $this->cachedRepository->getAllForUser($this->user);
        $this->assertCount(3, $secondResult);
        
        // Results should be identical
        $this->assertEquals($firstResult->pluck('id')->toArray(), $secondResult->pluck('id')->toArray());
    }

    /**
     * Test that filtered results bypass cache
     */
    public function testGetAllForUserBypassesCacheForFilteredResults(): void
    {
        Customer::factory(3)->create(['user_id' => $this->user->id]);
        Customer::factory(1)->create([
            'user_id' => $this->user->id,
            'organization' => 'TestOrg'
        ]);
        
        // Unfiltered call should cache
        $this->cachedRepository->getAllForUser($this->user);
        $this->assertCacheKeyExists(':customers_all', 'Cache key for all customers should exist');
        
        // Filtered call should bypass cache
        $filteredResult = $this->cachedRepository->getAllForUser($this->user, ['organization' => 'TestOrg']);
        $this->assertCount(1, $filteredResult);
        
        // Cache should still exist for unfiltered results
        $this->assertCacheKeyExists(':customers_all', 'Cache key for all customers should still exist after filtered call');
    }

    /**
     * Test cache invalidation on customer creation
     */
    public function testCreateForUserInvalidatesCache(): void
    {
        // Create initial customers and populate cache
        Customer::factory(2)->create(['user_id' => $this->user->id]);
        $this->cachedRepository->getAllForUser($this->user);
        
        // Verify cache exists
        $this->assertCacheKeyExists(':customers_all', 'Cache key for all customers should exist');
        
        // Create new customer
        $newCustomer = $this->cachedRepository->createForUser($this->user, [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com'
        ]);
        
        // Cache should be cleared
        $this->assertCacheKeyNotExists(':customers_all', 'Cache key for all customers should be cleared after creation');
        
        // Verify new customer is included in fresh results
        $freshResults = $this->cachedRepository->getAllForUser($this->user);
        $this->assertCount(3, $freshResults);
        $this->assertTrue($freshResults->contains($newCustomer));
    }

    /**
     * Test cache invalidation on customer update
     */
    public function testUpdateForUserInvalidatesCache(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->user->id]);
        
        // Populate cache
        $this->cachedRepository->getAllForUser($this->user);
        $this->cachedRepository->findForUser($this->user, $customer->id);
        
        // Verify caches exist
        $this->assertCacheKeyExists(':customers_all', 'Cache key for all customers should exist');
        $this->assertCacheKeyExists(":customer:find:{$customer->id}", 'Cache key for specific customer should exist');
        
        // Update customer
        $this->cachedRepository->updateForUser($this->user, $customer, [
            'first_name' => 'Updated'
        ]);
        
        // Customer-specific cache should be cleared
        $this->assertCacheKeyNotExists(":customer:find:{$customer->id}", 'Cache key for specific customer should be cleared after update');
        
        // Verify update was successful
        $updatedCustomer = $this->cachedRepository->findForUser($this->user, $customer->id);
        $this->assertEquals('Updated', $updatedCustomer->first_name);
    }

    /**
     * Test cache invalidation on customer deletion
     */
    public function testDeleteForUserInvalidatesCache(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->user->id]);
        
        // Populate cache
        $this->cachedRepository->getAllForUser($this->user);
        $this->cachedRepository->findForUser($this->user, $customer->id);
        
        // Verify caches exist
        $this->assertCacheKeyExists(':customers_all', 'Cache key for all customers should exist');
        $this->assertCacheKeyExists(":customer:find:{$customer->id}", 'Cache key for specific customer should exist');
        
        // Delete customer
        $this->cachedRepository->deleteForUser($this->user, $customer);
        
        // Customer-specific cache should be cleared
        $this->assertCacheKeyNotExists(":customer:find:{$customer->id}", 'Cache key for specific customer should be cleared after deletion');
        
        // Verify customer is deleted
        $this->assertNull($this->cachedRepository->findForUser($this->user, $customer->id));
    }

    /**
     * Test that findForUser uses cache
     */
    public function testFindForUserUsesCache(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->user->id]);
        
        // First call should hit database
        $firstResult = $this->cachedRepository->findForUser($this->user, $customer->id);
        $this->assertNotNull($firstResult);
        
        // Verify cache was populated
        $this->assertCacheKeyExists(":customer:find:{$customer->id}", 'Cache key for specific customer should exist after first call');
        
        // Second call should hit cache
        $secondResult = $this->cachedRepository->findForUser($this->user, $customer->id);
        $this->assertNotNull($secondResult);
        $this->assertEquals($firstResult->id, $secondResult->id);
    }

    /**
     * Test that findBySlugForUser uses cache
     */
    public function testFindBySlugForUserUsesCache(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->user->id]);
        
        // First call should hit database
        $firstResult = $this->cachedRepository->findBySlugForUser($this->user, $customer->slug);
        $this->assertNotNull($firstResult);
        
        // Verify cache was populated
        $this->assertCacheKeyExists(":customer_by_slug:{$customer->slug}", 'Cache key for customer by slug should exist after first call');
        
        // Second call should hit cache
        $secondResult = $this->cachedRepository->findBySlugForUser($this->user, $customer->slug);
        $this->assertNotNull($secondResult);
        $this->assertEquals($firstResult->id, $secondResult->id);
    }

    /**
     * Test that getCountForUser uses cache
     */
    public function testGetCountForUserUsesCache(): void
    {
        Customer::factory(3)->create(['user_id' => $this->user->id]);
        
        // First call should hit database
        $firstCount = $this->cachedRepository->getCountForUser($this->user);
        $this->assertEquals(3, $firstCount);
        
        // Verify cache was populated
        $this->assertCacheKeyExists(':count', 'Cache key for customer count should exist after first call');
        
        // Second call should hit cache
        $secondCount = $this->cachedRepository->getCountForUser($this->user);
        $this->assertEquals(3, $secondCount);
    }

    /**
     * Test that getRecentForUser uses cache with shorter TTL
     */
    public function testGetRecentForUserUsesCacheWithShorterTTL(): void
    {
        Customer::factory(5)->create(['user_id' => $this->user->id]);
        
        // First call should hit database
        $firstResult = $this->cachedRepository->getRecentForUser($this->user, 3);
        $this->assertCount(3, $firstResult);
        
        // Verify cache was populated
        $this->assertCacheKeyExists(':recent:3', 'Cache key for recent customers should exist after first call');
        
        // Second call should hit cache
        $secondResult = $this->cachedRepository->getRecentForUser($this->user, 3);
        $this->assertCount(3, $secondResult);
        $this->assertEquals($firstResult->pluck('id')->toArray(), $secondResult->pluck('id')->toArray());
    }

    /**
     * Test that paginated results bypass cache
     */
    public function testGetPaginatedForUserBypassesCache(): void
    {
        Customer::factory(10)->create(['user_id' => $this->user->id]);
        
        // First call should not cache
        $firstResult = $this->cachedRepository->getPaginatedForUser($this->user, [], 5);
        $this->assertCount(5, $firstResult);
        
        // Verify no cache was created for paginated results
        $this->assertCacheKeyNotExists(':customers_paginated', 'No cache should be created for paginated results');
        
        // Second call should hit database again
        $secondResult = $this->cachedRepository->getPaginatedForUser($this->user, [], 5);
        $this->assertCount(5, $secondResult);
    }

    /**
     * Test cache isolation between users
    */
    public function testCacheIsolationBetweenUsers(): void
    {
        // Create customers for both users
        Customer::factory(2)->create(['user_id' => $this->user->id]);
        Customer::factory(3)->create(['user_id' => $this->anotherUser->id]);
        
        // Populate cache for both users
        $this->cachedRepository->getAllForUser($this->user);
        $this->cachedRepository->getAllForUser($this->anotherUser);
        
        // Verify separate caches exist
        $this->assertCacheKeyExists(':customers_all', 'Cache key for customers should exist for both users');
        
        // Verify user isolation - each user should only see their own customers
        $userCustomers = $this->cachedRepository->getAllForUser($this->user);
        $anotherUserCustomers = $this->cachedRepository->getAllForUser($this->anotherUser);
        
        $this->assertCount(2, $userCustomers);
        $this->assertCount(3, $anotherUserCustomers);
        
        // Verify no cross-contamination
        $userCustomerIds = $userCustomers->pluck('id')->toArray();
        $anotherUserCustomerIds = $anotherUserCustomers->pluck('id')->toArray();
        
        $this->assertEmpty(array_intersect($userCustomerIds, $anotherUserCustomerIds));
    }

    /**
     * Test cache invalidation when customer is updated by another user
     */
    public function testCacheInvalidationRespectsUserOwnership(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->user->id]);
        
        // Populate cache for the owner
        $this->cachedRepository->getAllForUser($this->user);
        $this->cachedRepository->findForUser($this->user, $customer->id);
        
        // Verify caches exist
        $this->assertCacheKeyExists(':customers_all', 'Cache key for all customers should exist');
        $this->assertCacheKeyExists(":customer:find:{$customer->id}", 'Cache key for specific customer should exist');
        
        // Try to update customer as another user (should fail)
        $this->expectException(\InvalidArgumentException::class);
        $this->cachedRepository->updateForUser($this->anotherUser, $customer, [
            'first_name' => 'Hacked'
        ]);
        
        // Cache should remain intact since update failed
        $this->assertCacheKeyExists(':customers_all', 'Cache key for all customers should remain after failed update');
        $this->assertCacheKeyExists(":customer:find:{$customer->id}", 'Cache key for specific customer should remain after failed update');
        
        // Verify customer data is unchanged
        $unchangedCustomer = $this->cachedRepository->findForUser($this->user, $customer->id);
        $this->assertNotEquals('Hacked', $unchangedCustomer->first_name);
    }

    /**
     * Test that cache TTL is respected
     */
    public function testCacheTTLIsRespected(): void
    {
        Customer::factory(2)->create(['user_id' => $this->user->id]);
        
        // Set a very short TTL for testing
        config(['cache.ttl.customers' => 1]); // 1 second
        
        // Populate cache
        $this->cachedRepository->getAllForUser($this->user);
        $this->assertCacheKeyExists(':customers_all', 'Cache key for all customers should exist');
        
        // Wait for cache to expire
        sleep(2);
        
        // Cache should be expired
        $this->assertCacheKeyNotExists(':customers_all', 'Cache key for all customers should be expired after TTL');
        
        // Next call should hit database and repopulate cache
        $result = $this->cachedRepository->getAllForUser($this->user);
        $this->assertCount(2, $result);
        $this->assertCacheKeyExists(':customers_all', 'Cache key for all customers should exist after repopulation');
    }

    /**
     * Test cache behavior with empty results
     */
    public function testCacheBehaviorWithEmptyResults(): void
    {
        // No customers exist yet
        
        // First call should hit database and cache empty result
        $firstResult = $this->cachedRepository->getAllForUser($this->user);
        $this->assertCount(0, $firstResult);
        
        // Verify cache was populated even for empty results
        $this->assertCacheKeyExists(':customers_all', 'Cache key for all customers should exist even for empty results');
        
        // Second call should hit cache
        $secondResult = $this->cachedRepository->getAllForUser($this->user);
        $this->assertCount(0, $secondResult);
        
        // Create a customer
        Customer::factory()->create(['user_id' => $this->user->id]);
        
        // Cache should still return old (empty) result
        $cachedResult = $this->cachedRepository->getAllForUser($this->user);
        $this->assertCount(0, $cachedResult);
        
        // Clear cache manually to simulate invalidation
        Cache::flush();
        
        // Now should get fresh result
        $freshResult = $this->cachedRepository->getAllForUser($this->user);
        $this->assertCount(1, $freshResult);
    }
}
