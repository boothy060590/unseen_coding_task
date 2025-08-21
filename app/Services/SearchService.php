<?php

namespace App\Services;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Carbon\Carbon;

/**
 * Service for advanced search and filtering across entities
 */
class SearchService
{
    /**
     * Constructor
     *
     * @param CustomerRepositoryInterface $customerRepository
     * @param CacheService $cacheService
     * @param ConfigRepository $config
     */
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private CacheService $cacheService,
        private ConfigRepository $config
    ) {}

    /**
     * Advanced search for customers with multiple filters
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchCustomers(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        // Generate cache key based on filters
        $cacheKey = $this->generateSearchCacheKey($user->id, $filters, $perPage);
        $cacheInfo = $this->cacheService->getUserCacheInfo($user->id, 'search_customers', $cacheKey);

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            $this->config->get('cache.ttl.search', 300),
            fn() => $this->performCustomerSearch($user, $filters, $perPage)
        );
    }

    /**
     * Filter customers by organization
     *
     * @param User $user
     * @param string $organization
     * @param array<string, mixed> $additionalFilters
     * @return Collection<int, Customer>
     */
    public function filterByOrganization(User $user, string $organization, array $additionalFilters = []): Collection
    {
        $filters = array_merge($additionalFilters, ['organization' => $organization]);
        
        $cacheKey = $this->generateSearchCacheKey($user->id, $filters);
        $cacheInfo = $this->cacheService->getUserCacheInfo($user->id, 'filter_organization', $cacheKey);

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            $this->config->get('cache.ttl.search', 300),
            fn() => $this->customerRepository->getAllForUser($user, $filters)
        );
    }

    /**
     * Search customers by text query across multiple fields
     *
     * @param User $user
     * @param string $query
     * @param array<string, mixed> $additionalFilters
     * @param int $limit
     * @return Collection<int, Customer>
     */
    public function searchCustomersByText(User $user, string $query, array $additionalFilters = [], int $limit = 50): Collection
    {
        $cleanQuery = $this->sanitizeSearchQuery($query);
        
        if (strlen($cleanQuery) < 2) {
            return new Collection();
        }

        $cacheKey = $this->generateSearchCacheKey($user->id, [
            'query' => $cleanQuery,
            'filters' => $additionalFilters,
            'limit' => $limit
        ]);
        
        $cacheInfo = $this->cacheService->getUserCacheInfo($user->id, 'search_text', $cacheKey);

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            $this->config->get('cache.ttl.search', 300),
            fn() => $this->performTextSearch($user, $cleanQuery, $additionalFilters, $limit)
        );
    }

    /**
     * Get customers created within a date range
     *
     * @param User $user
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param array<string, mixed> $additionalFilters
     * @return Collection<int, Customer>
     */
    public function getCustomersInDateRange(
        User $user, 
        Carbon $startDate, 
        Carbon $endDate, 
        array $additionalFilters = []
    ): Collection {
        $filters = array_merge($additionalFilters, [
            'date_from' => $startDate,
            'date_to' => $endDate
        ]);

        $cacheKey = $this->generateSearchCacheKey($user->id, $filters);
        $cacheInfo = $this->cacheService->getUserCacheInfo($user->id, 'date_range', $cacheKey);

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            $this->config->get('cache.ttl.search', 600),
            fn() => $this->customerRepository->getAllForUser($user, $filters)
        );
    }

    /**
     * Get search suggestions based on existing data
     *
     * @param User $user
     * @param string $field
     * @param string $query
     * @param int $limit
     * @return array<string>
     */
    public function getSearchSuggestions(User $user, string $field, string $query, int $limit = 10): array
    {
        $cleanQuery = $this->sanitizeSearchQuery($query);
        
        if (strlen($cleanQuery) < 1) {
            return [];
        }

        $cacheKey = "suggestions_{$field}_{$cleanQuery}_{$limit}";
        $cacheInfo = $this->cacheService->getUserCacheInfo($user->id, 'suggestions', $cacheKey);

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            $this->config->get('cache.ttl.suggestions', 1800),
            fn() => $this->generateFieldSuggestions($user, $field, $cleanQuery, $limit)
        );
    }

    /**
     * Get aggregated search statistics
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getSearchStatistics(User $user, array $filters = []): array
    {
        $cacheKey = $this->generateSearchCacheKey($user->id, $filters);
        $cacheInfo = $this->cacheService->getUserCacheInfo($user->id, 'search_stats', $cacheKey);

        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            $this->config->get('cache.ttl.stats', 900),
            fn() => $this->calculateSearchStatistics($user, $filters)
        );
    }

    /**
     * Perform the actual customer search with filters
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    private function performCustomerSearch(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        // Use repository filtering instead of manual collection filtering
        return $this->customerRepository->getPaginatedForUser($user, $filters, $perPage);
    }

    /**
     * Perform text search across customer fields
     *
     * @param User $user
     * @param string $query
     * @param array<string, mixed> $filters
     * @param int $limit
     * @return Collection<int, Customer>
     */
    private function performTextSearch(User $user, string $query, array $filters, int $limit): Collection
    {
        // Use repository filtering instead of manual filtering
        $searchFilters = array_merge($filters, [
            'search' => $query,
            'limit' => $limit
        ]);

        return $this->customerRepository->getAllForUser($user, $searchFilters);
    }


    /**
     * Generate field suggestions
     *
     * @param User $user
     * @param string $field
     * @param string $query
     * @param int $limit
     * @return array<string>
     */
    private function generateFieldSuggestions(User $user, string $field, string $query, int $limit): array
    {
        // Use repository filtering for suggestions - search by the field
        $customers = $this->customerRepository->getAllForUser($user, [
            'search' => $query,
            'limit' => $limit * 5 // Get more results to filter from
        ]);
        
        $suggestions = [];

        foreach ($customers as $customer) {
            $value = $customer->{$field} ?? null;
            if ($value && stripos($value, $query) !== false) {
                $suggestions[] = $value;
            }
        }

        return array_slice(array_unique($suggestions), 0, $limit);
    }

    /**
     * Calculate search statistics
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function calculateSearchStatistics(User $user, array $filters): array
    {
        $customers = $this->customerRepository->getAllForUser($user, $filters);

        return [
            'total_results' => $customers->count(),
            'organizations' => $customers->whereNotNull('organization')
                ->pluck('organization')
                ->unique()
                ->count(),
            'job_titles' => $customers->whereNotNull('job_title')
                ->pluck('job_title')
                ->unique()
                ->count(),
            'date_range' => [
                'earliest' => $customers->min('created_at')?->toDateString(),
                'latest' => $customers->max('created_at')?->toDateString(),
            ],
            'email_domains' => $customers->pluck('email')
                ->map(fn($email) => substr(strrchr($email, '@'), 1))
                ->unique()
                ->count(),
        ];
    }

    /**
     * Generate cache key for search operations
     *
     * @param int $userId
     * @param array<string, mixed> $filters
     * @param int|null $perPage
     * @return string
     */
    private function generateSearchCacheKey(int $userId, array $filters, ?int $perPage = null): string
    {
        $keyData = [
            'user' => $userId,
            'filters' => $filters,
        ];

        if ($perPage !== null) {
            $keyData['per_page'] = $perPage;
            $keyData['page'] = request()->get('page', 1);
        }

        return 'search_' . md5(json_encode($keyData));
    }

    /**
     * Sanitize search query
     *
     * @param string $query
     * @return string
     */
    private function sanitizeSearchQuery(string $query): string
    {
        // Remove excessive whitespace and trim
        $cleaned = preg_replace('/\s+/', ' ', trim($query));
        
        // Remove potentially dangerous characters but keep useful ones
        $cleaned = preg_replace('/[<>"\'\\\]/', '', $cleaned);
        
        return $cleaned ?? '';
    }
}