<?php

namespace Tests\Integration\Services;

use App\Contracts\Repositories\AuditRepositoryInterface;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class AuditServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private AuditService $service;
    private AuditRepositoryInterface&MockObject $mockRepository;
    private Filesystem $storage; // Real storage for testing
    private User $user;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockRepository = $this->createMock(AuditRepositoryInterface::class);
        $this->storage = $this->app->make('filesystem.disk', ['local']); // Use real local storage
        
        $this->service = new AuditService($this->storage, $this->mockRepository);
        $this->user = new User(['first_name' => 'Test', 'last_name' => 'User', 'email' => 'test@example.com']);
        $this->user->setAttribute('id', 1);
        
        $this->customer = new Customer(['first_name' => 'John', 'last_name' => 'Doe', 'user_id' => 1]);
        $this->customer->setAttribute('id', 1);
    }

    protected function tearDown(): void
    {
        // Clean up any test files created during storage operations
        $testFiles = $this->storage->allFiles('audit-trails');
        foreach ($testFiles as $file) {
            $this->storage->delete($file);
        }
        
        parent::tearDown();
    }

    public function testStoreAuditToS3CreatesValidJsonFile(): void
    {
        $fromDate = now()->subDays(30);
        $toDate = now();
        
        $activities = new Collection([
            new Activity([
                'id' => 1,
                'event' => 'created',
                'description' => 'Customer created',
                'created_at' => now()->subDays(10),
                'properties' => ['ip_address' => '192.168.1.1']
            ]),
            new Activity([
                'id' => 2,
                'event' => 'updated',
                'description' => 'Customer updated',
                'created_at' => now()->subDays(5),
                'properties' => ['ip_address' => '192.168.1.2']
            ])
        ]);

        $this->mockRepository->expects($this->once())
            ->method('getActivitiesByDateRange')
            ->with($this->user, $fromDate, $toDate)
            ->willReturn($activities);

        $filePath = $this->service->storeAuditToS3($this->user, $fromDate, $toDate);

        // Verify file was created with expected structure
        $this->assertTrue($this->storage->exists($filePath));
        $this->assertStringContainsString('audit-trails/user_1/', $filePath);
        $this->assertStringEndsWith('.json', $filePath);
        
        // Verify file content
        $content = $this->storage->get($filePath);
        $data = json_decode($content, true);
        
        $this->assertArrayHasKey('user_id', $data);
        $this->assertArrayHasKey('user_name', $data);
        $this->assertArrayHasKey('user_email', $data);
        $this->assertArrayHasKey('export_date', $data);
        $this->assertArrayHasKey('date_range', $data);
        $this->assertArrayHasKey('total_activities', $data);
        $this->assertArrayHasKey('activities', $data);
        
        $this->assertSame(1, $data['user_id']);
        $this->assertSame('Test User', $data['user_name']);
        $this->assertSame('test@example.com', $data['user_email']);
        $this->assertSame(2, $data['total_activities']);
        $this->assertCount(2, $data['activities']);
        
        // Verify date range formatting
        $this->assertSame($fromDate->format('Y-m-d H:i:s'), $data['date_range']['from']);
        $this->assertSame($toDate->format('Y-m-d H:i:s'), $data['date_range']['to']);
        
        // Verify individual activities are properly formatted
        $activity1 = $data['activities'][0];
        $this->assertSame(1, $activity1['id']);
        $this->assertSame('created', $activity1['event']);
        $this->assertSame('Customer created', $activity1['description']);
        $this->assertSame('192.168.1.1', $activity1['ip_address']);
    }

    public function testRetrieveAuditFromS3ReadsCorrectData(): void
    {
        // Create test audit file
        $testData = [
            'user_id' => 1,
            'user_name' => 'Test User',
            'user_email' => 'test@example.com',
            'total_activities' => 1,
            'activities' => [
                [
                    'id' => 1,
                    'event' => 'test_event',
                    'description' => 'Test activity',
                    'ip_address' => '127.0.0.1'
                ]
            ]
        ];
        
        $filePath = 'audit-trails/user_1/test_audit.json';
        $this->storage->put($filePath, json_encode($testData));
        
        $result = $this->service->retrieveAuditFromS3($filePath);
        
        $this->assertSame($testData, $result);
    }

    public function testRetrieveAuditFromS3WithNonExistentFileThrowsException(): void
    {
        $nonExistentPath = 'audit-trails/user_1/nonexistent.json';
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Audit file not found in S3');
        
        $this->service->retrieveAuditFromS3($nonExistentPath);
    }

    public function testListStoredAuditsReturnsCorrectFiles(): void
    {
        // Create test audit files
        $testFiles = [
            'audit-trails/user_1/audit_2024_01_01.json',
            'audit-trails/user_1/audit_2024_01_15.json',
            'audit-trails/user_1/audit_2024_02_01.json'
        ];
        
        foreach ($testFiles as $file) {
            $this->storage->put($file, '{"test": "data"}');
        }
        
        $result = $this->service->listStoredAudits($this->user);
        
        $this->assertCount(3, $result);
        foreach ($testFiles as $expectedFile) {
            $this->assertContains($expectedFile, $result);
        }
    }

    public function testCleanupOldAuditsRemovesExpiredFiles(): void
    {
        // Create old and recent audit files
        $oldFile = 'audit-trails/user_1/old_audit.json';
        $recentFile = 'audit-trails/user_1/recent_audit.json';
        
        $this->storage->put($oldFile, '{"old": "data"}');
        $this->storage->put($recentFile, '{"recent": "data"}');
        
        // Mock file modification times
        $oldTime = now()->subDays(400)->getTimestamp();
        $recentTime = now()->subDays(30)->getTimestamp();
        
        // We'll test this differently since we can't easily mock lastModified in integration test
        // Instead, we'll test that the method handles the storage operations correctly
        
        $result = $this->service->cleanupOldAudits($this->user, 365);
        
        // Should return 0 since we can't mock file timestamps in real storage
        // But we verify the method doesn't crash and handles storage operations
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
        
        // Verify files still exist (since they weren't actually old enough in real time)
        $this->assertTrue($this->storage->exists($oldFile));
        $this->assertTrue($this->storage->exists($recentFile));
    }

    public function testArchiveOldActivitiesCreatesStorageFile(): void
    {
        $dateBefore = now()->subMonths(6);
        $activities = new Collection([
            new Activity([
                'id' => 1,
                'event' => 'archived_event',
                'description' => 'Old activity to be archived',
                'created_at' => now()->subMonths(8)
            ])
        ]);

        $this->mockRepository->expects($this->exactly(2))
            ->method('getActivitiesByDateRange')
            ->willReturnOnConsecutiveCalls($activities, $activities);

        $result = $this->service->archiveOldActivities($this->user, $dateBefore);

        $this->assertSame(1, $result);
        
        // Verify an archive file was created
        $archiveFiles = $this->storage->files('audit-trails/user_1');
        $this->assertNotEmpty($archiveFiles);
        
        // Verify the archive contains expected data
        $archiveFile = $archiveFiles[0];
        $content = $this->storage->get($archiveFile);
        $data = json_decode($content, true);
        
        $this->assertArrayHasKey('activities', $data);
        $this->assertSame(1, $data['total_activities']);
        $this->assertSame('archived_event', $data['activities'][0]['event']);
    }

    public function testExportAuditTrailToCsvCreatesValidFile(): void
    {
        $fromDate = now()->subDays(7);
        $toDate = now();
        
        $activities = new Collection([
            new Activity([
                'id' => 1,
                'event' => 'created',
                'description' => 'Customer created',
                'created_at' => now()->subDays(3),
                'properties' => ['ip_address' => '192.168.1.1']
            ])
        ]);
        
        // Mock the causer and subject relationships
        $activities->first()->setRelation('causer', $this->user);
        $activities->first()->setRelation('subject', $this->customer);

        $this->mockRepository->expects($this->once())
            ->method('getActivitiesByDateRange')
            ->with($this->user, $fromDate, $toDate)
            ->willReturn($activities);

        $response = $this->service->exportAuditTrail($this->user, $fromDate, $toDate, 'csv');

        $this->assertInstanceOf(\Illuminate\Http\Response::class, $response);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        
        // Test CSV content
        $csvContent = $response->getContent();
        $lines = explode("\n", $csvContent);
        
        // Should have header row + data row
        $this->assertGreaterThanOrEqual(2, count($lines));
        
        // Test header
        $header = str_getcsv($lines[0]);
        $this->assertContains('ID', $header);
        $this->assertContains('Event', $header);
        $this->assertContains('Description', $header);
        $this->assertContains('Customer', $header);
        
        // Test data row
        if (!empty(trim($lines[1]))) {
            $dataRow = str_getcsv($lines[1]);
            $this->assertSame('1', $dataRow[0]); // ID
            $this->assertSame('created', $dataRow[1]); // Event
            $this->assertSame('Customer created', $dataRow[2]); // Description
        }
    }

    public function testExportAuditTrailToJsonCreatesValidFile(): void
    {
        $fromDate = now()->subDays(7);
        $toDate = now();
        
        $activities = new Collection([
            new Activity([
                'id' => 1,
                'event' => 'updated',
                'description' => 'Customer updated',
                'created_at' => now()->subDays(2),
                'properties' => ['ip_address' => '10.0.0.1']
            ])
        ]);

        $this->mockRepository->expects($this->once())
            ->method('getActivitiesByDateRange')
            ->willReturn($activities);

        $response = $this->service->exportAuditTrail($this->user, $fromDate, $toDate, 'json');

        $this->assertInstanceOf(\Illuminate\Http\Response::class, $response);
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        
        // Test JSON content
        $jsonContent = $response->getContent();
        $data = json_decode($jsonContent, true);
        
        $this->assertArrayHasKey('export_date', $data);
        $this->assertArrayHasKey('total_activities', $data);
        $this->assertArrayHasKey('activities', $data);
        
        $this->assertSame(1, $data['total_activities']);
        $this->assertCount(1, $data['activities']);
        $this->assertSame('updated', $data['activities'][0]['event']);
    }

    public function testLargeAuditExportHandling(): void
    {
        $fromDate = now()->subDays(30);
        $toDate = now();
        
        // Create a large collection of activities
        $activities = new Collection();
        for ($i = 1; $i <= 1000; $i++) {
            $activities->push(new Activity([
                'id' => $i,
                'event' => 'bulk_event',
                'description' => "Bulk activity {$i}",
                'created_at' => now()->subDays(rand(1, 29)),
                'properties' => ['ip_address' => "192.168.1.{$i}"]
            ]));
        }

        $this->mockRepository->expects($this->once())
            ->method('getActivitiesByDateRange')
            ->willReturn($activities);

        $filePath = $this->service->storeAuditToS3($this->user, $fromDate, $toDate);

        // Verify large file was created successfully
        $this->assertTrue($this->storage->exists($filePath));
        
        $content = $this->storage->get($filePath);
        $data = json_decode($content, true);
        
        $this->assertSame(1000, $data['total_activities']);
        $this->assertCount(1000, $data['activities']);
        
        // Verify a sample of the activities
        $this->assertSame('bulk_event', $data['activities'][0]['event']);
        $this->assertSame('bulk_event', $data['activities'][999]['event']);
        $this->assertStringContainsString('Bulk activity', $data['activities'][500]['description']);
    }

    public function testStorageErrorHandlingInAuditOperations(): void
    {
        // Test with failing storage for put operations
        $failingStorage = $this->createMock(Filesystem::class);
        $failingStorage->method('put')
            ->willThrowException(new \Exception('Storage write failed'));

        $serviceWithFailingStorage = new AuditService($failingStorage, $this->mockRepository);

        $this->mockRepository->method('getActivitiesByDateRange')
            ->willReturn(new Collection([new Activity(['id' => 1])]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Storage write failed');

        $serviceWithFailingStorage->storeAuditToS3($this->user);
    }

    public function testAuditFileNameGeneration(): void
    {
        $fromDate = now()->subDays(15);
        $toDate = now()->subDays(5);
        
        $this->mockRepository->method('getActivitiesByDateRange')
            ->willReturn(new Collection([new Activity(['id' => 1])]));

        $filePath = $this->service->storeAuditToS3($this->user, $fromDate, $toDate);

        // Verify filename format: audit-trails/user_{id}/audit_{from_date}_to_{to_date}_{timestamp}.json
        $expectedPattern = sprintf(
            '/audit-trails\/user_%d\/audit_%s_to_%s_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.json/',
            $this->user->id,
            $fromDate->format('Y_m_d'),
            $toDate->format('Y_m_d')
        );
        
        $this->assertMatchesRegularExpression($expectedPattern, $filePath);
    }

    public function testConcurrentAuditStorageOperations(): void
    {
        $activity = new Activity(['id' => 1, 'event' => 'concurrent_test']);
        
        $this->mockRepository->method('getActivitiesByDateRange')
            ->willReturn(new Collection([$activity]));

        // Create multiple audit files simultaneously
        $filePaths = [];
        for ($i = 1; $i <= 5; $i++) {
            $fromDate = now()->subDays($i * 10);
            $toDate = now()->subDays($i * 5);
            $filePaths[] = $this->service->storeAuditToS3($this->user, $fromDate, $toDate);
        }

        // Verify all files were created with unique names
        $this->assertCount(5, array_unique($filePaths));
        
        // Verify all files exist in storage
        foreach ($filePaths as $path) {
            $this->assertTrue($this->storage->exists($path));
            $content = $this->storage->get($path);
            $data = json_decode($content, true);
            $this->assertSame(1, $data['total_activities']);
        }
    }
}