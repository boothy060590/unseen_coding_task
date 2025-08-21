<?php

namespace Tests\Integration\Repository;

use App\Models\Customer;
use App\Models\User;
use App\Repositories\AuditRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use App\Models\Activity;
use Tests\TestCase;

#[CoversClass(AuditRepository::class)]
class AuditRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    public function testGetAllForUser(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $anotherCustomer = Customer::factory()->create(['user_id' => $anotherUser->id]);

        // Create activities for both users
        Activity::factory()
            ->customerCreated()
            ->causedBy($user)
            ->performedOn($customer)
            ->createdAt(now()->subDays(2))
            ->create();

        Activity::factory()
            ->customerUpdated()
            ->causedBy($user)
            ->performedOn($customer)
            ->createdAt(now()->subDay())
            ->create();

        Activity::factory()
            ->customerCreated()
            ->causedBy($anotherUser)
            ->performedOn($anotherCustomer)
            ->createdAt(now())
            ->create();

        $repository = new AuditRepository();

        // Test basic getAllForUser
        $result = $repository->getAllForUser($user);
        $this->assertCount(2, $result);
        $result->each(function (Activity $activity) use ($user) {
            $this->assertSame($user->id, $activity->causer_id);
        });

        // Test with event filter
        $result = $repository->getAllForUser($user, ['event' => 'created']);
        $this->assertCount(1, $result);
        $this->assertSame('created', $result->first()->event);

        // Test with date filters
        $result = $repository->getAllForUser($user, [
            'date_from' => now()->subDay()->subHour(),
            'date_to' => now()->addHour(),
        ]);
        $this->assertCount(1, $result);
        $this->assertSame('updated', $result->first()->event);

        // Test with limit
        $result = $repository->getAllForUser($user, ['limit' => 1]);
        $this->assertCount(1, $result);

        // Test with customer_ids filter
        $result = $repository->getAllForUser($user, ['customer_ids' => [$customer->id]]);
        $this->assertCount(2, $result);

        // Test with sorting
        $result = $repository->getAllForUser($user, [
            'sort_by' => 'created_at',
            'sort_direction' => 'asc'
        ]);
        $this->assertCount(2, $result);
        $this->assertTrue($result->first()->created_at <= $result->last()->created_at);

        // Test with invalid sort_by field (should fall back to latest())
        $result = $repository->getAllForUser($user, [
            'sort_by' => 'invalid_field',
            'sort_direction' => 'asc'
        ]);
        $this->assertCount(2, $result);
        // Should be ordered by created_at desc (latest) regardless of sort_direction
        $this->assertTrue($result->first()->created_at >= $result->last()->created_at);
    }

    /**
     * @return void
     */
    public function testGetPaginatedForUser(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);

        // Create multiple activities
        Activity::factory(20)
            ->event('test')
            ->causedBy($user)
            ->performedOn($customer)
            ->create();

        $repository = new AuditRepository();

        // Test default pagination
        $result = $repository->getPaginatedForUser($user);
        $this->assertCount(15, $result);
        $this->assertSame(20, $result->total());

        // Test custom per page
        $result = $repository->getPaginatedForUser($user, [], 10);
        $this->assertCount(10, $result);

        // Test with filters
        $result = $repository->getPaginatedForUser($user, ['event' => 'test'], 5);
        $this->assertCount(5, $result);
    }

    /**
     * @return void
     */
    public function testGetCustomerAuditTrail(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);

        Activity::factory()
            ->customerCreated()
            ->causedBy($user)
            ->performedOn($customer)
            ->create();

        $repository = new AuditRepository();

        $result = $repository->getCustomerAuditTrail($user, $customer);
        $this->assertCount(1, $result);

        // Test security - should throw exception for wrong user
        $wrongCustomer = Customer::factory()->create(['user_id' => $anotherUser->id]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer does not belong to the specified user');
        $repository->getCustomerAuditTrail($user, $wrongCustomer);
    }

    /**
     * @return void
     */
    public function testGetUserAuditTrail(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);

        Activity::factory()
            ->customerCreated()
            ->causedBy($user)
            ->performedOn($customer)
            ->create();

        $repository = new AuditRepository();

        $result = $repository->getUserAuditTrail($user);
        $this->assertCount(1, $result);
    }

    /**
     * @return void
     */
    public function testGetRecentUserActivities(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);

        // Create activities with different timestamps
        Activity::factory(15)
            ->event('test')
            ->causedBy($user)
            ->performedOn($customer)
            ->sequence(fn ($sequence) => [
                'created_at' => now()->subMinutes($sequence->index)
            ])
            ->create();

        $repository = new AuditRepository();

        // Test default limit
        $result = $repository->getRecentUserActivities($user);
        $this->assertCount(10, $result);

        // Test custom limit
        $result = $repository->getRecentUserActivities($user, 5);
        $this->assertCount(5, $result);

        // Verify ordering (most recent first)
        $this->assertTrue($result->first()->created_at >= $result->last()->created_at);
    }

    /**
     * @return void
     */
    public function testGetActivitiesByDateRange(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);

        $fromDate = now()->subDays(3);
        $toDate = now()->subDay();

        // Activity within range
        Activity::factory()
            ->event('in_range')
            ->causedBy($user)
            ->performedOn($customer)
            ->createdAt(now()->subDays(2))
            ->create();

        // Activity outside range
        Activity::factory()
            ->event('out_range')
            ->causedBy($user)
            ->performedOn($customer)
            ->createdAt(now())
            ->create();

        $repository = new AuditRepository();

        $result = $repository->getActivitiesByDateRange($user, $fromDate, $toDate);
        $this->assertCount(1, $result);
        $this->assertSame('in_range', $result->first()->event);
    }

    /**
     * @return void
     */
    public function testGetActivitiesByEvent(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);

        Activity::factory()
            ->customerCreated()
            ->causedBy($user)
            ->performedOn($customer)
            ->create();

        Activity::factory()
            ->customerUpdated()
            ->causedBy($user)
            ->performedOn($customer)
            ->create();

        $repository = new AuditRepository();

        $result = $repository->getActivitiesByEvent($user, 'created');
        $this->assertCount(1, $result);
        $this->assertSame('created', $result->first()->event);
    }

    /**
     * @return void
     */
    public function testGetActivityCountForUser(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $anotherCustomer = Customer::factory()->create(['user_id' => $anotherUser->id]);

        // Create activities for user
        Activity::factory(3)
            ->event('test')
            ->causedBy($user)
            ->performedOn($customer)
            ->create();

        // Create activities for another user
        Activity::factory(2)
            ->event('test')
            ->causedBy($anotherUser)
            ->performedOn($anotherCustomer)
            ->create();

        $repository = new AuditRepository();

        $count = $repository->getActivityCountForUser($user);
        $this->assertSame(3, $count);

        $anotherCount = $repository->getActivityCountForUser($anotherUser);
        $this->assertSame(2, $anotherCount);
    }

    /**
     * @return void
     */
    public function testGetActivityCountsByEvent(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);

        // Create activities with different events
        Activity::factory(2)
            ->customerCreated()
            ->causedBy($user)
            ->performedOn($customer)
            ->create();

        Activity::factory()
            ->customerUpdated()
            ->causedBy($user)
            ->performedOn($customer)
            ->create();

        $repository = new AuditRepository();

        $counts = $repository->getActivityCountsByEvent($user);
        $this->assertSame(2, $counts['created']);
        $this->assertSame(1, $counts['updated']);
    }

    /**
     * @return void
     */
    public function testFindActivityForUser(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $anotherCustomer = Customer::factory()->create(['user_id' => $anotherUser->id]);

        $userActivity = Activity::factory()
            ->event('test')
            ->causedBy($user)
            ->performedOn($customer)
            ->create();

        $anotherUserActivity = Activity::factory()
            ->event('test')
            ->causedBy($anotherUser)
            ->performedOn($anotherCustomer)
            ->create();

        $repository = new AuditRepository();

        // Should find user's activity
        $result = $repository->findActivityForUser($user, $userActivity->id);
        $this->assertNotNull($result);
        $this->assertSame($userActivity->id, $result->id);

        // Should not find another user's activity
        $result = $repository->findActivityForUser($user, $anotherUserActivity->id);
        $this->assertNull($result);

        // Should return null for non-existent activity
        $result = $repository->findActivityForUser($user, 99999);
        $this->assertNull($result);
    }

    /**
     * @return void
     */
    public function testGetMostActiveCustomers(): void
    {
        $user = User::factory()->create();
        $customer1 = Customer::factory()->create(['user_id' => $user->id]);
        $customer2 = Customer::factory()->create(['user_id' => $user->id]);
        $customer3 = Customer::factory()->create(['user_id' => $user->id]);

        // Customer1: 3 activities
        Activity::factory(3)
            ->event('test')
            ->causedBy($user)
            ->performedOn($customer1)
            ->create();

        // Customer2: 1 activity
        Activity::factory()
            ->event('test')
            ->causedBy($user)
            ->performedOn($customer2)
            ->create();

        // Customer3: 2 activities
        Activity::factory(2)
            ->event('test')
            ->causedBy($user)
            ->performedOn($customer3)
            ->create();

        $repository = new AuditRepository();

        $result = $repository->getMostActiveCustomers($user);
        $this->assertCount(3, $result);

        // Should be ordered by activity count descending
        $this->assertSame($customer1->id, $result->first()['customer']->id);
        $this->assertSame(3, $result->first()['activity_count']);

        $this->assertSame($customer3->id, $result->get(1)['customer']->id);
        $this->assertSame(2, $result->get(1)['activity_count']);

        $this->assertSame($customer2->id, $result->last()['customer']->id);
        $this->assertSame(1, $result->last()['activity_count']);

        // Test with limit
        $result = $repository->getMostActiveCustomers($user, 2);
        $this->assertCount(2, $result);
    }

    /**
     * @return void
     */
    public function testLogCustomerActivity(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $anotherUser = User::factory()->create();
        $wrongCustomer = Customer::factory()->create(['user_id' => $anotherUser->id]);

        $repository = new AuditRepository();

        // Test successful logging
        $activity = $repository->logCustomerActivity(
            $user,
            $customer,
            'custom_event',
            'Custom activity description',
            ['key' => 'value']
        );

        $this->assertInstanceOf(Activity::class, $activity);
        $this->assertSame($user->id, $activity->causer_id);
        $this->assertSame($customer->id, $activity->subject_id);
        $this->assertSame('custom_event', $activity->event);
        $this->assertSame('Custom activity description', $activity->description);
        $this->assertSame('value', $activity->properties['key']);

        // Test security - should throw exception for wrong user
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer does not belong to the specified user');
        $repository->logCustomerActivity($user, $wrongCustomer, 'test', 'test');
    }

    /**
     * @return void
     */
    public function testGetActivitiesForCustomers(): void
    {
        $user = User::factory()->create();
        $customer1 = Customer::factory()->create(['user_id' => $user->id]);
        $customer2 = Customer::factory()->create(['user_id' => $user->id]);
        $customer3 = Customer::factory()->create(['user_id' => $user->id]);

        // Activities for customer1 and customer2
        Activity::factory()
            ->event('test')
            ->causedBy($user)
            ->performedOn($customer1)
            ->create();

        Activity::factory()
            ->event('test')
            ->causedBy($user)
            ->performedOn($customer2)
            ->create();

        // Activity for customer3 (should not be included)
        Activity::factory()
            ->event('test')
            ->causedBy($user)
            ->performedOn($customer3)
            ->create();

        $repository = new AuditRepository();

        $result = $repository->getActivitiesForCustomers($user, [$customer1->id, $customer2->id]);
        $this->assertCount(2, $result);

        $subjectIds = $result->pluck('subject_id')->toArray();
        $this->assertContains($customer1->id, $subjectIds);
        $this->assertContains($customer2->id, $subjectIds);
        $this->assertNotContains($customer3->id, $subjectIds);
    }
}
