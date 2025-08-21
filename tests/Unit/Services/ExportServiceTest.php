<?php

namespace Tests\Unit\Services;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\ExportRepositoryInterface;
use App\Models\Customer;
use App\Models\Export;
use App\Models\User;
use App\Services\ExportService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(ExportService::class)]
class ExportServiceTest extends TestCase
{
    private ExportService $service;
    private ExportRepositoryInterface&MockObject $mockExportRepository;
    private CustomerRepositoryInterface&MockObject $mockCustomerRepository;
    private Filesystem&MockObject $mockStorage;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockExportRepository = $this->createMock(ExportRepositoryInterface::class);
        $this->mockCustomerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->mockStorage = $this->createMock(Filesystem::class);

        $this->service = new ExportService(
            $this->mockExportRepository,
            $this->mockCustomerRepository,
            $this->mockStorage
        );

        $this->user = new User(['first_name' => 'Test', 'last_name' => 'User']);
        $this->user->setAttribute('id', 1);
    }

    public function testGetDashboardData(): void
    {
        $paginatedExports = $this->createMock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class);
        $downloadableExports = new Collection([new Export(['id' => 1])]);
        $recentExports = new Collection([new Export(['id' => 2])]);
        $allExports = new Collection([new Export(['status' => 'completed'])]);

        $this->mockExportRepository->expects($this->once())
            ->method('getPaginatedForUser')
            ->with($this->user, [])
            ->willReturn($paginatedExports);

        $this->mockExportRepository->expects($this->once())
            ->method('getDownloadableForUser')
            ->with($this->user)
            ->willReturn($downloadableExports);

        $this->mockExportRepository->expects($this->once())
            ->method('getRecentForUser')
            ->with($this->user, 5)
            ->willReturn($recentExports);

        // For getExportStatistics
        $this->mockExportRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, [])
            ->willReturn($allExports);

        $result = $this->service->getDashboardData($this->user);

        $this->assertSame($paginatedExports, $result['exports']);
        $this->assertSame($downloadableExports, $result['downloadable_exports']);
        $this->assertSame($recentExports, $result['recent_exports']);
        $this->assertArrayHasKey('stats', $result);
    }

    public function testCreateExport(): void
    {
        $format = 'csv';
        $filters = ['organization' => 'Acme'];
        $options = ['expires_days' => 14];

        $customers = new Collection([
            new Customer(['id' => 1, 'user_id' => 1]),
            new Customer(['id' => 2, 'user_id' => 1]),
        ]);

        $expectedExport = new Export(['id' => 1, 'filename' => 'test.csv']);

        $this->mockCustomerRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, $filters)
            ->willReturn($customers);

        $this->mockExportRepository->expects($this->once())
            ->method('createForUser')
            ->with($this->user, $this->callback(function ($data) use ($format) {
                return $data['format'] === $format &&
                       $data['type'] === 'filtered' &&
                       $data['total_records'] === 2 &&
                       str_contains($data['filename'], 'customers_export_Acme') &&
                       str_ends_with($data['filename'], '.csv');
            }))
            ->willReturn($expectedExport);

        $result = $this->service->createExport($this->user, $format, $filters, $options);

        $this->assertSame($expectedExport, $result);
    }

    public function testCreateExportWithInvalidFormatThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported export format: invalid');

        $this->service->createExport($this->user, 'invalid');
    }

    public function testStartProcessing(): void
    {
        $export = new Export(['id' => 1, 'user_id' => 1, 'status' => 'pending']);
        $updatedExport = new Export(['id' => 1, 'status' => 'processing']);

        $this->mockExportRepository->expects($this->once())
            ->method('updateForUser')
            ->with($this->user, $export, $this->callback(function ($data) {
                return $data['status'] === 'processing' && isset($data['started_at']);
            }))
            ->willReturn($updatedExport);

        $result = $this->service->startProcessing($this->user, $export);

        $this->assertSame($updatedExport, $result);
    }

    public function testStartProcessingWithWrongUserThrowsException(): void
    {
        $export = new Export(['id' => 1, 'user_id' => 999, 'status' => 'pending']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Export does not belong to the specified user');

        $this->service->startProcessing($this->user, $export);
    }

    public function testCompleteExport(): void
    {
        $export = new Export(['id' => 1, 'user_id' => 1, 'status' => 'processing']);
        $filePath = '/exports/test.csv';
        $downloadUrl = 'https://example.com/download/test.csv';
        $completedExport = new Export(['id' => 1, 'status' => 'completed']);

        $this->mockExportRepository->expects($this->once())
            ->method('updateForUser')
            ->with($this->user, $export, $this->callback(function ($data) use ($filePath, $downloadUrl) {
                return $data['status'] === 'completed' &&
                       $data['file_path'] === $filePath &&
                       $data['download_url'] === $downloadUrl &&
                       isset($data['completed_at']);
            }))
            ->willReturn($completedExport);

        $result = $this->service->completeExport($this->user, $export, $filePath, $downloadUrl);

        $this->assertSame($completedExport, $result);
    }

    public function testMarkAsFailed(): void
    {
        $export = new Export(['id' => 1, 'user_id' => 1, 'status' => 'processing']);
        $errorMessage = 'Export failed';
        $failedExport = new Export(['id' => 1, 'status' => 'failed']);

        $this->mockExportRepository->expects($this->once())
            ->method('updateForUser')
            ->with($this->user, $export, $this->callback(function ($data) {
                return $data['status'] === 'failed' && isset($data['completed_at']);
            }))
            ->willReturn($failedExport);

        $result = $this->service->markAsFailed($this->user, $export, $errorMessage);

        $this->assertSame($failedExport, $result);
    }

    public function testGetExportStatistics(): void
    {
        $allExports = new Collection([
            new Export(['id' => 1, 'status' => 'completed', 'total_records' => 100, 'format' => 'csv']),
            new Export(['id' => 2, 'status' => 'completed', 'total_records' => 50, 'format' => 'json']),
            new Export(['id' => 3, 'status' => 'failed', 'total_records' => 0, 'format' => 'csv']),
        ]);

        $downloadableExports = new Collection([new Export(['id' => 1])]);
        $recentExports = new Collection([new Export(['id' => 2])]);

        $this->mockExportRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, [])
            ->willReturn($allExports);

        $result = $this->service->getExportStatistics($this->user, $downloadableExports, $recentExports);

        $this->assertSame(3, $result['total_exports']);
        $this->assertSame(2, $result['completed_exports']);
        $this->assertSame(1, $result['downloadable_exports']);
        $this->assertSame(150, $result['total_records_exported']);
        $this->assertArrayHasKey('format_breakdown', $result);
    }

    public function testGenerateExportContent(): void
    {
        $export = new Export([
            'id' => 1,
            'user_id' => 1,
            'format' => 'csv',
            'filters' => ['organization' => 'Acme']
        ]);

        $customer =  new Customer([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '123-456-7890',
            'organization' => 'Acme Corp',
            'job_title' => 'Developer',
            'birthdate' => '1990-01-01',
            'notes' => 'Test notes',
            'user_id' => 1
        ]);

        $customer->setAttribute('id', 1);
        $customer->setAttribute('created_at', now());

        $customers = new Collection([$customer]);

        $this->mockCustomerRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, ['organization' => 'Acme'])
            ->willReturn($customers);

        $result = $this->service->generateExportContent($this->user, $export);

        $this->assertIsString($result);
        $this->assertStringContainsString('Name,Email,Phone', $result); // CSV headers
        $this->assertStringContainsString('John Doe,john@example.com', $result);
    }

    public function testGenerateExportContentWithJsonFormat(): void
    {
        $export = new Export([
            'id' => 1,
            'user_id' => 1,
            'format' => 'json',
            'filters' => []
        ]);

        $customer = new Customer([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'user_id' => 1
        ]);

        $customer->setAttribute('id', 1);
        $customer->setAttribute('created_at', now());

        $customers = new Collection([$customer]);

        $this->mockCustomerRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, [])
            ->willReturn($customers);

        $result = $this->service->generateExportContent($this->user, $export);

        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('customers', $decoded);
        $this->assertArrayHasKey('total', $decoded);
        $this->assertSame(1, $decoded['total']);
    }

    public function testGenerateExportContentWithUnsupportedFormatThrowsException(): void
    {
        $export = new Export([
            'id' => 1,
            'user_id' => 1,
            'format' => 'unsupported'
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported export format: unsupported');

        $this->service->generateExportContent($this->user, $export);
    }

    public function testCleanupExpiredExports(): void
    {
        $this->mockExportRepository->expects($this->once())
            ->method('cleanupExpiredExports')
            ->willReturn(5);

        $result = $this->service->cleanupExpiredExports();

        $this->assertSame(5, $result);
    }

    public function testGetExport(): void
    {
        $exportId = 1;
        $expectedExport = new Export(['id' => $exportId]);

        $this->mockExportRepository->expects($this->once())
            ->method('findForUser')
            ->with($this->user, $exportId)
            ->willReturn($expectedExport);

        $result = $this->service->getExport($this->user, $exportId);

        $this->assertSame($expectedExport, $result);
    }

    public function testGetPaginatedExports(): void
    {
        $perPage = 20;
        $paginatedExports = $this->createMock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class);

        $this->mockExportRepository->expects($this->once())
            ->method('getPaginatedForUser')
            ->with($this->user, [], $perPage)
            ->willReturn($paginatedExports);

        $result = $this->service->getPaginatedExports($this->user, $perPage);

        $this->assertSame($paginatedExports, $result);
    }

    public function testGetRecentExports(): void
    {
        $limit = 5;
        $recentExports = new Collection([new Export(['id' => 1])]);

        $this->mockExportRepository->expects($this->once())
            ->method('getRecentForUser')
            ->with($this->user, $limit)
            ->willReturn($recentExports);

        $result = $this->service->getRecentExports($this->user, $limit);

        $this->assertSame($recentExports, $result);
    }

    public function testFileExists(): void
    {
        $export = new Export(['file_path' => '/exports/test.csv']);

        $this->mockStorage->expects($this->once())
            ->method('exists')
            ->with('/exports/test.csv')
            ->willReturn(true);

        $result = $this->service->fileExists($export);

        $this->assertTrue($result);
    }

    public function testFileExistsWithNullPathReturnsFalse(): void
    {
        $export = new Export(['file_path' => null]);

        $result = $this->service->fileExists($export);

        $this->assertFalse($result);
    }

    public function testDeleteExport(): void
    {
        $export = $this->createPartialMock(Export::class, ['getAttribute', 'delete']);
        $export->method('getAttribute')->with('file_path')->willReturn('/exports/test.csv');
        $export->method('delete')->willReturn(true);

        $this->mockStorage->expects($this->once())
            ->method('exists')
            ->with('/exports/test.csv')
            ->willReturn(true);

        $this->mockStorage->expects($this->once())
            ->method('delete')
            ->with('/exports/test.csv');

        $result = $this->service->deleteExport($export);

        $this->assertTrue($result);
    }

    public function testEscapeCsvField(): void
    {
        $export = new Export([
            'id' => 1,
            'user_id' => 1,
            'format' => 'csv',
            'filters' => []
        ]);

        $customer = new Customer([
            'first_name' => 'John, Jr.',
            'last_name' => 'Doe "The Great"',
            'email' => 'john@example.com',
            'notes' => "Multi\nline\nnotes",
            'user_id' => 1
        ]);
        $customer->setAttribute('id', 1);
        $customer->setAttribute('created_at', now());
        $customers = new Collection([$customer]);

        $this->mockCustomerRepository->expects($this->once())
            ->method('getAllForUser')
            ->willReturn($customers);

        $result = $this->service->generateExportContent($this->user, $export);

        // Test CSV escaping
        $this->assertStringContainsString('John, Jr.', $result);
        $this->assertStringContainsString('Doe ""The Great""', $result);
        $this->assertStringContainsString("Multi\nline\nnote", $result);
    }

    public function testGenerateExportFilename(): void
    {
        $customers = new Collection([new Customer(['user_id' => 1])]);

        $this->mockCustomerRepository->expects($this->once())
            ->method('getAllForUser')
            ->willReturn($customers);

        $this->mockExportRepository->expects($this->once())
            ->method('createForUser')
            ->with($this->user, $this->callback(function ($data) {
                $filename = $data['filename'];
                return str_contains($filename, 'customers_export_TestOrg_') &&
                       str_ends_with($filename, '.csv');
            }))
            ->willReturn(new Export());

        $this->service->createExport($this->user, 'csv', ['organization' => 'Test Org!@#']);
    }
}
