<?php

namespace App\Repositories;

use App\Contracts\Repositories\AuditRepositoryInterface;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * Repository for Audit operations using Spatie Activity Log with user scoping
 */
class AuditRepository implements AuditRepositoryInterface
{
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
        // Ensure user owns the customer
        if ($customer->user_id !== $user->id) {
            throw new \InvalidArgumentException('Customer does not belong to the specified user');
        }

        return $this->buildCustomerActivityQuery($customer)
            ->where('causer_id', $user->id)
            ->with('causer')
            ->latest()
            ->paginate($perPage);
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
        return $this->buildUserActivityQuery($user)
            ->with(['causer', 'subject'])
            ->latest()
            ->paginate($perPage);
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
        return $this->buildUserActivityQuery($user)
            ->with(['causer', 'subject'])
            ->latest()
            ->limit($limit)
            ->get();
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
        return $this->buildUserActivityQuery($user)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->with(['causer', 'subject'])
            ->orderBy('created_at')
            ->get();
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
        return $this->buildUserActivityQuery($user)
            ->where('event', $event)
            ->with(['causer', 'subject'])
            ->latest()
            ->get();
    }

    /**
     * Get audit activity count for a user
     *
     * @param User $user
     * @return int
     */
    public function getActivityCountForUser(User $user): int
    {
        return $this->buildUserActivityQuery($user)->count();
    }

    /**
     * Get audit activity count by event type for a user
     *
     * @param User $user
     * @return array<string, int>
     */
    public function getActivityCountsByEvent(User $user): array
    {
        $activities = $this->buildUserActivityQuery($user)
            ->select('event')
            ->get()
            ->groupBy('event')
            ->map(fn($group) => $group->count());

        return $activities->toArray();
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
        return $this->buildUserActivityQuery($user)
            ->where('id', $activityId)
            ->with(['causer', 'subject'])
            ->first();
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
        $activities = $this->buildUserActivityQuery($user)
            ->with('subject')
            ->get()
            ->groupBy('subject_id')
            ->map(function ($group) {
                $customer = $group->first()->subject;
                return [
                    'customer' => $customer,
                    'activity_count' => $group->count(),
                ];
            })
            ->sortByDesc('activity_count')
            ->take($limit)
            ->values();

        return $activities;
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
        // Ensure user owns the customer
        if ($customer->user_id !== $user->id) {
            throw new \InvalidArgumentException('Customer does not belong to the specified user');
        }

        // Add IP and user agent if available
        if (request()) {
            $properties['ip_address'] = request()->ip();
            $properties['user_agent'] = request()->userAgent();
        }

        return activity()
            ->causedBy($user)
            ->performedOn($customer)
            ->event($event)
            ->withProperties($properties)
            ->log($description);
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
        return $this->buildUserActivityQuery($user)
            ->whereIn('subject_id', $customerIds)
            ->with(['causer', 'subject'])
            ->latest()
            ->get();
    }

    /**
     * Build base query for user-scoped activities
     *
     * @param User $user
     * @return Builder
     */
    private function buildUserActivityQuery(User $user): Builder
    {
        return Activity::where('causer_id', $user->id)
            ->where('subject_type', Customer::class);
    }

    /**
     * Build query for customer-specific activities
     *
     * @param Customer $customer
     * @return Builder
     */
    private function buildCustomerActivityQuery(Customer $customer): Builder
    {
        return Activity::where('subject_type', Customer::class)
            ->where('subject_id', $customer->id);
    }
}