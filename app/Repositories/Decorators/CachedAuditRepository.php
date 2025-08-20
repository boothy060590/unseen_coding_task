<?php

namespace App\Repositories\Decorators;

use App\Contracts\Repositories\AuditRepositoryInterface;
use App\Models\Customer;
use App\Models\User;
use App\Services\CacheService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Cache decorator for AuditRepository to improve performance
 */
class CachedAuditRepository implements AuditRepositoryInterface
{
    /**
     * Cache TTL in seconds (15 minutes - shorter than other entities as audit data changes frequently)
     */
    private const CACHE_TTL = 900;

    /**
     * Short cache TTL for very dynamic data (5 minutes)
     */
    private const SHORT_CACHE_TTL = 300;

    /**
     * Constructor
     *
     * @param AuditRepositoryInterface $repository
     * @param CacheService $cacheService
     */
    public function __construct(
        private AuditRepositoryInterface $repository,
        private CacheService $cacheService
    ) {}

    /**
     * Get audit trail for a specific customer
     *
     * @param User $user
     * @param Customer $customer
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getCustomerAuditTrail(User $user, Customer $customer, int $perPage = 15): LengthAwarePaginator
    {
        // Don't cache paginated results as they change frequently and have many variations
        return $this->repository->getCustomerAuditTrail($user, $customer, $perPage);
    }

    /**
     * Get all audit activities for a user's customers
     *
     * @param User $user
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUserAuditTrail(User $user, int $perPage = 15): LengthAwarePaginator
    {
        // Don't cache paginated results as they change frequently and have many variations
        return $this->repository->getUserAuditTrail($user, $perPage);
    }

    /**
     * Get recent audit activities for a user
     *
     * @param User $user
     * @param int $limit
     * @return Collection<int, Activity>
     */
    public function getRecentUserActivities(User $user, int $limit = 10): Collection
    {
        $cacheInfo = $this->cacheService->getUserCacheInfo($user->id, 'audit_recent', $limit);

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            [...$cacheInfo['tags'], 'audit:recent'],
            self::SHORT_CACHE_TTL,
            fn() => $this->repository->getRecentUserActivities($user, $limit)
        );
    }

    /**
     * Get audit activities by date range for a user
     *
     * @param User $user
     * @param \DateTimeInterface $fromDate
     * @param \DateTimeInterface $toDate
     * @return Collection<int, Activity>
     */
    public function getActivitiesByDateRange(User $user, \DateTimeInterface $fromDate, \DateTimeInterface $toDate): Collection
    {
        // Cache date range queries with longer TTL as historical data doesn't change
        $cacheInfo = $this->cacheService->getUserCacheInfo(
            $user->id,
            'audit_date_range',
            $fromDate->format('Y-m-d'),
            $toDate->format('Y-m-d')
        );

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            [...$cacheInfo['tags'], 'audit:date_range'],
            self::CACHE_TTL * 4, // Longer TTL for historical data
            fn() => $this->repository->getActivitiesByDateRange($user, $fromDate, $toDate)
        );
    }

    /**
     * Get audit activities by event type for a user
     *
     * @param User $user
     * @param string $event
     * @return Collection<int, Activity>
     */
    public function getActivitiesByEvent(User $user, string $event): Collection
    {
        $cacheInfo = $this->cacheService->getUserCacheInfo($user->id, 'audit_by_event', $event);

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            [...$cacheInfo['tags'], 'audit:event', "audit:event:{$event}"],
            self::CACHE_TTL,
            fn() => $this->repository->getActivitiesByEvent($user, $event)
        );
    }

    /**
     * Get audit activity count for a user
     *
     * @param User $user
     * @return int
     */
    public function getActivityCountForUser(User $user): int
    {
        $cacheInfo = $this->cacheService->getUserCacheInfo($user->id, 'audit_count');

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            [...$cacheInfo['tags'], 'audit:count'],
            self::CACHE_TTL,
            fn() => $this->repository->getActivityCountForUser($user)
        );
    }

    /**
     * Get audit activity count by event type for a user
     *
     * @param User $user
     * @return array<string, int>
     */
    public function getActivityCountsByEvent(User $user): array
    {
        $cacheInfo = $this->cacheService->getUserCacheInfo($user->id, 'audit_counts_by_event');

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            [...$cacheInfo['tags'], 'audit:count', 'audit:event'],
            self::CACHE_TTL,
            fn() => $this->repository->getActivityCountsByEvent($user)
        );
    }

    /**
     * Find specific audit activity for a user
     *
     * @param User $user
     * @param int $activityId
     * @return Activity|null
     */
    public function findActivityForUser(User $user, int $activityId): ?Activity
    {
        $cacheInfo = $this->cacheService->getUserCacheInfo($user->id, 'audit_find', $activityId);

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            [...$cacheInfo['tags'], "audit:activity:{$activityId}"],
            self::CACHE_TTL,
            fn() => $this->repository->findActivityForUser($user, $activityId)
        );
    }

    /**
     * Get most active customers by audit count
     *
     * @param User $user
     * @param int $limit
     * @return Collection<int, array>
     */
    public function getMostActiveCustomers(User $user, int $limit = 5): Collection
    {
        $cacheInfo = $this->cacheService->getUserCacheInfo($user->id, 'audit_most_active_customers', $limit);

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            [...$cacheInfo['tags'], 'audit:statistics'],
            self::CACHE_TTL,
            fn() => $this->repository->getMostActiveCustomers($user, $limit)
        );
    }

    /**
     * Log custom activity for a customer
     *
     * @param User $user
     * @param Customer $customer
     * @param string $event
     * @param string $description
     * @param array<string, mixed> $properties
     * @return Activity
     */
    public function logCustomerActivity(
        User $user,
        Customer $customer,
        string $event,
        string $description,
        array $properties = []
    ): Activity {
        $activity = $this->repository->logCustomerActivity($user, $customer, $event, $description, $properties);
        
        // Clear audit-related cache when new activity is logged
        $this->clearAuditCacheForUser($user);
        $this->clearAuditCacheForCustomer($user, $customer);
        
        return $activity;
    }

    /**
     * Get activities for multiple customers
     *
     * @param User $user
     * @param array<int> $customerIds
     * @return Collection<int, Activity>
     */
    public function getActivitiesForCustomers(User $user, array $customerIds): Collection
    {
        // Cache key based on sorted customer IDs to ensure consistency
        sort($customerIds);
        $customerIdHash = md5(implode(',', $customerIds));
        
        $cacheInfo = $this->cacheService->getUserCacheInfo($user->id, 'audit_for_customers', $customerIdHash);

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            [...$cacheInfo['tags'], 'audit:multiple_customers'],
            self::CACHE_TTL,
            fn() => $this->repository->getActivitiesForCustomers($user, $customerIds)
        );
    }

    /**
     * Clear audit-related cache for a user
     *
     * @param User $user
     * @return void
     */
    private function clearAuditCacheForUser(User $user): void
    {
        // Clear audit-specific tags for the user
        $this->cacheService->flushByTags([
            "user:{$user->id}",
            'audit:recent',
            'audit:count',
            'audit:event',
            'audit:statistics',
            'audit:multiple_customers',
        ]);
    }

    /**
     * Clear audit cache for specific customer
     *
     * @param User $user
     * @param Customer $customer
     * @return void
     */
    private function clearAuditCacheForCustomer(User $user, Customer $customer): void
    {
        // Clear customer-specific audit cache
        $this->cacheService->flushByTags([
            "user:{$user->id}",
            "customer:{$customer->id}",
            'audit:recent',
            'audit:count',
            'audit:statistics',
        ]);
    }
}