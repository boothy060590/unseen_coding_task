<?php

namespace App\Services;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Events\Customer\CustomerCreated;
use App\Events\Customer\CustomerDeleted;
use App\Events\Customer\CustomerUpdated;
use App\Models\Customer;
use App\Models\User;
use App\Services\CacheService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Service for Customer business logic and operations
 */
class CustomerService
{
    /**
     * Constructor
     *
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private CacheService $cacheService
    ) {}

    /**
     * Get dashboard data for a user
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getDashboardData(User $user, array $filters = []): array
    {
        $customers = $this->customerRepository->getPaginatedForUser($user, $filters);
        $totalCustomers = $this->customerRepository->getCountForUser($user);
        $recentCustomers = $this->customerRepository->getRecentForUser($user, 5);

        return [
            'customers' => $customers,
            'total_customers' => $totalCustomers,
            'recent_customers' => $recentCustomers,
            'filters' => $filters,
        ];
    }

    /**
     * Create a new customer (validation handled by FormRequest)
     *
     * @param User $user
     * @param array<string, mixed> $data
     * @return Customer
     * @throws ValidationException
     */
    public function createCustomer(User $user, array $data): Customer
    {
        // Check for duplicate email within user's scope (business logic)
        if (isset($data['email']) && $this->emailExistsForUser($user, $data['email'])) {
            throw ValidationException::withMessages([
                'email' => ['A customer with this email already exists in your account.'],
            ]);
        }

        // Clean and prepare data
        $cleanData = $this->prepareCustomerData($data);

        $customer = $this->customerRepository->createForUser($user, $cleanData);

        // Ensure cache is cleared for immediate UI updates
        $this->cacheService->clearUserCache($user->id);

        // Dispatch event for auditing and other side effects
        CustomerCreated::dispatch($customer, $user, ['source' => 'service']);

        return $customer;
    }

    /**
     * Update an existing customer with validation
     *
     * @param User $user
     * @param Customer $customer
     * @param array<string, mixed> $data
     * @return Customer
     * @throws ValidationException
     */
    public function updateCustomer(User $user, Customer $customer, array $data): Customer
    {

        // Check for duplicate email within user's scope (excluding current customer)
        if (isset($data['email']) &&
            $data['email'] !== $customer->email &&
            $this->emailExistsForUser($user, $data['email'], $customer->id)
        ) {
            throw ValidationException::withMessages([
                'email' => ['A customer with this email already exists in your account.'],
            ]);
        }

        // Store original data for audit purposes
        $originalData = $customer->toArray();

        // Clean and prepare data
        $cleanData = $this->prepareCustomerData($data);

        $updatedCustomer = $this->customerRepository->updateForUser($user, $customer, $cleanData);

        // Ensure cache is cleared for immediate UI updates
        $this->cacheService->clearUserCache($user->id);

        // Dispatch event for auditing and other side effects
        CustomerUpdated::dispatch($updatedCustomer, $user, $originalData, ['source' => 'service']);

        return $updatedCustomer;
    }

    /**
     * Delete a customer with business logic checks
     *
     * @param User $user
     * @param Customer $customer
     * @return bool
     */
    public function deleteCustomer(User $user, Customer $customer): bool
    {
        // Add any business logic checks here (e.g., prevent deletion if customer has orders)
        // For now, we'll allow deletion

        // Dispatch event before deletion (while customer data is still available)
        CustomerDeleted::dispatch($customer, $user, ['source' => 'service']);

        $result = $this->customerRepository->deleteForUser($user, $customer);

        // Ensure cache is cleared for immediate UI updates
        $this->cacheService->clearUserCache($user->id);

        return $result;
    }

    /**
     * Search customers with enhanced logic
     *
     * @param User $user
     * @param string $query
     * @param int $limit
     * @return Collection<int, Customer>
     */
    public function searchCustomers(User $user, string $query, int $limit = 50): Collection
    {
        // Sanitize search query
        $cleanQuery = trim($query);

        if (strlen($cleanQuery) < 2) {
            return new Collection();
        }

        return $this->customerRepository->getAllForUser($user, [
            'search' => $cleanQuery,
            'limit' => $limit
        ]);
    }

    /**
     * Get customer statistics for a user
     *
     * @param User $user
     * @return array<string, mixed>
     */
    public function getCustomerStatistics(User $user): array
    {
        $totalCustomers = $this->customerRepository->getCountForUser($user);
        $recentCustomers = $this->customerRepository->getRecentForUser($user, 30);

        // Calculate statistics
        $monthlyGrowth = $recentCustomers->where('created_at', '>=', now()->subMonth())->count();
        $weeklyGrowth = $recentCustomers->where('created_at', '>=', now()->subWeek())->count();

        // Top organizations
        $organizationCount = $this->customerRepository->getAllForUser($user)
            ->whereNotNull('organization')
            ->unique('organization')
            ->count();

        return [
            'total_customers' => $totalCustomers,
            'monthly_growth' => $monthlyGrowth,
            'weekly_growth' => $weeklyGrowth,
            'organisation_count' => $organizationCount,
        ];
    }

    

    /**
     * Get customer by slug for user
     *
     * @param User $user
     * @param string $slug
     * @return Customer|null
     */
    public function getCustomerBySlug(User $user, string $slug): ?Customer
    {
        return $this->customerRepository->findBySlugForUser($user, $slug);
    }

    /**
     * Get customers by organization for user
     *
     * @param User $user
     * @param string $organization
     * @return Collection<int, Customer>
     */
    public function getCustomersByOrganization(User $user, string $organization): Collection
    {
        return $this->customerRepository->getAllForUser($user, ['organization' => $organization]);
    }


    /**
     * Check if email exists for user
     *
     * @param User $user
     * @param string $email
     * @param int|null $excludeId
     * @return bool
     */
    private function emailExistsForUser(User $user, string $email, ?int $excludeId = null): bool
    {
        $customers = $this->customerRepository->getAllForUser($user);

        return $customers
            ->when($excludeId, fn($collection) => $collection->where('id', '!=', $excludeId))
            ->contains('email', $email);
    }

    /**
     * Prepare and clean customer data
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function prepareCustomerData(array $data): array
    {
        // Clean and format data
        $cleanData = [];

        if (isset($data['first_name'])) {
            $cleanData['first_name'] = trim($data['first_name']);
        }

        if (isset($data['last_name'])) {
            $cleanData['last_name'] = trim($data['last_name']);
        }

        if (isset($data['email'])) {
            $cleanData['email'] = strtolower(trim($data['email']));
        }

        if (isset($data['phone'])) {
            $cleanData['phone'] = $data['phone'] ? trim($data['phone']) : null;
        }

        if (isset($data['organization'])) {
            $cleanData['organization'] = $data['organization'] ? trim($data['organization']) : null;
        }

        if (isset($data['job_title'])) {
            $cleanData['job_title'] = $data['job_title'] ? trim($data['job_title']) : null;
        }

        if (isset($data['birthdate'])) {
            $cleanData['birthdate'] = $data['birthdate'] ?: null;
        }

        if (isset($data['notes'])) {
            $cleanData['notes'] = $data['notes'] ? trim($data['notes']) : null;
        }

        return $cleanData;
    }
}
