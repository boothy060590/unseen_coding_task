<?php

namespace App\Repositories\Decorators;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Models\User;
use App\Services\CacheService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cache decorator for CustomerRepository to improve performance
 */
class CachedCustomerRepository implements CustomerRepositoryInterface
{
    /**
     * Cache TTL in seconds (1 hour)
     */
    private const CACHE_TTL = 3600;

    /**
     * Constructor
     *
     * @param CustomerRepositoryInterface $repository
     * @param CacheService $cacheService
     */
    public function __construct(
        private CustomerRepositoryInterface $repository,
        private CacheService $cacheService
    ) {}

    /**
     * Get all customers for a specific user with optional filtering
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @return Collection<int, Customer>
     */
    public function getAllForUser(User $user, array $filters = []): Collection
    {
        // Don't cache filtered results as they can be highly variable
        if (!empty($filters)) {
            return $this->repository->getAllForUser($user, $filters);
        }

        $cacheInfo = $this->cacheService->getUserCacheInfo($user->id, 'customers_all');

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            self::CACHE_TTL,
            fn() => $this->repository->getAllForUser($user, $filters)
        );
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
        // Don't cache paginated results as they change frequently and have many variations
        return $this->repository->getPaginatedForUser($user, $filters, $perPage);
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
        $cacheInfo = $this->cacheService->getCustomerCacheInfo($user->id, $id, 'find');

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            self::CACHE_TTL,
            fn() => $this->repository->findForUser($user, $id)
        );
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
        $cacheInfo = $this->cacheService->getUserCacheInfo($user->id, 'customer_by_slug', $slug);

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            self::CACHE_TTL,
            fn() => $this->repository->findBySlugForUser($user, $slug)
        );
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
        $customer = $this->repository->createForUser($user, $data);
        
        // Clear user cache using improved invalidation
        $this->cacheService->clearUserCache($user->id);
        
        return $customer;
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
        $updatedCustomer = $this->repository->updateForUser($user, $customer, $data);
        
        // Clear specific customer cache using improved invalidation
        $this->cacheService->clearCustomerCache($user->id, $customer->id);
        
        return $updatedCustomer;
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
        $result = $this->repository->deleteForUser($user, $customer);
        
        if ($result) {
            // Clear specific customer cache using improved invalidation
            $this->cacheService->clearCustomerCache($user->id, $customer->id);
        }
        
        return $result;
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
        $cacheInfo = $this->cacheService->getUserCacheInfo($user->id, 'search', md5($query), $limit);

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            self::CACHE_TTL / 2, // Shorter TTL for search results
            fn() => $this->repository->searchForUser($user, $query, $limit)
        );
    }

    /**
     * Get customer count for a specific user
     *
     * @param User $user
     * @return int
     */
    public function getCountForUser(User $user): int
    {
        $cacheInfo = $this->cacheService->getUserCacheInfo($user->id, 'count');

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            self::CACHE_TTL,
            fn() => $this->repository->getCountForUser($user)
        );
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
        $cacheInfo = $this->cacheService->getUserCacheInfo($user->id, 'organization', md5($organization));

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            self::CACHE_TTL,
            fn() => $this->repository->getByOrganizationForUser($user, $organization)
        );
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
        $cacheInfo = $this->cacheService->getUserCacheInfo($user->id, 'recent', $limit);

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            self::CACHE_TTL / 4, // Shorter TTL for recent data
            fn() => $this->repository->getRecentForUser($user, $limit)
        );
    }
}