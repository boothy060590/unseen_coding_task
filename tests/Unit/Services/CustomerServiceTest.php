<?php

namespace Tests\Unit\Services;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Events\Customer\CustomerCreated;
use App\Events\Customer\CustomerDeleted;
use App\Events\Customer\CustomerUpdated;
use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(CustomerService::class)]
class CustomerServiceTest extends TestCase
{
    private CustomerService $service;
    private CustomerRepositoryInterface&MockObject $mockRepository;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->service = new CustomerService($this->mockRepository);
        $this->user = new User(['first_name' => 'Test', 'last_name' => 'User', 'email' => 'test@example.com']);
        $this->user->setAttribute('id', 1);
        
        Event::fake();
    }

    public function testGetDashboardData(): void
    {
        $paginatedCustomers = $this->createMock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class);
        $recentCustomers = new Collection([new Customer(['id' => 1, 'first_name' => 'John', 'user_id' => 1])]);
        $filters = ['search' => 'test'];

        $this->mockRepository->expects($this->once())
            ->method('getPaginatedForUser')
            ->with($this->user, $filters)
            ->willReturn($paginatedCustomers);

        $this->mockRepository->expects($this->once())
            ->method('getCountForUser')
            ->with($this->user)
            ->willReturn(42);

        $this->mockRepository->expects($this->once())
            ->method('getRecentForUser')
            ->with($this->user, 5)
            ->willReturn($recentCustomers);

        $result = $this->service->getDashboardData($this->user, $filters);

        $this->assertSame($paginatedCustomers, $result['customers']);
        $this->assertSame(42, $result['total_customers']);
        $this->assertSame($recentCustomers, $result['recent_customers']);
        $this->assertSame($filters, $result['filters']);
    }

    public function testCreateCustomer(): void
    {
        $customerData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '123-456-7890',
        ];

        $expectedCustomer = new Customer($customerData + ['id' => 1, 'user_id' => 1]);

        // Mock getAllForUser to check for duplicate email
        $this->mockRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user)
            ->willReturn(new Collection()); // No existing customers

        $this->mockRepository->expects($this->once())
            ->method('createForUser')
            ->with($this->user, $this->callback(function ($data) {
                return $data['first_name'] === 'John' &&
                       $data['last_name'] === 'Doe' &&
                       $data['email'] === 'john@example.com' &&
                       $data['phone'] === '123-456-7890';
            }))
            ->willReturn($expectedCustomer);

        $result = $this->service->createCustomer($this->user, $customerData);

        $this->assertSame($expectedCustomer, $result);
        Event::assertDispatched(CustomerCreated::class);
    }

    public function testCreateCustomerWithDuplicateEmailThrowsException(): void
    {
        $customerData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ];

        $existingCustomer = new Customer(['id' => 2, 'email' => 'john@example.com', 'user_id' => 1]);

        $this->mockRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user)
            ->willReturn(new Collection([$existingCustomer]));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('A customer with this email already exists in your account.');

        $this->service->createCustomer($this->user, $customerData);
    }


    public function testUpdateCustomer(): void
    {
        $customer = new Customer([
            'id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'user_id' => 1
        ]);

        $updateData = [
            'first_name' => 'Jane',
            'email' => 'jane@example.com',
        ];

        $updatedCustomer = new Customer([
            'id' => 1,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'user_id' => 1
        ]);

        // Mock getAllForUser for email uniqueness check
        $this->mockRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user)
            ->willReturn(new Collection([$customer])); // Only existing customer

        $this->mockRepository->expects($this->once())
            ->method('updateForUser')
            ->with($this->user, $customer, $this->callback(function ($data) {
                return $data['first_name'] === 'Jane' &&
                       $data['email'] === 'jane@example.com';
            }))
            ->willReturn($updatedCustomer);

        $result = $this->service->updateCustomer($this->user, $customer, $updateData);

        $this->assertSame($updatedCustomer, $result);
        Event::assertDispatched(CustomerUpdated::class);
    }

    public function testUpdateCustomerWithDuplicateEmailThrowsException(): void
    {
        $customer = new Customer([
            'id' => 1,
            'first_name' => 'John',
            'email' => 'john@example.com',
            'user_id' => 1
        ]);

        $otherCustomer = new Customer([
            'id' => 2,
            'email' => 'jane@example.com',
            'user_id' => 1
        ]);

        $updateData = ['email' => 'jane@example.com']; // Duplicate email

        $this->mockRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user)
            ->willReturn(new Collection([$customer, $otherCustomer]));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('A customer with this email already exists in your account.');

        $this->service->updateCustomer($this->user, $customer, $updateData);
    }

    public function testDeleteCustomer(): void
    {
        $customer = new Customer(['id' => 1, 'first_name' => 'John', 'user_id' => 1]);

        $this->mockRepository->expects($this->once())
            ->method('deleteForUser')
            ->with($this->user, $customer)
            ->willReturn(true);

        $result = $this->service->deleteCustomer($this->user, $customer);

        $this->assertTrue($result);
        Event::assertDispatched(CustomerDeleted::class);
    }

    public function testSearchCustomers(): void
    {
        $query = 'John Doe';
        $limit = 25;
        $expectedResults = new Collection([new Customer(['first_name' => 'John', 'user_id' => 1])]);

        $this->mockRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, [
                'search' => 'John Doe',
                'limit' => 25
            ])
            ->willReturn($expectedResults);

        $result = $this->service->searchCustomers($this->user, $query, $limit);

        $this->assertSame($expectedResults, $result);
    }

    public function testSearchCustomersWithShortQueryReturnsEmpty(): void
    {
        $query = 'J'; // Too short
        
        $result = $this->service->searchCustomers($this->user, $query);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function testGetCustomerStatistics(): void
    {
        $allCustomers = new Collection([
            new Customer(['id' => 1, 'organization' => 'Acme Corp', 'created_at' => now()->subDays(5), 'user_id' => 1]),
            new Customer(['id' => 2, 'organization' => 'Acme Corp', 'created_at' => now()->subDays(15), 'user_id' => 1]),
            new Customer(['id' => 3, 'organization' => 'Beta Inc', 'created_at' => now()->subDays(25), 'user_id' => 1]),
        ]);

        $customer1 = new Customer(['id' => 1, 'user_id' => 1]);
        $customer1->created_at = now()->subDays(5);
        $customer2 = new Customer(['id' => 2, 'user_id' => 1]);
        $customer2->created_at = now()->subDays(15);
        
        $recentCustomers = new Collection([$customer1, $customer2]);

        $this->mockRepository->expects($this->once())
            ->method('getCountForUser')
            ->with($this->user)
            ->willReturn(3);

        $this->mockRepository->expects($this->once())
            ->method('getRecentForUser')
            ->with($this->user, 30)
            ->willReturn($recentCustomers);

        $this->mockRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user)
            ->willReturn($allCustomers);

        $result = $this->service->getCustomerStatistics($this->user);

        $this->assertSame(3, $result['total_customers']);
        $this->assertSame(2, $result['monthly_growth']); // 2 customers in last month (5 and 15 days ago)
        $this->assertSame(1, $result['weekly_growth']); // 1 customer in last week (5 days ago)
        $this->assertArrayHasKey('top_organizations', $result);
    }

    public function testGetCustomerBySlug(): void
    {
        $slug = 'john-doe';
        $expectedCustomer = new Customer(['id' => 1, 'slug' => $slug, 'user_id' => 1]);

        $this->mockRepository->expects($this->once())
            ->method('findBySlugForUser')
            ->with($this->user, $slug)
            ->willReturn($expectedCustomer);

        $result = $this->service->getCustomerBySlug($this->user, $slug);

        $this->assertSame($expectedCustomer, $result);
    }

    public function testGetCustomersByOrganization(): void
    {
        $organization = 'Acme Corp';
        $expectedCustomers = new Collection([new Customer(['organization' => $organization, 'user_id' => 1])]);

        $this->mockRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, ['organization' => $organization])
            ->willReturn($expectedCustomers);

        $result = $this->service->getCustomersByOrganization($this->user, $organization);

        $this->assertSame($expectedCustomers, $result);
    }

    public function testPrepareCustomerDataCleansInput(): void
    {
        $dirtyData = [
            'first_name' => '  John  ',
            'last_name' => '  Doe  ',
            'email' => '  JOHN@EXAMPLE.COM  ',
            'phone' => '  123-456-7890  ',
            'organization' => '',
            'notes' => '  Some notes  ',
        ];

        $customer = new Customer(['id' => 1, 'user_id' => 1]);

        // Mock getAllForUser for duplicate check
        $this->mockRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user)
            ->willReturn(new Collection());

        $this->mockRepository->expects($this->once())
            ->method('createForUser')
            ->with($this->user, $this->callback(function ($data) {
                return $data['first_name'] === 'John' &&
                       $data['last_name'] === 'Doe' &&
                       $data['email'] === 'john@example.com' &&
                       $data['phone'] === '123-456-7890' &&
                       $data['organization'] === null &&
                       $data['notes'] === 'Some notes';
            }))
            ->willReturn($customer);

        $this->service->createCustomer($this->user, $dirtyData);
    }
}