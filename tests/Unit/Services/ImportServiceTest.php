<?php

namespace Tests\Unit\Services;

use App\Contracts\Repositories\ImportRepositoryInterface;
use App\Models\Import;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(ImportService::class)]
class ImportServiceTest extends TestCase
{
    private ImportService $service;
    private ImportRepositoryInterface&MockObject $mockRepository;
    private Filesystem&MockObject $mockStorage;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = $this->createMock(ImportRepositoryInterface::class);
        $this->mockStorage = $this->createMock(Filesystem::class);

        $this->service = new ImportService($this->mockRepository, $this->mockStorage);
        $this->user = new User(['first_name' => 'Test', 'last_name' => 'User']);
        $this->user->setAttribute('id', 1);
    }

    public function testGetDashboardData(): void
    {
        $paginatedImports = $this->createMock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class);
        $recentImports = new Collection([new Import(['id' => 1, 'user_id' => 1])]);
        $allImports = new Collection([
            new Import(['status' => 'completed', 'successful_rows' => 100, 'processed_rows' => 110, 'user_id' => 1])
        ]);

        $this->mockRepository->expects($this->once())
            ->method('getPaginatedForUser')
            ->with($this->user, [])
            ->willReturn($paginatedImports);

        $this->mockRepository->expects($this->once())
            ->method('getRecentForUser')
            ->with($this->user, 5)
            ->willReturn($recentImports);

        // For getImportStatistics
        $this->mockRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, [])
            ->willReturn($allImports);

        $this->mockRepository->expects($this->once())
            ->method('getCompletedForUser')
            ->with($this->user)
            ->willReturn($allImports);

        $this->mockRepository->expects($this->once())
            ->method('getFailedForUser')
            ->with($this->user)
            ->willReturn(new Collection());

        $result = $this->service->getDashboardData($this->user);

        $this->assertSame($paginatedImports, $result['imports']);
        $this->assertSame($recentImports, $result['recent_imports']);
        $this->assertArrayHasKey('stats', $result);
    }

    public function testCreateImport(): void
    {
        // Create a real UploadedFile from the fixture
        $uploadedFile = new UploadedFile(
            base_path('tests/fixtures/sample_customers.csv'),
            'customers.csv',
            'text/csv',
            null,
            true
        );

        $expectedImport = new Import(['id' => 1, 'filename' => 'customers_20240101_120000.csv']);

        $this->mockRepository->expects($this->once())
            ->method('createForUser')
            ->with($this->user, $this->callback(function ($data) {
                return $data['status'] === 'pending' &&
                       $data['total_rows'] === 0 &&
                       $data['processed_rows'] === 0 &&
                       str_contains($data['filename'], 'customers_') &&
                       str_ends_with($data['filename'], '.csv');
            }))
            ->willReturn($expectedImport);

        $result = $this->service->createImport($this->user, $uploadedFile);

        $this->assertSame($expectedImport, $result);
    }

    public function testCreateImportWithOversizedFileThrowsException(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(15 * 1024 * 1024); // 15MB (over 10MB limit)

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('File size cannot exceed 10MB.');

        $this->service->createImport($this->user, $file);
    }

    public function testCreateImportWithInvalidMimeTypeThrowsException(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(1000);
        $file->method('getMimeType')->willReturn('image/png');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('File must be a CSV file.');

        $this->service->createImport($this->user, $file);
    }

    public function testCreateImportWithInvalidExtensionThrowsException(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(1000);
        $file->method('getMimeType')->willReturn('text/csv');
        $file->method('getClientOriginalExtension')->willReturn('exe');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('File must have a .csv extension.');

        $this->service->createImport($this->user, $file);
    }

    public function testStartProcessing(): void
    {
        $import = new Import(['id' => 1, 'user_id' => 1, 'status' => 'pending']);
        $updatedImport = new Import(['id' => 1, 'status' => 'processing']);

        $this->mockRepository->expects($this->once())
            ->method('updateForUser')
            ->with($this->user, $import, $this->callback(function ($data) {
                return $data['status'] === 'processing' && isset($data['started_at']);
            }))
            ->willReturn($updatedImport);

        $result = $this->service->startProcessing($this->user, $import);

        $this->assertSame($updatedImport, $result);
    }

    public function testStartProcessingWithWrongUserThrowsException(): void
    {
        $import = new Import(['id' => 1, 'user_id' => 999, 'status' => 'pending']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Import does not belong to the specified user');

        $this->service->startProcessing($this->user, $import);
    }

    public function testUpdateProgress(): void
    {
        $import = new Import(['id' => 1, 'user_id' => 1, 'status' => 'processing']);
        $processedRows = 50;
        $successfulRows = 45;
        $failedRows = 5;
        $errors = ['row_2' => ['Invalid email format']];
        $updatedImport = new Import(['id' => 1, 'processed_rows' => $processedRows]);

        $this->mockRepository->expects($this->once())
            ->method('updateForUser')
            ->with($this->user, $import, $this->callback(function ($data) use ($processedRows, $successfulRows, $failedRows, $errors) {
                return $data['processed_rows'] === $processedRows &&
                       $data['successful_rows'] === $successfulRows &&
                       $data['failed_rows'] === $failedRows &&
                       $data['row_errors'] === $errors;
            }))
            ->willReturn($updatedImport);

        $result = $this->service->updateProgress($this->user, $import, $processedRows, $successfulRows, $failedRows, $errors);

        $this->assertSame($updatedImport, $result);
    }

    public function testUpdateProgressWithoutErrors(): void
    {
        $import = new Import(['id' => 1, 'user_id' => 1, 'status' => 'processing']);
        $updatedImport = new Import(['id' => 1, 'processed_rows' => 10]);

        $this->mockRepository->expects($this->once())
            ->method('updateForUser')
            ->with($this->user, $import, $this->callback(function ($data) {
                return !isset($data['row_errors']);
            }))
            ->willReturn($updatedImport);

        $result = $this->service->updateProgress($this->user, $import, 10, 8, 2);

        $this->assertSame($updatedImport, $result);
    }

    public function testCompleteImport(): void
    {
        $import = new Import(['id' => 1, 'user_id' => 1, 'status' => 'processing']);
        $finalStats = [
            'processed_rows' => 100,
            'successful_rows' => 95,
            'failed_rows' => 5,
            'errors' => ['Row 5: Invalid email format']
        ];
        $completedImport = new Import(['id' => 1, 'status' => 'completed']);

        $this->mockRepository->expects($this->once())
            ->method('updateForUser')
            ->with($this->user, $import, $this->callback(function ($data) use ($finalStats) {
                return $data['status'] === 'completed' &&
                       $data['processed_rows'] === 100 &&
                       $data['successful_rows'] === 95 &&
                       $data['failed_rows'] === 5 &&
                       isset($data['completed_at']);
            }))
            ->willReturn($completedImport);

        $result = $this->service->completeImport($this->user, $import, $finalStats);

        $this->assertSame($completedImport, $result);
    }

    public function testMarkAsFailed(): void
    {
        $import = new Import(['id' => 1, 'user_id' => 1, 'status' => 'processing']);
        $errors = ['file' => ['Invalid CSV format']];
        $failedImport = new Import(['id' => 1, 'status' => 'failed']);

        $this->mockRepository->expects($this->once())
            ->method('updateForUser')
            ->with($this->user, $import, $this->callback(function ($data) use ($errors) {
                return $data['status'] === 'failed' &&
                       $data['validation_errors'] === $errors &&
                       isset($data['completed_at']);
            }))
            ->willReturn($failedImport);

        $result = $this->service->markAsFailed($this->user, $import, $errors);

        $this->assertSame($failedImport, $result);
    }

    public function testGetImportStatistics(): void
    {
        $allImports = new Collection([
            new Import(['status' => 'completed', 'successful_rows' => 100, 'processed_rows' => 110, 'user_id' => 1]),
            new Import(['status' => 'completed', 'successful_rows' => 50, 'processed_rows' => 60, 'user_id' => 1]),
            new Import(['status' => 'failed', 'user_id' => 1]),
        ]);

        $completedImports = new Collection([
            new Import(['successful_rows' => 100, 'processed_rows' => 110, 'user_id' => 1]),
            new Import(['successful_rows' => 50, 'processed_rows' => 60, 'user_id' => 1]),
        ]);

        $failedImports = new Collection([
            new Import(['status' => 'failed', 'user_id' => 1]),
        ]);

        $recentImports = new Collection([new Import(['id' => 1, 'user_id' => 1])]);

        $this->mockRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, [])
            ->willReturn($allImports);

        $this->mockRepository->expects($this->once())
            ->method('getCompletedForUser')
            ->with($this->user)
            ->willReturn($completedImports);

        $this->mockRepository->expects($this->once())
            ->method('getFailedForUser')
            ->with($this->user)
            ->willReturn($failedImports);

        $result = $this->service->getImportStatistics($this->user, $recentImports);

        $this->assertSame(3, $result['total_imports']);
        $this->assertSame(2, $result['completed_imports']);
        $this->assertSame(1, $result['failed_imports']);
        $this->assertSame(150, $result['total_customers_imported']); // 100 + 50
        $this->assertSame(88.24, $result['overall_success_rate']); // 150/170 * 100 rounded
        $this->assertSame($recentImports, $result['recent_imports']);
    }

    public function testGetImportStatisticsWithNoImports(): void
    {
        $emptyCollection = new Collection();

        $this->mockRepository->expects($this->once())
            ->method('getAllForUser')
            ->willReturn($emptyCollection);

        $this->mockRepository->expects($this->once())
            ->method('getCompletedForUser')
            ->willReturn($emptyCollection);

        $this->mockRepository->expects($this->once())
            ->method('getFailedForUser')
            ->willReturn($emptyCollection);

        $result = $this->service->getImportStatistics($this->user, $emptyCollection);

        $this->assertSame(0, $result['total_imports']);
        $this->assertSame(0.0, $result['overall_success_rate']);
    }

    public function testGetImport(): void
    {
        $importId = 1;
        $expectedImport = new Import(['id' => $importId, 'user_id' => 1]);

        $this->mockRepository->expects($this->once())
            ->method('findForUser')
            ->with($this->user, $importId)
            ->willReturn($expectedImport);

        $result = $this->service->getImport($this->user, $importId);

        $this->assertSame($expectedImport, $result);
    }

    public function testGetPaginatedImports(): void
    {
        $perPage = 20;
        $paginatedImports = $this->createMock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class);

        $this->mockRepository->expects($this->once())
            ->method('getPaginatedForUser')
            ->with($this->user, [], $perPage)
            ->willReturn($paginatedImports);

        $result = $this->service->getPaginatedImports($this->user, $perPage);

        $this->assertSame($paginatedImports, $result);
    }

    public function testGetRecentImports(): void
    {
        $limit = 5;
        $recentImports = new Collection([new Import(['id' => 1, 'user_id' => 1])]);

        $this->mockRepository->expects($this->once())
            ->method('getRecentForUser')
            ->with($this->user, $limit)
            ->willReturn($recentImports);

        $result = $this->service->getRecentImports($this->user, $limit);

        $this->assertSame($recentImports, $result);
    }

    public function testCancelImport(): void
    {
        $import = $this->createPartialMock(Import::class, ['getAttribute', 'update']);
        $import->method('getAttribute')->with('status')->willReturn('pending');
        $import->method('update')->willReturn(true);

        $result = $this->service->cancelImport($import);

        $this->assertTrue($result);
    }

    public function testCancelImportWithCompletedStatusReturnsFalse(): void
    {
        $import = $this->createMock(Import::class);
        $import->method('getAttribute')->with('status')->willReturn('completed');

        $result = $this->service->cancelImport($import);

        $this->assertFalse($result);
    }

    public function testDeleteImport(): void
    {
        $import = $this->createPartialMock(Import::class, ['getAttribute', 'delete']);
        $import->method('getAttribute')->with('file_path')->willReturn('/imports/test.csv');
        $import->method('delete')->willReturn(true);

        $this->mockStorage->expects($this->once())
            ->method('exists')
            ->with('/imports/test.csv')
            ->willReturn(true);

        $this->mockStorage->expects($this->once())
            ->method('delete')
            ->with('/imports/test.csv');

        $result = $this->service->deleteImport($import);

        $this->assertTrue($result);
    }

    public function testDeleteImportWithoutFile(): void
    {
        $import = $this->createMock(Import::class);
        $import->method('getAttribute')->with('file_path')->willReturn(null);
        $import->expects($this->once())->method('delete')->willReturn(true);

        $this->mockStorage->expects($this->never())->method('exists');
        $this->mockStorage->expects($this->never())->method('delete');

        $result = $this->service->deleteImport($import);

        $this->assertTrue($result);
    }

    public function testGenerateUniqueFilename(): void
    {
        // Create a real UploadedFile from the fixture
        $file = new \Illuminate\Http\UploadedFile(
            base_path('tests/fixtures/sample_customers.csv'),
            'test file.csv',
            'text/csv',
            null,
            true
        );

        $this->mockRepository->expects($this->once())
            ->method('createForUser')
            ->with($this->user, $this->callback(function ($data) {
                $filename = $data['filename'];
                return str_contains($filename, 'test file_') &&
                       str_ends_with($filename, '.csv') &&
                       preg_match('/\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}/', $filename);
            }))
            ->willReturn(new Import());

        $this->service->createImport($this->user, $file);
    }

    public function testStoreImportFileInUserDirectory(): void
    {
        // Create a real UploadedFile from the fixture
        $file = new \Illuminate\Http\UploadedFile(
            base_path('tests/fixtures/sample_customers.csv'),
            'customers.csv',
            'text/csv',
            null,
            true
        );

        $this->mockRepository->expects($this->once())
            ->method('createForUser')
            ->willReturn(new Import());

        $result = $this->service->createImport($this->user, $file);

        $this->assertInstanceOf(Import::class, $result);
    }
}
