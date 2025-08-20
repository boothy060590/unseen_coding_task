<?php

namespace App\Repositories;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

/**
 * Repository for Customer operations with user scoping and security
 */
class CustomerRepository implements CustomerRepositoryInterface
{
    /**
     * Get all customers for a specific user with optional filtering
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @return Collection<int, Customer>
     */
    public function getAllForUser(User $user, array $filters = []): Collection
    {
        $query = $this->buildUserQuery($user);
        
        return $this->applyFilters($query, $filters)->get();
    }

    /**
     * Get paginated customers for a specific user with optional filtering
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedForUser(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->buildUserQuery($user);
        
        return $this->applyFilters($query, $filters)->paginate($perPage);
    }

    /**
     * Find a customer by ID for a specific user
     *
     * @param User $user
     * @param int $id
     * @return Customer|null
     */
    public function findForUser(User $user, int $id): ?Customer
    {
        return $this->buildUserQuery($user)->find($id);
    }

    /**
     * Find a customer by slug for a specific user
     *
     * @param User $user
     * @param string $slug
     * @return Customer|null
     */
    public function findBySlugForUser(User $user, string $slug): ?Customer
    {
        return $this->buildUserQuery($user)->where('slug', $slug)->first();
    }

    /**
     * Create a new customer for a specific user
     *
     * @param User $user
     * @param array<string, mixed> $data
     * @return Customer
     */
    public function createForUser(User $user, array $data): Customer
    {
        $data['user_id'] = $user->id;
        
        return Customer::create($data);
    }

    /**
     * Update a customer for a specific user
     *
     * @param User $user
     * @param Customer $customer
     * @param array<string, mixed> $data
     * @return Customer
     */
    public function updateForUser(User $user, Customer $customer, array $data): Customer
    {
        // Security check: ensure customer belongs to user
        if ($customer->user_id !== $user->id) {
            throw new \InvalidArgumentException('Customer does not belong to the specified user');
        }

        $customer->update($data);
        
        return $customer->fresh();
    }

    /**
     * Delete a customer for a specific user
     *
     * @param User $user
     * @param Customer $customer
     * @return bool
     */
    public function deleteForUser(User $user, Customer $customer): bool
    {
        // Security check: ensure customer belongs to user
        if ($customer->user_id !== $user->id) {
            throw new \InvalidArgumentException('Customer does not belong to the specified user');
        }

        return (bool) $customer->delete();
    }

    /**
     * Search customers for a specific user
     *
     * @param User $user
     * @param string $query
     * @param int $limit
     * @return Collection<int, Customer>
     */
    public function searchForUser(User $user, string $query, int $limit = 50): Collection
    {
        return $this->buildUserQuery($user)
            ->where(function (Builder $builder) use ($query) {
                $builder->whereFullText(['notes', 'name', 'organization'], $query)
                    ->orWhere('name', 'LIKE', "%{$query}%")
                    ->orWhere('email', 'LIKE', "%{$query}%")
                    ->orWhere('organization', 'LIKE', "%{$query}%")
                    ->orWhere('job_title', 'LIKE', "%{$query}%");
            })
            ->limit($limit)
            ->get();
    }

    /**
     * Get customer count for a specific user
     *
     * @param User $user
     * @return int
     */
    public function getCountForUser(User $user): int
    {
        return $this->buildUserQuery($user)->count();
    }

    /**
     * Get customers by organization for a specific user
     *
     * @param User $user
     * @param string $organization
     * @return Collection<int, Customer>
     */
    public function getByOrganizationForUser(User $user, string $organization): Collection
    {
        return $this->buildUserQuery($user)
            ->where('organization', $organization)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get recent customers for a specific user
     *
     * @param User $user
     * @param int $limit
     * @return Collection<int, Customer>
     */
    public function getRecentForUser(User $user, int $limit = 10): Collection
    {
        return $this->buildUserQuery($user)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Build base query for user-scoped operations
     *
     * @param User $user
     * @return Builder
     */
    private function buildUserQuery(User $user): Builder
    {
        return Customer::where('user_id', $user->id);
    }

    /**
     * Apply filters to the query
     *
     * @param Builder $query
     * @param array<string, mixed> $filters
     * @return Builder
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (isset($filters['search']) && $filters['search']) {
            $query->where(function (Builder $builder) use ($filters) {
                $searchTerm = $filters['search'];
                $builder->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('email', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('organization', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('job_title', 'LIKE', "%{$searchTerm}%");
            });
        }

        if (isset($filters['organization']) && $filters['organization']) {
            $query->where('organization', $filters['organization']);
        }

        if (isset($filters['job_title']) && $filters['job_title']) {
            $query->where('job_title', $filters['job_title']);
        }

        if (isset($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from']);
        }

        if (isset($filters['created_to'])) {
            $query->where('created_at', '<=', $filters['created_to']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'name';
        $sortDirection = $filters['sort_direction'] ?? 'asc';
        
        $validSorts = ['name', 'email', 'organization', 'job_title', 'created_at', 'updated_at'];
        if (in_array($sortBy, $validSorts, true)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('name', 'asc');
        }

        return $query;
    }
}