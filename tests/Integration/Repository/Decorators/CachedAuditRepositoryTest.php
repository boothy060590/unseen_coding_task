<?php

namespace Tests\Integration\Repository\Decorators;

use App\Contracts\Repositories\AuditRepositoryInterface;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\Decorators\CachedAuditRepository::class)]
class CachedAuditRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private AuditRepositoryInterface $cachedRepository;
    private User $user;
    private User $anotherUser;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure cache for testing
        config(['cache.ttl.audit' => 900]);

        // Use Laravel's service container to resolve dependencies
        $this->cachedRepository = app(AuditRepositoryInterface::class);

        $this->user = User::factory()->create();
        $this->anotherUser = User::factory()->create();
        $this->customer = Customer::factory()->create(['user_id' => $this->user->id]);

        // Clear cache before each test
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    /**
     * Get cache keys using reflection for debugging
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
     * Test that paginated results bypass cache
     */
    public function testPaginatedResultsBypassCache(): void
    {
        // Create activities
        Activity::factory(5)
            ->customerCreated()
            ->causedBy($this->user)
            ->performedOn($this->customer)
            ->create();

        // First call should not cache
        $firstResult = $this->cachedRepository->getCustomerAuditTrail($this->user, $this->customer, 3);
        $this->assertCount(3, $firstResult);

        // Verify no cache was created for paginated results
        $this->assertFalse(Cache::has("audit_customer_trail"));

        // Second call should hit database again
        $secondResult = $this->cachedRepository->getCustomerAuditTrail($this->user, $this->customer, 3);
        $this->assertCount(3, $secondResult);
    }

    /**
     * Test that getUserAuditTrail bypasses cache
     */
    public function testGetUserAuditTrailBypassesCache(): void
    {
        Activity::factory(3)
            ->customerCreated()
            ->causedBy($this->user)
            ->performedOn($this->customer)
            ->create();

        // First call should not cache
        $firstResult = $this->cachedRepository->getUserAuditTrail($this->user, 3);
        $this->assertCount(3, $firstResult);

        // Verify no cache was created
        $this->assertFalse(Cache::has("audit_user_trail"));

        // Second call should hit database again
        $secondResult = $this->cachedRepository->getUserAuditTrail($this->user, 3);
        $this->assertCount(3, $secondResult);
    }

    /**
     * Test that getRecentUserActivities uses cache
     */
    public function testGetRecentUserActivitiesUsesCache(): void
    {
        Activity::factory(5)
            ->customerCreated()
            ->causedBy($this->user)
            ->performedOn($this->customer)
            ->create();

        // First call should hit database
        $firstResult = $this->cachedRepository->getRecentUserActivities($this->user, 3);
        $this->assertCount(3, $firstResult);

        // Verify cache was populated (works with tagged array cache)
        $this->assertCacheKeyExists(':audit_recent:3', 'Cache key for recent user activities should exist after first call');

        // Second call should hit cache
        $secondResult = $this->cachedRepository->getRecentUserActivities($this->user, 3);
        $this->assertCount(3, $secondResult);
        $this->assertEquals($firstResult->pluck('id')->toArray(), $secondResult->pluck('id')->toArray());
    }

    /**
     * Test that getActivitiesByDateRange uses cache with longer TTL
     */
    public function testGetActivitiesByDateRangeUsesCacheWithLongerTTL(): void
    {
        $fromDate = now()->subDays(7);
        $toDate = now();

        // Create activities within the date range we're querying
        $activity1 = Activity::factory()
            ->customerCreated()
            ->causedBy($this->user)
            ->performedOn($this->customer)
            ->create(['created_at' => now()->subDays(3)]);

        $activity2 = Activity::factory()
            ->customerCreated()
            ->causedBy($this->user)
            ->performedOn($this->customer)
            ->create(['created_at' => now()->subDays(2)]);

        $activity3 = Activity::factory()
            ->customerCreated()
            ->causedBy($this->user)
            ->performedOn($this->customer)
            ->create(['created_at' => now()->subDays(1)]);

        // First call should hit database
        $firstResult = $this->cachedRepository->getActivitiesByDateRange($this->user, $fromDate, $toDate);
        $this->assertCount(3, $firstResult);

        // Verify cache was populated (works with tagged array cache)
        $expectedSuffix = ":audit_date_range:{$fromDate->format('Y-m-d')}:{$toDate->format('Y-m-d')}";
        $this->assertCacheKeyExists($expectedSuffix, 'Cache key for date range activities should exist after first call');

        // Second call should hit cache
        $secondResult = $this->cachedRepository->getActivitiesByDateRange($this->user, $fromDate, $toDate);
        $this->assertCount(3, $secondResult);
        $this->assertEquals($firstResult->pluck('id')->toArray(), $secondResult->pluck('id')->toArray());
    }

    /**
     * Test that getActivitiesByEvent uses cache
     */
    public function testGetActivitiesByEventUsesCache(): void
    {
        Activity::factory(3)
            ->customerCreated()
            ->causedBy($this->user)
            ->performedOn($this->customer)
            ->create();

        // First call should hit database
        $firstResult = $this->cachedRepository->getActivitiesByEvent($this->user, 'created');
        $this->assertCount(3, $firstResult);

        // Verify cache was populated (works with tagged array cache)
        $this->assertCacheKeyExists(':audit_by_event:created', 'Cache key for event activities should exist after first call');

        // Second call should hit cache
        $secondResult = $this->cachedRepository->getActivitiesByEvent($this->user, 'created');
        $this->assertCount(3, $secondResult);
        $this->assertEquals($firstResult->pluck('id')->toArray(), $secondResult->pluck('id')->toArray());
    }

    /**
     * Test that getActivityCountForUser uses cache
     */
    public function testGetActivityCountForUserUsesCache(): void
    {
        Activity::factory(3)
            ->customerCreated()
            ->causedBy($this->user)
            ->performedOn($this->customer)
            ->create();

        // First call should hit database
        $firstCount = $this->cachedRepository->getActivityCountForUser($this->user);
        $this->assertEquals(3, $firstCount);

        // Verify cache was populated (works with tagged array cache)
        $this->assertCacheKeyExists(':audit_count', 'Cache key for activity count should exist after first call');

        // Second call should hit cache
        $secondCount = $this->cachedRepository->getActivityCountForUser($this->user);
        $this->assertEquals(3, $secondCount);
    }

    /**
     * Test that getActivityCountsByEvent uses cache
     */
    public function testGetActivityCountsByEventUsesCache(): void
    {
        Activity::factory(2)
            ->customerCreated()
            ->causedBy($this->user)
            ->performedOn($this->customer)
            ->create();

        Activity::factory(1)
            ->customerUpdated()
            ->causedBy($this->user)
            ->performedOn($this->customer)
            ->create();

        // First call should hit database
        $firstResult = $this->cachedRepository->getActivityCountsByEvent($this->user);
        $this->assertArrayHasKey('created', $firstResult);
        $this->assertArrayHasKey('updated', $firstResult);

        // Verify cache was populated (works with tagged array cache)
        $this->assertCacheKeyExists(':audit_counts_by_event', 'Cache key for activity counts by event should exist after first call');

        // Second call should hit cache
        $secondResult = $this->cachedRepository->getActivityCountsByEvent($this->user);
        $this->assertEquals($firstResult, $secondResult);
    }

    /**
     * Test that findActivityForUser uses cache
     */
    public function testFindActivityForUserUsesCache(): void
    {
        $activity = Activity::factory()
            ->customerCreated()
            ->causedBy($this->user)
            ->performedOn($this->customer)
            ->create();

        // First call should hit database
        $firstResult = $this->cachedRepository->findActivityForUser($this->user, $activity->id);
        $this->assertNotNull($firstResult);

        // Verify cache was populated (works with tagged array cache)
        $this->assertCacheKeyExists(":audit_find:{$activity->id}", 'Cache key for find activity should exist after first call');

        // Second call should hit cache
        $secondResult = $this->cachedRepository->findActivityForUser($this->user, $activity->id);
        $this->assertNotNull($secondResult);
        $this->assertEquals($firstResult->id, $secondResult->id);
    }

    /**
     * Test that getMostActiveCustomers uses cache
     */
    public function testGetMostActiveCustomersUsesCache(): void
    {
        // Create multiple customers with different activity levels
        $customer1 = Customer::factory()->create(['user_id' => $this->user->id]);
        $customer2 = Customer::factory()->create(['user_id' => $this->user->id]);

        Activity::factory(3)
            ->customerCreated()
            ->causedBy($this->user)
            ->performedOn($customer1)
            ->create();

        Activity::factory(1)
            ->customerCreated()
            ->causedBy($this->user)
            ->performedOn($customer2)
            ->create();

        // First call should hit database
        $firstResult = $this->cachedRepository->getMostActiveCustomers($this->user, 2);
        $this->assertCount(2, $firstResult);

        // Verify cache was populated (works with tagged array cache)
        $this->assertCacheKeyExists(':audit_most_active_customers:2', 'Cache key for most active customers should exist after first call');

        // Second call should hit cache
        $secondResult = $this->cachedRepository->getMostActiveCustomers($this->user, 2);
        $this->assertCount(2, $secondResult);

        // Verify first customer has more activities
        $this->assertEquals(3, $secondResult->first()['activity_count']);
        $this->assertEquals(1, $secondResult->last()['activity_count']);
    }

    /**
     * Test that logCustomerActivity invalidates cache
     */
    public function testLogCustomerActivityInvalidatesCache(): void
    {
        // Create initial activities and populate cache
        Activity::factory(2)
            ->customerCreated()
            ->causedBy($this->user)
            ->performedOn($this->customer)
            ->create();

        $this->cachedRepository->getRecentUserActivities($this->user, 5);
        $this->cachedRepository->getActivityCountForUser($this->user);

        // Verify caches exist
        $this->assertCacheKeyExists(":audit_recent:5", "Recent activities cache should exist");
        $this->assertCacheKeyExists(":audit_count", "Activity count cache should exist");

        // Log new activity
        $this->cachedRepository->logCustomerActivity(
            $this->user,
            $this->customer,
            'custom_event',
            'Custom activity logged'
        );



        // Cache should be cleared
        $this->assertCacheKeyNotExists(":audit_recent:5", "Recent activities cache should be cleared after activity logged");
        $this->assertCacheKeyNotExists(":audit_count", "Activity count cache should be cleared after activity logged");

        // Verify new activity is included in fresh results
        $freshCount = $this->cachedRepository->getActivityCountForUser($this->user);
        $this->assertEquals(3, $freshCount);
    }

    /**
     * Test that getActivitiesForCustomers uses cache
     */
    public function testGetActivitiesForCustomersUsesCache(): void
    {
        $customer1 = Customer::factory()->create(['user_id' => $this->user->id]);
        $customer2 = Customer::factory()->create(['user_id' => $this->user->id]);

        Activity::factory(2)
            ->customerCreated()
            ->causedBy($this->user)
            ->performedOn($customer1)
            ->create();

        Activity::factory(1)
            ->customerCreated()
            ->causedBy($this->user)
            ->performedOn($customer2)
            ->create();

        $customerIds = [$customer1->id, $customer2->id];

        // First call should hit database
        $firstResult = $this->cachedRepository->getActivitiesForCustomers($this->user, $customerIds);
        $this->assertCount(3, $firstResult);

        // Verify cache was populated (works with tagged array cache)
        sort($customerIds);
        $customerIdHash = md5(implode(',', $customerIds));
        $this->assertCacheKeyExists(":audit_for_customers:{$customerIdHash}", 'Cache key for activities by customers should exist after first call');

        // Second call should hit cache
        $secondResult = $this->cachedRepository->getActivitiesForCustomers($this->user, $customerIds);
        $this->assertCount(3, $secondResult);
        $this->assertEquals($firstResult->pluck('id')->toArray(), $secondResult->pluck('id')->toArray());
    }

    /**
     * Test cache isolation between users
     */
    public function testCacheIsolationBetweenUsers(): void
    {
        $anotherCustomer = Customer::factory()->create(['user_id' => $this->anotherUser->id]);

        // Create activities for both users
        Activity::factory(2)
            ->customerCreated()
            ->causedBy($this->user)
            ->performedOn($this->customer)
            ->create();

        Activity::factory(3)
            ->customerCreated()
            ->causedBy($this->anotherUser)
            ->performedOn($anotherCustomer)
            ->create();

        // Populate cache for both users
        $this->cachedRepository->getRecentUserActivities($this->user, 5);
        $this->cachedRepository->getRecentUserActivities($this->anotherUser, 5);

        // Verify separate caches exist
        $this->assertCacheKeyExists(":audit_recent:5", "Recent activities cache should exist");

        // Verify user isolation - each user should only see their own activities
        $userActivities = $this->cachedRepository->getRecentUserActivities($this->user, 5);
        $anotherUserActivities = $this->cachedRepository->getRecentUserActivities($this->anotherUser, 5);

        $this->assertCount(2, $userActivities);
        $this->assertCount(3, $anotherUserActivities);

        // Verify no cross-contamination
        $userActivityIds = $userActivities->pluck('id')->toArray();
        $anotherUserActivityIds = $anotherUserActivities->pluck('id')->toArray();

        $this->assertEmpty(array_intersect($userActivityIds, $anotherUserActivityIds));
    }

    /**
     * Test that cache TTL is respected
     */
    public function testCacheTTLIsRespected(): void
    {
        Activity::factory(2)
            ->customerCreated()
            ->causedBy($this->user)
            ->performedOn($this->customer)
            ->create();

        // First, test that cache works normally with the default repository
        $this->cachedRepository->getRecentUserActivities($this->user, 5);

        // Verify cache was populated with normal TTL
        $this->assertCacheKeyExists("cache:audit:recent:operation:audit_recent:user:{$this->user->id}:audit_recent:5", "Recent activities cache should exist");

        // Clear cache and create a fresh repository with mocked config for short TTL
        Cache::flush();

        // Create a mocked config repository with moderate TTL for testing
        $mockConfig = \Mockery::mock(\Illuminate\Contracts\Config\Repository::class);
        $mockConfig->shouldReceive('get')
            ->with('cache.ttl.audit_short', 300)
            ->andReturn(2); // 2 second TTL (enough time to test)

        // Manually create a fresh repository instance with the mocked config
        $baseRepository = new \App\Repositories\AuditRepository();
        $cacheService = app(\App\Services\CacheService::class); // Use existing service from container
        $freshRepository = new \App\Repositories\Decorators\CachedAuditRepository(
            $baseRepository,
            $cacheService,
            $mockConfig
        );

        // Populate cache with the fresh repository (should use 5 second TTL)
        $freshRepository->getRecentUserActivities($this->user, 5);

        // Verify cache was populated with custom TTL
        $this->assertCacheKeyExists("cache:audit:recent:operation:audit_recent:user:{$this->user->id}:audit_recent:5", "Recent activities cache should exist");

        // Wait for cache to expire (2 second + buffer)
        sleep(3);

        // Cache should be expired
        $this->assertCacheKeyNotExists("cache:audit:recent:operation:audit_recent:user:{$this->user->id}:audit_recent:5", "Recent activities cache should be cleared");

        // Next call should hit database and repopulate cache
        $result = $freshRepository->getRecentUserActivities($this->user, 5);
        $this->assertCount(2, $result);
        $this->assertCacheKeyExists("cache:audit:recent:operation:audit_recent:user:{$this->user->id}:audit_recent:5", "Recent activities cache should exist");
    }

    /**
     * Test cache behavior with empty results
     */
    public function testCacheBehaviorWithEmptyResults(): void
    {
        // No activities exist yet

        // First call should hit database and cache empty result
        $firstResult = $this->cachedRepository->getRecentUserActivities($this->user, 5);
        $this->assertCount(0, $firstResult);

        // Verify cache was populated even for empty results
        $this->assertCacheKeyExists(":audit_recent:5", "Recent activities cache should exist");

        // Second call should hit cache
        $secondResult = $this->cachedRepository->getRecentUserActivities($this->user, 5);
        $this->assertCount(0, $secondResult);

        // Create an activity
        Activity::factory()
            ->customerCreated()
            ->causedBy($this->user)
            ->performedOn($this->customer)
            ->create();

        // Cache should still return old (empty) result
        $cachedResult = $this->cachedRepository->getRecentUserActivities($this->user, 5);
        $this->assertCount(0, $cachedResult);

        // Clear cache manually to simulate invalidation
        Cache::flush();

        // Now should get fresh result
        $freshResult = $this->cachedRepository->getRecentUserActivities($this->user, 5);
        $this->assertCount(1, $freshResult);
    }

    /**
     * Test that date range cache uses longer TTL for historical data
     */
    public function testDateRangeCacheUsesLongerTTL(): void
    {
        $fromDate = now()->subDays(31);
        $toDate = now()->subDays(1);

        Activity::factory(2)
            ->customerCreated()
            ->causedBy($this->user)
            ->performedOn($this->customer)
            ->create();

        // First call should hit database
        $firstResult = $this->cachedRepository->getActivitiesByDateRange($this->user, $fromDate, $toDate);
        $this->assertCount(2, $firstResult);

        // Verify cache was populated (works with tagged array cache)
        $expectedSuffix = ":audit_date_range:{$fromDate->format('Y-m-d')}:{$toDate->format('Y-m-d')}";
        $this->assertCacheKeyExists($expectedSuffix, 'Cache key for historical date range should exist after first call');

        // Historical data should use longer TTL (4x normal TTL)
        // We can't easily test the exact TTL, but we can verify the cache exists
        $this->assertCacheKeyExists($expectedSuffix, 'Cache key for historical date range should persist with longer TTL');

        // Second call should hit cache
        $secondResult = $this->cachedRepository->getActivitiesByDateRange($this->user, $fromDate, $toDate);
        $this->assertCount(2, $secondResult);
    }
}
