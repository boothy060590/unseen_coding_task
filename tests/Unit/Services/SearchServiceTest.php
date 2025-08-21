<?php

namespace Tests\Unit\Services;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Models\User;
use App\Services\CacheService;
use App\Services\SearchService;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(SearchService::class)]
class SearchServiceTest extends TestCase
{
    private SearchService $service;
    private CustomerRepositoryInterface&MockObject $mockCustomerRepository;
    private CacheService&MockObject $mockCacheService;
    private ConfigRepository&MockObject $mockConfig;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockCustomerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->mockCacheService = $this->createMock(CacheService::class);
        $this->mockConfig = $this->createMock(ConfigRepository::class);

        $this->service = new SearchService(
            $this->mockCustomerRepository,
            $this->mockCacheService,
            $this->mockConfig
        );

        $this->user = new User(['first_name' => 'Test', 'last_name' => 'User']);
        $this->user->setAttribute('id', 1);
    }

    public function testSearchCustomers(): void
    {
        $filters = ['organization' => 'Acme'];
        $perPage = 20;
        $cacheKey = 'test_cache_key';
        $cacheInfo = ['key' => 'cached_search_key', 'tags' => ['user_1', 'customers']];
        $paginatedCustomers = $this->createMock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class);

        $this->mockCacheService->expects($this->once())
            ->method('getUserCacheInfo')
            ->with($this->user->id, 'search_customers', $this->isType('string'))
            ->willReturn($cacheInfo);

        $this->mockConfig->expects($this->once())
            ->method('get')
            ->with('cache.ttl.search', 300)
            ->willReturn(300);

        $this->mockCacheService->expects($this->once())
            ->method('rememberWithTags')
            ->with(
                $cacheInfo['key'],
                $cacheInfo['tags'],
                300,
                $this->isType('callable')
            )
            ->willReturnCallback(function ($key, $tags, $ttl, $callback) use ($paginatedCustomers) {
                return $callback();
            });

        $this->mockCustomerRepository->expects($this->once())
            ->method('getPaginatedForUser')
            ->with($this->user, $filters, $perPage)
            ->willReturn($paginatedCustomers);

        $result = $this->service->searchCustomers($this->user, $filters, $perPage);

        $this->assertSame($paginatedCustomers, $result);
    }

    public function testFilterByOrganization(): void
    {
        $organization = 'Acme Corp';
        $additionalFilters = ['status' => 'active'];
        $expectedFilters = array_merge($additionalFilters, ['organization' => $organization]);
        $cacheInfo = ['key' => 'cached_filter_key', 'tags' => ['user_1', 'customers']];
        $customers = new Collection([new Customer(['organization' => $organization, 'user_id' => 1])]);

        $this->mockCacheService->expects($this->once())
            ->method('getUserCacheInfo')
            ->with($this->user->id, 'filter_organization', $this->isType('string'))
            ->willReturn($cacheInfo);

        $this->mockConfig->expects($this->once())
            ->method('get')
            ->with('cache.ttl.search', 300)
            ->willReturn(300);

        $this->mockCacheService->expects($this->once())
            ->method('rememberWithTags')
            ->willReturnCallback(function ($key, $tags, $ttl, $callback) {
                return $callback();
            });

        $this->mockCustomerRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, $expectedFilters)
            ->willReturn($customers);

        $result = $this->service->filterByOrganization($this->user, $organization, $additionalFilters);

        $this->assertSame($customers, $result);
    }

    public function testSearchCustomersByText(): void
    {
        $query = 'John Doe';
        $additionalFilters = ['status' => 'active'];
        $limit = 25;
        $cacheInfo = ['key' => 'cached_text_key', 'tags' => ['user_1', 'customers']];
        $customers = new Collection([new Customer(['first_name' => 'John', 'last_name' => 'Doe', 'user_id' => 1])]);

        $this->mockCacheService->expects($this->once())
            ->method('getUserCacheInfo')
            ->with($this->user->id, 'search_text', $this->isType('string'))
            ->willReturn($cacheInfo);

        $this->mockConfig->expects($this->once())
            ->method('get')
            ->with('cache.ttl.search', 300)
            ->willReturn(300);

        $this->mockCacheService->expects($this->once())
            ->method('rememberWithTags')
            ->willReturnCallback(function ($key, $tags, $ttl, $callback) {
                return $callback();
            });

        $this->mockCustomerRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, $this->callback(function ($filters) use ($additionalFilters, $limit) {
                return $filters['search'] === 'John Doe' &&
                       $filters['limit'] === $limit &&
                       isset($filters['status']) &&
                       $filters['status'] === 'active';
            }))
            ->willReturn($customers);

        $result = $this->service->searchCustomersByText($this->user, $query, $additionalFilters, $limit);

        $this->assertSame($customers, $result);
    }

    public function testSearchCustomersByTextWithShortQueryReturnsEmpty(): void
    {
        $query = 'J'; // Too short

        $result = $this->service->searchCustomersByText($this->user, $query);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function testGetCustomersInDateRange(): void
    {
        $startDate = now()->subDays(30);
        $endDate = now();
        $additionalFilters = ['status' => 'active'];
        $expectedFilters = array_merge($additionalFilters, [
            'date_from' => $startDate,
            'date_to' => $endDate
        ]);
        $cacheInfo = ['key' => 'cached_date_key', 'tags' => ['user_1', 'customers']];
        $customers = new Collection([new Customer(['id' => 1, 'user_id' => 1])]);

        $this->mockCacheService->expects($this->once())
            ->method('getUserCacheInfo')
            ->with($this->user->id, 'date_range', $this->isType('string'))
            ->willReturn($cacheInfo);

        $this->mockConfig->expects($this->once())
            ->method('get')
            ->with('cache.ttl.search', 600)
            ->willReturn(600);

        $this->mockCacheService->expects($this->once())
            ->method('rememberWithTags')
            ->willReturnCallback(function ($key, $tags, $ttl, $callback) {
                return $callback();
            });

        $this->mockCustomerRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, $expectedFilters)
            ->willReturn($customers);

        $result = $this->service->getCustomersInDateRange($this->user, $startDate, $endDate, $additionalFilters);

        $this->assertSame($customers, $result);
    }

    public function testGetSearchSuggestions(): void
    {
        $field = 'organization';
        $query = 'Acme';
        $limit = 10;
        $cacheInfo = ['key' => 'cached_suggestions_key', 'tags' => ['user_1', 'suggestions']];
        $customers = new Collection([
            new Customer(['organization' => 'Acme Corp', 'user_id' => 1]),
            new Customer(['organization' => 'Acme Industries', 'user_id' => 1]),
            new Customer(['organization' => 'Beta Corp', 'user_id' => 1])
        ]);
        $expectedSuggestions = ['Acme Corp', 'Acme Industries'];

        $this->mockCacheService->expects($this->once())
            ->method('getUserCacheInfo')
            ->with($this->user->id, 'suggestions', $this->isType('string'))
            ->willReturn($cacheInfo);

        $this->mockConfig->expects($this->once())
            ->method('get')
            ->with('cache.ttl.suggestions', 1800)
            ->willReturn(1800);

        $this->mockCacheService->expects($this->once())
            ->method('rememberWithTags')
            ->willReturnCallback(function ($key, $tags, $ttl, $callback) {
                return $callback();
            });

        $this->mockCustomerRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, $this->callback(function ($filters) use ($query) {
                return $filters['search'] === $query &&
                       $filters['limit'] === 50; // 10 * 5
            }))
            ->willReturn($customers);

        $result = $this->service->getSearchSuggestions($this->user, $field, $query, $limit);

        $this->assertSame($expectedSuggestions, $result);
    }

    public function testGetSearchSuggestionsWithShortQueryReturnsEmpty(): void
    {
        $result = $this->service->getSearchSuggestions($this->user, 'organization', '');

        $this->assertSame([], $result);
    }

    public function testGetSearchStatistics(): void
    {
        $filters = ['organization' => 'Acme'];
        $cacheInfo = ['key' => 'cached_stats_key', 'tags' => ['user_1', 'stats']];
        $customerOne =  new Customer([
            'organization' => 'Acme Corp',
            'job_title' => 'Developer',
            'email' => 'john@acme.com',
            'user_id' => 1
        ]);
        $customerOne->setAttribute('id', 1);
        $customerOne->setAttribute('created_at', now()->subDays(5));

        $customerTwo = new Customer([
            'organization' => 'Beta Inc',
            'job_title' => 'Manager',
            'email' => 'jane@beta.com',
            'user_id' => 1
        ]);

        $customerTwo->setAttribute('id', 2);
        $customerTwo->setAttribute('created_at', now()->subDays(2));

        $customers = new Collection([$customerOne, $customerTwo]);


        $expectedStats = [
            'total_results' => 2,
            'organizations' => 2,
            'job_titles' => 2,
            'date_range' => [
                'earliest' => now()->subDays(5)->toDateString(),
                'latest' => now()->subDays(2)->toDateString(),
            ],
            'email_domains' => 2,
        ];

        $this->mockCacheService->expects($this->once())
            ->method('getUserCacheInfo')
            ->with($this->user->id, 'search_stats', $this->isType('string'))
            ->willReturn($cacheInfo);

        $this->mockConfig->expects($this->once())
            ->method('get')
            ->with('cache.ttl.stats', 900)
            ->willReturn(900);

        $this->mockCacheService->expects($this->once())
            ->method('rememberWithTags')
            ->willReturnCallback(function ($key, $tags, $ttl, $callback) {
                return $callback();
            });

        $this->mockCustomerRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, $filters)
            ->willReturn($customers);

        $result = $this->service->getSearchStatistics($this->user, $filters);

        $this->assertSame($expectedStats, $result);
    }

    public function testGenerateSearchCacheKey(): void
    {
        $filters = ['organization' => 'Acme'];
        $perPage = 15;

        // We'll test this indirectly by verifying cache methods are called
        $cacheInfo = ['key' => 'test_key', 'tags' => ['user_1']];

        $this->mockCacheService->expects($this->once())
            ->method('getUserCacheInfo')
            ->with($this->user->id, 'search_customers', $this->isType('string'))
            ->willReturn($cacheInfo);

        $this->mockConfig->method('get')->willReturn(300);
        $this->mockCacheService->method('rememberWithTags')->willReturnCallback(function ($key, $tags, $ttl, $callback) {
            return $callback();
        });
        $this->mockCustomerRepository->method('getPaginatedForUser')->willReturn(
            $this->createMock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class)
        );

        $this->service->searchCustomers($this->user, $filters, $perPage);
    }

    public function testSanitizeSearchQuery(): void
    {
        // We can test this indirectly by verifying dangerous characters are handled
        $maliciousQuery = '<script>alert("xss")</script> test   query';

        $this->mockCacheService->method('getUserCacheInfo')->willReturn(['key' => 'test', 'tags' => []]);
        $this->mockConfig->method('get')->willReturn(300);
        $this->mockCacheService->method('rememberWithTags')->willReturnCallback(function ($key, $tags, $ttl, $callback) {
            return $callback();
        });


        $this->mockCustomerRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, ['search' => 'scriptalert(xss)/script test query', 'limit' => 50])
            ->willReturn(new Collection());

        $this->service->searchCustomersByText($this->user, $maliciousQuery);
    }

    public function testPerformCustomerSearchUsesRepositoryFiltering(): void
    {
        $filters = ['organization' => 'Acme', 'status' => 'active'];
        $perPage = 25;
        $paginatedResult = $this->createMock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class);

        $this->mockCacheService->method('getUserCacheInfo')->willReturn(['key' => 'test', 'tags' => []]);
        $this->mockConfig->method('get')->willReturn(300);
        $this->mockCacheService->method('rememberWithTags')->willReturnCallback(function ($key, $tags, $ttl, $callback) {
            return $callback();
        });

        $this->mockCustomerRepository->expects($this->once())
            ->method('getPaginatedForUser')
            ->with($this->user, $filters, $perPage)
            ->willReturn($paginatedResult);

        $result = $this->service->searchCustomers($this->user, $filters, $perPage);

        $this->assertSame($paginatedResult, $result);
    }

    public function testPerformTextSearchUsesRepositoryFiltering(): void
    {
        $query = 'John Doe';
        $additionalFilters = ['status' => 'active'];
        $limit = 50;
        $customers = new Collection([new Customer(['user_id' => 1])]);

        $this->mockCacheService->method('getUserCacheInfo')->willReturn(['key' => 'test', 'tags' => []]);
        $this->mockConfig->method('get')->willReturn(300);
        $this->mockCacheService->method('rememberWithTags')->willReturnCallback(function ($key, $tags, $ttl, $callback) {
            return $callback();
        });

        $this->mockCustomerRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, $this->callback(function ($filters) use ($query, $limit, $additionalFilters) {
                return $filters['search'] === $query &&
                       $filters['limit'] === $limit &&
                       $filters['status'] === 'active';
            }))
            ->willReturn($customers);

        $result = $this->service->searchCustomersByText($this->user, $query, $additionalFilters, $limit);

        $this->assertSame($customers, $result);
    }

    public function testCalculateSearchStatisticsWithEmptyResults(): void
    {
        $filters = ['organization' => 'NonExistent'];
        $emptyCollection = new Collection();

        $this->mockCacheService->method('getUserCacheInfo')->willReturn(['key' => 'test', 'tags' => []]);
        $this->mockConfig->method('get')->willReturn(300);
        $this->mockCacheService->method('rememberWithTags')->willReturnCallback(function ($key, $tags, $ttl, $callback) {
            return $callback();
        });

        $this->mockCustomerRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, $filters)
            ->willReturn($emptyCollection);

        $result = $this->service->getSearchStatistics($this->user, $filters);

        $this->assertSame(0, $result['total_results']);
        $this->assertSame(0, $result['organizations']);
        $this->assertSame(0, $result['job_titles']);
        $this->assertSame(0, $result['email_domains']);
        $this->assertNull($result['date_range']['earliest']);
        $this->assertNull($result['date_range']['latest']);
    }
}
