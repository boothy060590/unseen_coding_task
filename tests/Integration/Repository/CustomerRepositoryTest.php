<?php

namespace Tests\Integration\Repository;

use App\Models\Customer;
use App\Models\User;
use App\Repositories\CustomerRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CustomerRepository::class)]
class CustomerRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    public function testGetAllForUser(): void
    {
        // No Filters
        $validUser = User::factory()->create();
        $invalidUser = User::factory()->create();
        Customer::factory(2)->create(['user_id' => $validUser->id]); // Will be returned
        Customer::factory(2)->create(['user_id' => $invalidUser->id]); //Won't be

        $repository = new CustomerRepository();
        $result = $repository->getAllForUser($validUser);

        $this->assertCount(2, $result);
        $result->each(function (Customer $customer) use ($validUser, $invalidUser) {
            $this->assertSame($validUser->id, $customer->user_id);
            $this->assertNotSame($invalidUser->id, $customer->user_id);
        });

        // Now let's start adding filters starting with organisation
        $filteringCustomers = collect(['organization1', 'organization2'])
            ->map(fn (string $organization) => Customer::factory()->create([
                'user_id' => $validUser->id,
                'organization' => $organization
            ]));
        $result = $repository->getAllForUser($validUser, ['organization' => 'organization1']);
        $this->assertCount(1, $result);
        $this->assertSame($result->first()->organization, $filteringCustomers->first()->organization);

        // now let's give the filtering customers the same org but different job_title
        $filteringCustomers->each(function (Customer $customer){
           $customer->organization = 'organization1';
           $customer->job_title = "Job title: $customer->id";
           $customer->save();
        });

        $result = $repository->getAllForUser(
            $validUser,
            [
                'organization' => 'organization1',
                'job_title' => 'Job title: ' . $filteringCustomers->first()->id
            ]
        );
        $this->assertCount(1, $result);
        $this->assertSame($result->first()->job_title, $filteringCustomers->first()->job_title);

        // now set the job title the same, but different created at dates.
        $subDay = true;
        $now = now();
        $filteringCustomers->each(function (Customer $customer) use (&$subDay, $now) {
            $customer->job_title = "Job title";
            $customer->created_at = $subDay ? $now->copy()->subDay() : $now;
            $subDay = false;
            $customer->save();
        });

        $result = $repository->getAllForUser(
            $validUser,
            [
                'organization' => 'organization1',
                'job_title' => 'Job title',
                'created_from' => $now->copy()->subMinutes(10)
            ]
        );

        $this->assertCount(1, $result);
        $this->assertSame(
            $now->format('Y-m-d H:i:s'),
            $result->first()->created_at->format('Y-m-d H:i:s')
        );

        // Now add created_to filter and get the result created yesterday
        $result = $repository->getAllForUser(
            $validUser,
            [
                'organization' => 'organization1',
                'job_title' => 'Job title',
                'created_from' => $now->copy()->subDays(10),
                'created_to' => $now->copy()->subDay()->addMinutes(10)
            ]
        );

        $this->assertCount(1, $result);
        $this->assertSame(
            $now->copy()->subDay()->format('Y-m-d H:i:s'),
            $result->last()->created_at->format('Y-m-d H:i:s')
        );

        // Now let's apply name filtering
        $searchTerm = $filteringCustomers->first()->full_name;

        $result = $repository->getAllForUser(
            $validUser,
            [
                'organization' => 'organization1',
                'job_title' => 'Job title',
                'created_from' => $now->copy()->subDays(10),
                'created_to' => $now->addMinutes(10),
                'search' => $searchTerm
            ]
        );

        $this->assertCount(1, $result);
        $this->assertSame($result->first()->full_name, $searchTerm);

        // now lets filter on e-mail
        $searchTerm = $filteringCustomers->first()->email;
        $result = $repository->getAllForUser(
            $validUser,
            [
                'organization' => 'organization1',
                'job_title' => 'Job title',
                'created_from' => $now->copy()->subDays(10),
                'created_to' => $now->addMinutes(10),
                'search' => $searchTerm
            ]
        );

        $this->assertCount(1, $result);
        $this->assertSame($result->first()->email, $searchTerm);

        // let's add the limit filter
        $result = $repository->getAllForUser(
            $validUser,
            [
                'organization' => 'organization1',
                'job_title' => 'Job title',
                'created_from' => $now->copy()->subDays(10),
                'created_to' => $now->addMinutes(10),
                'limit' => 1
            ]
        );
        $this->assertCount(1, $result);

        $result = $repository->getAllForUser(
            $validUser,
            [
                'organization' => 'organization1',
                'job_title' => 'Job title',
                'created_from' => $now->copy()->subDays(10),
                'created_to' => $now->addMinutes(10),
                'limit' => 2
            ]
        );
        $this->assertCount(2, $result);

        // test sort by
        $result = $repository->getAllForUser(
            $validUser,
            [
                'organization' => 'organization1',
                'job_title' => 'Job title',
                'created_from' => $now->copy()->subDays(10),
                'created_to' => $now->addMinutes(10),
                'sort_by' => 'created_at',
                'sort_direction' => 'desc'
            ]
        );

        $this->assertSame($filteringCustomers->last()->id, $result->first()->id);
        $this->assertTrue($filteringCustomers->first()->created_at < $filteringCustomers->last()->created_at);
    }

    /**
     * @return void
     */
    public function testGetAllForUsersPaginated(): void
    {
        $validUser = User::factory()->create();
        Customer::factory(20)->create(['user_id' => $validUser->id]); // Will be returned

        // The filtering has already been covered in the non-paginated test, so we just need to ensure pagination here
        $repository = new CustomerRepository();

        $result = $repository->getPaginatedForUser($validUser);
        $this->assertCount(15, $result);

        $result = $repository->getPaginatedForUser($validUser, [], 10);
        $this->assertCount(10, $result);

        $result = $repository->getPaginatedForUser($validUser, [], 20);
        $this->assertCount(20, $result);
    }

    /**
     * @return void
     */
    public function testFindForUser(): void
    {
        $user = User::factory()->create();
        $customerOne = Customer::factory()->create(['user_id' => $user->id]);
        $customerTwo = Customer::factory()->create(['user_id' => $user->id]);

        $repository = new CustomerRepository();

        $result = $repository->findForUser($user, $customerOne->id);
        $this->assertSame($result->email, $customerOne->email);

        $result = $repository->findForUser($user, $customerTwo->id);
        $this->assertSame($result->email, $customerTwo->email);
    }

    /**
     * @return void
     */
    public function testFindBySlugForUser(): void
    {
        $user = User::factory()->create();
        $customerOne = Customer::factory()->create(['user_id' => $user->id]);
        $customerTwo = Customer::factory()->create(['user_id' => $user->id]);

        $repository = new CustomerRepository();

        $result = $repository->findBySlugForUser($user, $customerOne->slug);
        $this->assertSame($result->slug, $customerOne->slug);

        $result = $repository->findBySlugForUser($user, $customerTwo->slug);
        $this->assertSame($result->slug, $customerTwo->slug);
    }

    /**
     * @return void
     */
    public function testCreateForUser(): void
    {
        $user = User::factory()->create();
        $repository = new CustomerRepository();

        $customerData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '123-456-7890',
            'organization' => 'Test Corp',
            'job_title' => 'Manager',
            'notes' => 'Test customer notes',
        ];

        $customer = $repository->createForUser($user, $customerData);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertSame($user->id, $customer->user_id);
        $this->assertSame('John', $customer->first_name);
        $this->assertSame('Doe', $customer->last_name);
        $this->assertSame('john.doe@example.com', $customer->email);
        $this->assertSame('Test Corp', $customer->organization);
        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com'
        ]);
    }

    /**
     * @return void
     */
    public function testUpdateForUser(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $repository = new CustomerRepository();

        $updateData = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'organization' => 'Updated Corp'
        ];

        $updatedCustomer = $repository->updateForUser($user, $customer, $updateData);

        $this->assertInstanceOf(Customer::class, $updatedCustomer);
        $this->assertSame($customer->id, $updatedCustomer->id);
        $this->assertSame('Jane', $updatedCustomer->first_name);
        $this->assertSame('Smith', $updatedCustomer->last_name);
        $this->assertSame('Updated Corp', $updatedCustomer->organization);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'organization' => 'Updated Corp'
        ]);
    }

    /**
     * @return void
     */
    public function testUpdateForUserThrowsExceptionForWrongUser(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $anotherUser->id]);
        $repository = new CustomerRepository();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer does not belong to the specified user');

        $repository->updateForUser($user, $customer, ['first_name' => 'Jane']);
    }

    /**
     * @return void
     */
    public function testDeleteForUser(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $repository = new CustomerRepository();

        $result = $repository->deleteForUser($user, $customer);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    /**
     * @return void
     */
    public function testDeleteForUserThrowsExceptionForWrongUser(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $anotherUser->id]);
        $repository = new CustomerRepository();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer does not belong to the specified user');

        $repository->deleteForUser($user, $customer);
    }

    /**
     * @return void
     */
    public function testGetCountForUser(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        Customer::factory(3)->create(['user_id' => $user->id]);
        Customer::factory(2)->create(['user_id' => $anotherUser->id]);

        $repository = new CustomerRepository();

        $count = $repository->getCountForUser($user);
        $this->assertSame(3, $count);

        $anotherCount = $repository->getCountForUser($anotherUser);
        $this->assertSame(2, $anotherCount);
    }

    /**
     * @return void
     */
    public function testGetRecentForUser(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        // Create customers with different created_at times
        $oldCustomer = Customer::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subDays(5)
        ]);

        $recentCustomer = Customer::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subHours(1)
        ]);

        $newestCustomer = Customer::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()
        ]);

        // Create customer for another user
        Customer::factory()->create(['user_id' => $anotherUser->id]);

        $repository = new CustomerRepository();

        // Test default limit (10)
        $recent = $repository->getRecentForUser($user);
        $this->assertCount(3, $recent);

        // Test that results are ordered by most recent first
        $this->assertSame($newestCustomer->id, $recent->first()->id);
        $this->assertSame($recentCustomer->id, $recent->get(1)->id);
        $this->assertSame($oldCustomer->id, $recent->last()->id);

        // Test with custom limit
        $recent = $repository->getRecentForUser($user, 2);
        $this->assertCount(2, $recent);
        $this->assertSame($newestCustomer->id, $recent->first()->id);
        $this->assertSame($recentCustomer->id, $recent->last()->id);

        // Ensure only user's customers are returned
        $recent->each(function (Customer $customer) use ($user) {
            $this->assertSame($user->id, $customer->user_id);
        });
    }
}
