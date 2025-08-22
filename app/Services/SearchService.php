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

    /**
     * Get comprehensive search suggestions from multiple sources
     *
     * @param User $user
     * @param string $query
     * @param int $limit
     * @return array<array{text: string, value: string, type: string, subtitle?: string}>
     */
    public function getComprehensiveSuggestions(User $user, string $query, int $limit = 8): array
    {
        if (strlen(trim($query)) < 2) {
            return [];
        }

        $cacheInfo = $this->cacheService->getUserCacheInfo($user->id, 'suggestions', $query, $limit);
        
        return $this->cacheService->rememberWithTags(
            $cacheInfo['key'],
            $cacheInfo['tags'],
            300, // 5 minutes cache
            fn() => $this->buildSuggestions($user, $query, $limit)
        );
    }

    /**
     * Build suggestions from multiple sources
     *
     * @param User $user
     * @param string $query
     * @param int $limit
     * @return array<array{text: string, value: string, type: string, subtitle?: string}>
     */
    private function buildSuggestions(User $user, string $query, int $limit): array
    {
        $suggestions = [];
        $sanitizedQuery = $this->sanitizeSearchQuery($query);

        // 1. Customer name suggestions
        $customerSuggestions = $this->getCustomerNameSuggestions($user, $sanitizedQuery, 5);
        $suggestions = array_merge($suggestions, $customerSuggestions);

        // 2. Email suggestions (if query contains @ or looks like email)
        if (strpos($sanitizedQuery, '@') !== false || filter_var($sanitizedQuery, FILTER_VALIDATE_EMAIL)) {
            $emailSuggestions = $this->getEmailSuggestions($user, $sanitizedQuery, 3);
            $suggestions = array_merge($suggestions, $emailSuggestions);
        }

        // 3. Organization suggestions
        $organizationSuggestions = $this->getOrganizationSuggestions($user, $sanitizedQuery, 3);
        $suggestions = array_merge($suggestions, $organizationSuggestions);

        // 4. Phone number suggestions (if query looks like a phone number)
        if (preg_match('/[\d\-\+\(\)\s]+/', $sanitizedQuery) && strlen(preg_replace('/\D/', '', $sanitizedQuery)) >= 3) {
            $phoneSuggestions = $this->getPhoneSuggestions($user, $sanitizedQuery, 2);
            $suggestions = array_merge($suggestions, $phoneSuggestions);
        }

        // Remove duplicates, prioritize, and limit results
        return $this->processSuggestions($suggestions, $limit);
    }

    /**
     * Get customer name suggestions
     *
     * @param User $user
     * @param string $query
     * @param int $limit
     * @return array<array{text: string, value: string, type: string, subtitle: string}>
     */
    private function getCustomerNameSuggestions(User $user, string $query, int $limit): array
    {
        $customers = $this->searchCustomersByText($user, $query, [], $limit);
        $suggestions = [];

        foreach ($customers as $customer) {
            $suggestions[] = [
                'text' => $customer->full_name,
                'value' => $customer->full_name,
                'type' => 'customer',
                'subtitle' => $customer->email,
                'priority' => 100
            ];
        }

        return $suggestions;
    }

    /**
     * Get email suggestions
     *
     * @param User $user
     * @param string $query
     * @param int $limit
     * @return array<array{text: string, value: string, type: string, subtitle: string}>
     */
    private function getEmailSuggestions(User $user, string $query, int $limit): array
    {
        $customers = $this->customerRepository->getAllForUser($user, [
            'search' => $query,
            'limit' => $limit * 2
        ]);

        $suggestions = [];
        foreach ($customers as $customer) {
            if (stripos($customer->email, $query) !== false) {
                $suggestions[] = [
                    'text' => $customer->email,
                    'value' => $customer->email,
                    'type' => 'email',
                    'subtitle' => $customer->full_name,
                    'priority' => 90
                ];
            }
        }

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * Get organization suggestions
     *
     * @param User $user
     * @param string $query
     * @param int $limit
     * @return array<array{text: string, value: string, type: string, subtitle: string}>
     */
    private function getOrganizationSuggestions(User $user, string $query, int $limit): array
    {
        $customers = $this->customerRepository->getAllForUser($user, [
            'search' => $query,
            'limit' => $limit * 3
        ]);

        $organizations = $customers
            ->whereNotNull('organization')
            ->filter(fn($customer) => stripos($customer->organization, $query) !== false)
            ->pluck('organization')
            ->unique()
            ->take($limit);

        $suggestions = [];
        foreach ($organizations as $organization) {
            $suggestions[] = [
                'text' => $organization,
                'value' => $organization,
                'type' => 'organization',
                'subtitle' => 'Organization',
                'priority' => 80
            ];
        }

        return $suggestions;
    }

    /**
     * Get phone number suggestions
     *
     * @param User $user
     * @param string $query
     * @param int $limit
     * @return array<array{text: string, value: string, type: string, subtitle: string}>
     */
    private function getPhoneSuggestions(User $user, string $query, int $limit): array
    {
        $customers = $this->customerRepository->getAllForUser($user, [
            'search' => $query,
            'limit' => $limit * 2
        ]);

        $suggestions = [];
        foreach ($customers as $customer) {
            if ($customer->phone && stripos($customer->phone, preg_replace('/\D/', '', $query)) !== false) {
                $suggestions[] = [
                    'text' => $customer->phone,
                    'value' => $customer->phone,
                    'type' => 'phone',
                    'subtitle' => $customer->full_name,
                    'priority' => 70
                ];
            }
        }

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * Process and prioritize suggestions
     *
     * @param array<array{text: string, value: string, type: string, subtitle?: string, priority?: int}> $suggestions
     * @param int $limit
     * @return array<array{text: string, value: string, type: string, subtitle?: string}>
     */
    private function processSuggestions(array $suggestions, int $limit): array
    {
        // Remove duplicates based on value
        $uniqueSuggestions = collect($suggestions)
            ->unique('value')
            ->sortByDesc('priority')
            ->take($limit)
            ->map(function ($suggestion) {
                // Remove priority from final output
                unset($suggestion['priority']);
                return $suggestion;
            })
            ->values()
            ->toArray();

        return $uniqueSuggestions;
    }
}