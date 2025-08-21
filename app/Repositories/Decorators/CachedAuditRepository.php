<?php

namespace App\Repositories\Decorators;

use App\Contracts\Repositories\AuditRepositoryInterface;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\User;
use App\Services\CacheService;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Cache decorator for AuditRepository to improve performance
 */
class CachedAuditRepository implements AuditRepositoryInterface
{
    /**
     * Constructor
     *
     * @param AuditRepositoryInterface $repository
     * @param CacheService $cacheService
     * @param ConfigRepository $config
     */
    public function __construct(
        private AuditRepositoryInterface $repository,
        private CacheService $cacheService,
        private ConfigRepository $config
    ) {}

    /**
     * @param User $user
     * @param array $filters
     * @return Collection
     * @desc filters vary, so we use the base repo to query as this isn't something we should cache
     */
    public function getAllForUser(User $user, array $filters = []): Collection
    {
        return $this->repository->getAllForUser($user, $filters);
    }

    /**
     * @param User $user
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedForUser(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->getPaginatedForUser($user, $filters, $perPage);
    }

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
            $this->config->get('cache.ttl.audit_short', 300),
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
            $this->config->get('cache.ttl.audit', 900) * 4, // Longer TTL for historical data
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
            $this->config->get('cache.ttl.audit', 900),
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
            $this->config->get('cache.ttl.audit', 900),
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
            $this->config->get('cache.ttl.audit', 900),
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
            $this->config->get('cache.ttl.audit', 900),
            fn() => $this->repository->findActivityForUser($user, $activityId)
        );
    }

    /**
     * Get most active customers by audit count
     *
     * @param User $user
     * @param int $limit
     * @return SupportCollection<int, array>
     */
    public function getMostActiveCustomers(User $user, int $limit = 5): SupportCollection
    {
        $cacheInfo = $this->cacheService->getUserCacheInfo($user->id, 'audit_most_active_customers', $limit);

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            [...$cacheInfo['tags'], 'audit:statistics'],
            $this->config->get('cache.ttl.audit', 900),
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
     * @throws \ReflectionException
     */
    public function logCustomerActivity(
        User $user,
        Customer $customer,
        string $event,
        string $description,
        array $properties = []
    ): Activity
    {
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
            $this->config->get('cache.ttl.audit', 900),
            fn() => $this->repository->getActivitiesForCustomers($user, $customerIds)
        );
    }

    /**
     * Clear audit-related cache for a user
     *
     * @param User $user
     * @return void
     * @throws \ReflectionException
     */
    private function clearAuditCacheForUser(User $user): void
    {
        // Clear audit-specific tags for the user
        $this->cacheService->flushByKeys([
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
     * @throws \ReflectionException
     */
    private function clearAuditCacheForCustomer(User $user, Customer $customer): void
    {
        // Clear customer-specific audit cache
        $this->cacheService->flushByKeys([
            "user:{$user->id}",
            "customer:{$customer->id}",
            'audit:recent',
            'audit:count',
            'audit:statistics',
        ]);
    }
}
