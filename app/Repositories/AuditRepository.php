<?php

namespace App\Repositories;

use App\Contracts\Repositories\AuditRepositoryInterface;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Activity;
use Illuminate\Support\Collection as SupportCollection;

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
     * Get all activities for a user with optional filtering
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @return Collection<int, Activity>
     */
    public function getAllForUser(User $user, array $filters = []): Collection
    {
        $query = $this->buildUserActivityQuery($user);

        return $this->applyFilters($query, $filters)->get();
    }

    /**
     * Get paginated activities for a user with optional filtering
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedForUser(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->buildUserActivityQuery($user);

        return $this->applyFilters($query, $filters)->paginate($perPage);
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
        return $this->getPaginatedForUser($user, [], $perPage);
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
        return $this->getAllForUser($user, ['limit' => $limit]);
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
        return $this->getAllForUser($user, [
            'date_from' => $fromDate,
            'date_to' => $toDate,
            'sort_by' => 'created_at',
            'sort_direction' => 'asc'
        ]);
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
        return $this->getAllForUser($user, ['event' => $event]);
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
     * @return SupportCollection<int, array>
     */
    public function getMostActiveCustomers(User $user, int $limit = 5): SupportCollection
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
        return $this->getAllForUser($user, ['customer_ids' => $customerIds]);
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

    /**
     * Apply filters to the activity query
     *
     * @param Builder $query
     * @param array<string, mixed> $filters
     * @return Builder
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        // Always eager load relationships
        $query->with(['causer', 'subject']);

        if (isset($filters['event']) && $filters['event']) {
            $query->where('event', $filters['event']);
        }

        if (isset($filters['customer_ids']) && is_array($filters['customer_ids'])) {
            $query->whereIn('subject_id', $filters['customer_ids']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['limit']) && is_numeric($filters['limit'])) {
            $query->limit((int) $filters['limit']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        $validSorts = ['created_at', 'event', 'id'];

        if (in_array($sortBy, $validSorts, true)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->latest();
        }

        return $query;
    }
}
