<?php

namespace App\Contracts\Repositories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Interface for Audit repository operations with user scoping
 */
interface AuditRepositoryInterface
{
    /**
     * Get audit trail for a specific customer
     *
     * @param User $user
     * @param Customer $customer
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getCustomerAuditTrail(User $user, Customer $customer, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all audit activities for a user's customers
     *
     * @param User $user
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUserAuditTrail(User $user, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get recent audit activities for a user
     *
     * @param User $user
     * @param int $limit
     * @return Collection<int, Activity>
     */
    public function getRecentUserActivities(User $user, int $limit = 10): Collection;

    /**
     * Get audit activities by date range for a user
     *
     * @param User $user
     * @param \DateTimeInterface $fromDate
     * @param \DateTimeInterface $toDate
     * @return Collection<int, Activity>
     */
    public function getActivitiesByDateRange(User $user, \DateTimeInterface $fromDate, \DateTimeInterface $toDate): Collection;

    /**
     * Get audit activities by event type for a user
     *
     * @param User $user
     * @param string $event
     * @return Collection<int, Activity>
     */
    public function getActivitiesByEvent(User $user, string $event): Collection;

    /**
     * Get audit activity count for a user
     *
     * @param User $user
     * @return int
     */
    public function getActivityCountForUser(User $user): int;

    /**
     * Get audit activity count by event type for a user
     *
     * @param User $user
     * @return array<string, int>
     */
    public function getActivityCountsByEvent(User $user): array;

    /**
     * Find specific audit activity for a user
     *
     * @param User $user
     * @param int $activityId
     * @return Activity|null
     */
    public function findActivityForUser(User $user, int $activityId): ?Activity;

    /**
     * Get most active customers by audit count
     *
     * @param User $user
     * @param int $limit
     * @return Collection<int, array>
     */
    public function getMostActiveCustomers(User $user, int $limit = 5): Collection;

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
    ): Activity;

    /**
     * Get activities for multiple customers
     *
     * @param User $user
     * @param array<int> $customerIds
     * @return Collection<int, Activity>
     */
    public function getActivitiesForCustomers(User $user, array $customerIds): Collection;
}