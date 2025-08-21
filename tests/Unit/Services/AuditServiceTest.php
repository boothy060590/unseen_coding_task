<?php

namespace Tests\Unit\Services;

use App\Contracts\Repositories\AuditRepositoryInterface;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(AuditService::class)]
class AuditServiceTest extends TestCase
{
    private AuditService $service;
    private AuditRepositoryInterface&MockObject $mockRepository;
    private Filesystem&MockObject $mockStorage;
    private User $user;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = $this->createMock(AuditRepositoryInterface::class);
        $this->mockStorage = $this->createMock(Filesystem::class);

        $this->service = new AuditService($this->mockStorage, $this->mockRepository);

        // Create User with proper ID using setAttribute
        $this->user = new User([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com'
        ]);
        $this->user->setAttribute('id', 1);

        // Create Customer with proper ID and user_id using setAttribute
        $this->customer = new Customer([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'user_id' => 1
        ]);
        $this->customer->setAttribute('id', 1);
    }

    public function testGetCustomerAuditTrail(): void
    {
        $paginatedActivities = $this->createMock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class);

        $this->mockRepository->expects($this->once())
            ->method('getCustomerAuditTrail')
            ->with($this->user, $this->customer, 15)
            ->willReturn($paginatedActivities);

        $result = $this->service->getCustomerAuditTrail($this->user, $this->customer);

        $this->assertSame($paginatedActivities, $result);
    }

    public function testGetUserAuditTrail(): void
    {
        $paginatedActivities = $this->createMock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class);

        $this->mockRepository->expects($this->once())
            ->method('getUserAuditTrail')
            ->with($this->user, 20)
            ->willReturn($paginatedActivities);

        $result = $this->service->getUserAuditTrail($this->user, 20);

        $this->assertSame($paginatedActivities, $result);
    }

    public function testGetRecentUserActivities(): void
    {
        $activities = new Collection([new Activity(['id' => 1])]);

        $this->mockRepository->expects($this->once())
            ->method('getRecentUserActivities')
            ->with($this->user, 10)
            ->willReturn($activities);

        $result = $this->service->getRecentUserActivities($this->user);

        $this->assertSame($activities, $result);
    }

    public function testGetRecentUserActivitiesWithSince(): void
    {
        $activities = new Collection([new Activity(['id' => 1])]);
        $since = '2024-01-01';

        $this->mockRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, $this->callback(function ($filters) use ($since) {
                return isset($filters['date_from']) &&
                       $filters['date_from']->format('Y-m-d') === $since &&
                       $filters['limit'] === 5 &&
                       $filters['sort_by'] === 'created_at' &&
                       $filters['sort_direction'] === 'desc';
            }))
            ->willReturn($activities);

        $result = $this->service->getRecentUserActivities($this->user, 5, $since);

        $this->assertSame($activities, $result);
    }

    public function testGetAuditStatistics(): void
    {
        $todayActivities = new Collection([new Activity(['id' => 1])]);
        $weekActivities = new Collection([new Activity(['id' => 1]), new Activity(['id' => 2])]);
        $monthActivities = new Collection([new Activity(['id' => 1]), new Activity(['id' => 2]), new Activity(['id' => 3])]);
        $eventBreakdown = ['created' => 5, 'updated' => 3];
        $mostActiveCustomers = new Collection([new Customer(['id' => 1, 'user_id' => 1])]);

        $this->mockRepository->expects($this->once())
            ->method('getActivityCountForUser')
            ->with($this->user)
            ->willReturn(100);

        $this->mockRepository->expects($this->once())
            ->method('getActivityCountsByEvent')
            ->with($this->user)
            ->willReturn($eventBreakdown);

        $this->mockRepository->expects($this->once())
            ->method('getMostActiveCustomers')
            ->with($this->user, 5)
            ->willReturn($mostActiveCustomers);

        $this->mockRepository->expects($this->exactly(3))
            ->method('getActivitiesByDateRange')
            ->willReturnOnConsecutiveCalls($todayActivities, $weekActivities, $monthActivities);

        $result = $this->service->getAuditStatistics($this->user);

        $this->assertSame(100, $result['total_activities']);
        $this->assertSame(1, $result['today_activities']);
        $this->assertSame(2, $result['week_activities']);
        $this->assertSame(3, $result['month_activities']);
        $this->assertSame($eventBreakdown, $result['event_breakdown']);
        $this->assertSame($mostActiveCustomers, $result['most_active_customers']);
    }

    public function testGetCustomerActivitySummary(): void
    {
        $activities = new Collection([
            new Activity(['id' => 1, 'event' => 'created', 'created_at' => now()->subDays(5)]),
            new Activity(['id' => 2, 'event' => 'updated', 'created_at' => now()->subDays(2)]),
            new Activity(['id' => 3, 'event' => 'created', 'created_at' => now()->subDays(1)]),
        ]);

        $this->mockRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, $this->callback(function ($filters) {
                return $filters['customer_ids'] === [$this->customer->id] &&
                       $filters['limit'] === 1000;
            }))
            ->willReturn($activities);

        $result = $this->service->getCustomerActivitySummary($this->user, $this->customer);

        $this->assertSame(3, $result['total_activities']);
        $this->assertArrayHasKey('first_activity', $result);
        $this->assertArrayHasKey('last_activity', $result);
        $this->assertSame(['created' => 2, 'updated' => 1], $result['event_breakdown']);
        $this->assertSame('created', $result['most_recent_event']);
        $this->assertArrayHasKey('activity_frequency', $result);
    }

    public function testFormatActivity(): void
    {
        // Create activity with proper attributes
        $activity = new Activity([
            'id' => 1,
            'event' => 'created',
            'description' => 'Customer created',
            'created_at' => now()
        ]);
        $activity->setAttribute('id', 1);

        // Set properties as a proper collection
        $properties = collect(['ip_address' => '192.168.1.1', 'user_agent' => 'Mozilla/5.0']);
        $activity->properties = $properties;

        // Create real model instances instead of mocks for better compatibility
        $causer = new User([
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);
        $causer->setAttribute('id', 2);

        $subject = new Customer([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'user_id' => 1
        ]);
        $subject->setAttribute('id', 2);

        // Use setRelation to set up the relationships properly
        $activity->setRelation('causer', $causer);
        $activity->setRelation('subject', $subject);

        $result = $this->service->formatActivity($activity);

        $this->assertSame(1, $result['id']);
        $this->assertSame('created', $result['event']);
        $this->assertSame('Customer created', $result['description']);
        $this->assertSame('John Doe', $result['causer']);
        $this->assertSame('Test Customer', $result['subject']);
        $this->assertSame('192.168.1.1', $result['ip_address']);
        $this->assertSame('Mozilla/5.0', $result['user_agent']);
        $this->assertArrayHasKey('changes', $result);
    }

    public function testStoreAuditToS3(): void
    {
        $fromDate = now()->subDays(30);
        $toDate = now();

        // Create activities with proper properties
        $activity = new Activity([
            'id' => 1,
            'event' => 'created',
            'description' => 'Test activity',
            'created_at' => now()
        ]);
        $activity->properties = collect([]);

        $activities = new Collection([$activity]);

        $this->mockRepository->expects($this->once())
            ->method('getActivitiesByDateRange')
            ->with($this->user, $fromDate, $toDate)
            ->willReturn($activities);

        $this->mockStorage->expects($this->once())
            ->method('put')
            ->with(
                $this->stringContains('audit-trails/user_1/audit_'),
                $this->isType('string')
            );

        $result = $this->service->storeAuditToS3($this->user, $fromDate, $toDate);

        $this->assertStringContainsString('audit-trails/user_1/audit_', $result);
        $this->assertStringEndsWith('.json', $result);
    }

    public function testRetrieveAuditFromS3(): void
    {
        $filePath = 'audit-trails/user_1/audit_test.json';
        $auditData = ['user_id' => 1, 'activities' => []];

        $this->mockStorage->expects($this->once())
            ->method('exists')
            ->with($filePath)
            ->willReturn(true);

        $this->mockStorage->expects($this->once())
            ->method('get')
            ->with($filePath)
            ->willReturn(json_encode($auditData));

        $result = $this->service->retrieveAuditFromS3($filePath);

        $this->assertSame($auditData, $result);
    }

    public function testRetrieveAuditFromS3WithMissingFileThrowsException(): void
    {
        $filePath = 'audit-trails/user_1/nonexistent.json';

        $this->mockStorage->expects($this->once())
            ->method('exists')
            ->with($filePath)
            ->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Audit file not found in S3');

        $this->service->retrieveAuditFromS3($filePath);
    }

    public function testListStoredAudits(): void
    {
        $expectedFiles = ['audit-trails/user_1/audit_1.json', 'audit-trails/user_1/audit_2.json'];

        $this->mockStorage->expects($this->once())
            ->method('files')
            ->with('audit-trails/user_1/')
            ->willReturn($expectedFiles);

        $result = $this->service->listStoredAudits($this->user);

        $this->assertSame($expectedFiles, $result);
    }

    public function testCleanupOldAudits(): void
    {
        $files = ['audit-trails/user_1/old_file.json', 'audit-trails/user_1/recent_file.json'];
        $cutoffTimestamp = now()->subDays(365)->getTimestamp();

        $this->mockStorage->expects($this->once())
            ->method('files')
            ->with('audit-trails/user_1/')
            ->willReturn($files);

        $this->mockStorage->expects($this->exactly(2))
            ->method('lastModified')
            ->willReturnOnConsecutiveCalls($cutoffTimestamp - 1000, $cutoffTimestamp + 1000);

        $this->mockStorage->expects($this->once())
            ->method('delete')
            ->with('audit-trails/user_1/old_file.json');

        $result = $this->service->cleanupOldAudits($this->user);

        $this->assertSame(1, $result);
    }

    public function testLogCustomerActivity(): void
    {
        $event = 'custom_event';
        $description = 'Custom activity';
        $properties = ['key' => 'value'];
        $expectedActivity = new Activity(['id' => 1]);

        $this->mockRepository->expects($this->once())
            ->method('logCustomerActivity')
            ->with($this->user, $this->customer, $event, $description, $properties)
            ->willReturn($expectedActivity);

        $result = $this->service->logCustomerActivity($this->user, $this->customer, $event, $description, $properties);

        $this->assertSame($expectedActivity, $result);
    }

    public function testFormatActivityForApi(): void
    {
        $activity = new Activity([
            'id' => 1,
            'event' => 'created',
            'description' => 'Customer created',
            'created_at' => now()
        ]);
        $activity->setAttribute('id', 1);

        $subject = new Customer([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'user_id' => 1
        ]);
        $subject->setAttribute('id', 2);
        $subject->setAttribute('slug', 'john-doe'); // Set the slug manually

        // Use setRelation to set up the relationship properly
        $activity->setRelation('subject', $subject);

        $result = $this->service->formatActivityForApi($activity);

        $this->assertSame(1, $result['id']);
        $this->assertSame('created', $result['event']);
        $this->assertSame('Customer created', $result['description']);
        $this->assertSame('John Doe', $result['customer_name']);
        $this->assertSame('john-doe', $result['customer_slug']);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('created_at_human', $result);
    }

    public function testGetDetailedActivity(): void
    {
        $activityId = 1;
        $expectedActivity = new Activity(['id' => $activityId]);

        $this->mockRepository->expects($this->once())
            ->method('findActivityForUser')
            ->with($this->user, $activityId)
            ->willReturn($expectedActivity);

        $result = $this->service->getDetailedActivity($this->user, $activityId);

        $this->assertSame($expectedActivity, $result);
    }

    public function testGetActivitiesByEvent(): void
    {
        $event = 'created';
        $expectedActivities = new Collection([new Activity(['event' => $event])]);

        $this->mockRepository->expects($this->once())
            ->method('getActivitiesByEvent')
            ->with($this->user, $event)
            ->willReturn($expectedActivities);

        $result = $this->service->getActivitiesByEvent($this->user, $event);

        $this->assertSame($expectedActivities, $result);
    }

    public function testGetActivitiesByDateRange(): void
    {
        $fromDate = now()->subDays(7);
        $toDate = now();
        $expectedActivities = new Collection([new Activity(['id' => 1])]);

        $this->mockRepository->expects($this->once())
            ->method('getActivitiesByDateRange')
            ->with($this->user, $fromDate, $toDate)
            ->willReturn($expectedActivities);

        $result = $this->service->getActivitiesByDateRange($this->user, $fromDate, $toDate);

        $this->assertSame($expectedActivities, $result);
    }

    public function testGetFilteredActivities(): void
    {
        $filters = ['event' => 'created', 'limit' => 50];
        $expectedActivities = new Collection([new Activity(['event' => 'created'])]);

        $this->mockRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, $filters)
            ->willReturn($expectedActivities);

        $result = $this->service->getFilteredActivities($this->user, $filters);

        $this->assertSame($expectedActivities, $result);
    }

    public function testGetFilteredStatistics(): void
    {
        $filters = ['event' => 'created'];
        $filteredActivities = new Collection([
            new Activity(['event' => 'created']),
            new Activity(['event' => 'created']),
            new Activity(['event' => 'updated']),
        ]);

        $this->mockRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, $filters)
            ->willReturn($filteredActivities);

        $this->mockRepository->expects($this->once())
            ->method('getActivityCountForUser')
            ->with($this->user)
            ->willReturn(100);

        $result = $this->service->getFilteredStatistics($this->user, $filters);

        $this->assertSame(100, $result['total_activities']);
        $this->assertSame(3, $result['filtered_count']);
        $this->assertSame(['created' => 2, 'updated' => 1], $result['event_breakdown']);
    }

    public function testExportAuditTrail(): void
    {
        $fromDate = now()->subDays(30);
        $toDate = now();

        // Create activity with proper created_at timestamp
        $activity = new Activity([
            'id' => 1,
            'event' => 'created',
            'description' => 'Test activity',
            'created_at' => now()
        ]);
        $activity->properties = collect(['ip_address' => '127.0.0.1']);

        $activities = new Collection([$activity]);

        $this->mockRepository->expects($this->once())
            ->method('getActivitiesByDateRange')
            ->with($this->user, $fromDate, $toDate)
            ->willReturn($activities);

        $result = $this->service->exportAuditTrail($this->user, $fromDate, $toDate, 'csv');

        $this->assertInstanceOf(\Illuminate\Http\Response::class, $result);
        $this->assertStringContainsString('text/csv', $result->headers->get('Content-Type'));
    }

    public function testGetStatisticsForPeriod(): void
    {
        $activities = new Collection([
            new Activity(['id' => 1, 'event' => 'created', 'created_at' => now()->subDays(2)]),
            new Activity(['id' => 2, 'event' => 'updated', 'created_at' => now()->subDays(1)]),
        ]);

        $this->mockRepository->expects($this->once())
            ->method('getActivitiesByDateRange')
            ->willReturn($activities);

        $result = $this->service->getStatisticsForPeriod($this->user, '7days');

        $this->assertSame('7days', $result['period']);
        $this->assertSame(2, $result['total_activities']);
        $this->assertSame(['created' => 1, 'updated' => 1], $result['event_breakdown']);
        $this->assertArrayHasKey('daily_breakdown', $result);
    }

    public function testArchiveOldActivities(): void
    {
        $dateBefore = now()->subMonths(6);

        // Create activity with proper properties
        $activity = new Activity([
            'id' => 1,
            'event' => 'test',
            'description' => 'Test activity',
            'created_at' => now()->subMonths(8)
        ]);
        $activity->properties = collect([]);

        $activities = new Collection([$activity]);

        $this->mockRepository->expects($this->exactly(2))
            ->method('getActivitiesByDateRange')
            ->willReturnOnConsecutiveCalls($activities, $activities);

        $this->mockStorage->expects($this->once())
            ->method('put');

        $result = $this->service->archiveOldActivities($this->user, $dateBefore);

        $this->assertSame(1, $result);
    }

    public function testArchiveOldActivitiesWithNoActivities(): void
    {
        $dateBefore = now()->subMonths(6);
        $emptyCollection = new Collection();

        $this->mockRepository->expects($this->once())
            ->method('getActivitiesByDateRange')
            ->willReturn($emptyCollection);

        $result = $this->service->archiveOldActivities($this->user, $dateBefore);

        $this->assertSame(0, $result);
    }
}
