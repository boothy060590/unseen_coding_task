<?php

namespace App\Repositories\Decorators;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Models\User;
use App\Services\CacheService;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cache decorator for CustomerRepository to improve performance
 */
class CachedCustomerRepository implements CustomerRepositoryInterface
{
    /**
     * Constructor
     *
     * @param CustomerRepositoryInterface $repository
     * @param CacheService $cacheService
     * @param ConfigRepository $config
     */
    public function __construct(
        private CustomerRepositoryInterface $repository,
        private CacheService $cacheService,
        private ConfigRepository $config
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
            $this->config->get('cache.ttl.customers', 3600),
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
            $this->config->get('cache.ttl.customers', 3600),
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
            $this->config->get('cache.ttl.customers', 3600),
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
     * Get customer count for a specific user
     * Bypass cache for dashboard stats to ensure fresh data
     *
     * @param User $user
     * @return int
     */
    public function getCountForUser(User $user): int
    {
        // Bypass cache for dashboard stats - always return fresh data
        return $this->repository->getCountForUser($user);
    }

    /**
     * Get recent customers for a specific user
     * Bypass cache for dashboard stats to ensure fresh data
     *
     * @param User $user
     * @param int $limit
     * @return Collection<int, Customer>
     */
    public function getRecentForUser(User $user, int $limit = 10): Collection
    {
        // Bypass cache for dashboard stats - always return fresh data
        return $this->repository->getRecentForUser($user, $limit);
    }


}