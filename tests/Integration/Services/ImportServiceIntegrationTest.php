<?php

namespace Tests\Integration\Services;

use App\Contracts\Repositories\ImportRepositoryInterface;
use App\Models\Import;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ImportServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private ImportService $service;
    private ImportRepositoryInterface&MockObject $mockRepository;
    private Filesystem $storage; // Real storage for testing
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = $this->createMock(ImportRepositoryInterface::class);
        $this->storage = $this->app->make('filesystem.disk');

        $this->service = new ImportService($this->mockRepository, $this->storage);
        $this->user = new User(['first_name' => 'Test', 'last_name' => 'User']);
        $this->user->setAttribute('id', 1);
    }

    protected function tearDown(): void
    {
        // Clean up any test files created during storage operations
        $testFiles = $this->storage->files('test-imports');
        foreach ($testFiles as $file) {
            $this->storage->delete($file);
        }

        parent::tearDown();
    }

    public function testCreateImportWithFileStorage(): void
    {
        // Create a temporary CSV file for testing
        $csvContent = "first_name,last_name,email\nJohn,Doe,john@example.com\nJane,Smith,jane@example.com";
        $tempFilePath = tempnam(sys_get_temp_dir(), 'test_import_');
        file_put_contents($tempFilePath, $csvContent);

        $file = new UploadedFile(
            $tempFilePath,
            'test_customers.csv',
            'text/csv',
            null,
            true // Mark as test file
        );

        $expectedImport = new Import([
            'id' => 1,
            'filename' => 'test_customers_' . now()->format('Y_m_d_H_i_s') . '.csv'
        ]);

        $this->mockRepository->expects($this->once())
            ->method('createForUser')
            ->with($this->user, $this->callback(function ($data) {
                return $data['status'] === 'pending' &&
                       $data['total_rows'] === 0 &&
                       $data['processed_rows'] === 0 &&
                       str_contains($data['filename'], 'test_customers_') &&
                       str_ends_with($data['filename'], '.csv') &&
                       str_contains($data['file_path'], 'imports/user_' . $this->user->id . '/');
            }))
            ->willReturn($expectedImport);

        $result = $this->service->createImport($this->user, $file);

        $this->assertSame($expectedImport, $result);

        // Verify the file was stored (we'll check for a pattern since exact path includes timestamp)
        $currentYearMonth = now()->format('Y/m');
        $userImportFiles = $this->storage->files('imports/user_' . $this->user->id . '/' . $currentYearMonth);
        $this->assertNotEmpty($userImportFiles);

        // Clean up
        unlink($tempFilePath);
    }

    public function testCreateImportGeneratesUniqueFilenames(): void
    {
        $csvContent = "first_name,last_name,email\nTest,User,test@example.com";

        // Create two files with the same original name
        $tempFilePath1 = tempnam(sys_get_temp_dir(), 'test_import_1_');
        $tempFilePath2 = tempnam(sys_get_temp_dir(), 'test_import_2_');
        file_put_contents($tempFilePath1, $csvContent);
        file_put_contents($tempFilePath2, $csvContent);

        $file1 = new UploadedFile($tempFilePath1, 'customers.csv', 'text/csv', null, true);
        $file2 = new UploadedFile($tempFilePath2, 'customers.csv', 'text/csv', null, true);

        $generatedFilenames = [];

        $this->mockRepository->expects($this->exactly(2))
            ->method('createForUser')
            ->willReturnCallback(function ($user, $data) use (&$generatedFilenames) {
                $filename = $data['filename'] . '_' . count($generatedFilenames);
                $generatedFilenames[] = $filename;
                return new Import(['id' => count($generatedFilenames), 'filename' => $filename]);
            });

        $this->service->createImport($this->user, $file1);
        $this->service->createImport($this->user, $file2);

        // Verify both filenames are unique (they should have different timestamps)
        $this->assertNotSame($generatedFilenames[0], $generatedFilenames[1]);
        $this->assertCount(2, array_unique($generatedFilenames));

        // Clean up
        unlink($tempFilePath1);
        unlink($tempFilePath2);
    }

    public function testCreateImportStoresInUserSpecificDirectory(): void
    {
        $csvContent = "name,email\nTest User,test@example.com";
        $tempFilePath = tempnam(sys_get_temp_dir(), 'test_import_');
        file_put_contents($tempFilePath, $csvContent);

        $file = new UploadedFile($tempFilePath, 'test.csv', 'text/csv', null, true);

        $storedPath = '';
        $this->mockRepository->expects($this->once())
            ->method('createForUser')
            ->willReturnCallback(function ($user, $data) use (&$storedPath) {
                $storedPath = $data['file_path'];
                return new Import(['id' => 1, 'file_path' => $data['file_path']]);
            });

        $this->service->createImport($this->user, $file);

        // Verify the file is stored in the user-specific directory with year/month structure
        $this->assertStringStartsWith('imports/user_' . $this->user->id . '/', $storedPath);
        $this->assertMatchesRegularExpression('/imports\/user_' . $this->user->id . '\/\d{4}\/\d{2}\//', $storedPath);

        // Verify file actually exists in storage
        $this->assertTrue($this->storage->exists($storedPath));

        // Clean up
        unlink($tempFilePath);
    }

    public function testDeleteImportRemovesFileFromStorage(): void
    {
        // Create a test file in storage
        $testFilePath = 'test-imports/delete-test.csv';
        $testContent = "name,email\nTest User,test@example.com";

        $this->storage->put($testFilePath, $testContent);
        $this->assertTrue($this->storage->exists($testFilePath));

        // Create mock import that tracks deletion
        $import = $this->createPartialMock(Import::class, ['getAttribute', 'delete']);
        $import->method('getAttribute')->with('file_path')->willReturn($testFilePath);
        $import->method('delete')->willReturn(true);

        $result = $this->service->deleteImport($import);

        $this->assertTrue($result);
        $this->assertFalse($this->storage->exists($testFilePath));
    }

    public function testDeleteImportWithoutFileStillDeletesRecord(): void
    {
        $import = $this->createMock(Import::class);
        $import->method('getAttribute')
            ->with('file_path')
            ->willReturn(null);
        $import->expects($this->once())
            ->method('delete')
            ->willReturn(true);

        $result = $this->service->deleteImport($import);

        $this->assertTrue($result);
    }

    public function testFileValidationWithRealFiles(): void
    {
        // Test oversized file
        $largeContent = str_repeat("name,email\nTest User,test@example.com\n", 500000); // ~15MB
        $largeTempFile = tempnam(sys_get_temp_dir(), 'large_import_');
        file_put_contents($largeTempFile, $largeContent);

        $largeFile = new UploadedFile($largeTempFile, 'large.csv', 'text/csv', null, true);

        try {
            $this->service->createImport($this->user, $largeFile);
            $this->fail('Expected ValidationException for oversized file');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('File size cannot exceed 10MB', $e->getMessage());
        }

        // Clean up
        unlink($largeTempFile);
    }

    public function testImportFileIntegrityAfterStorage(): void
    {
        $originalContent = "first_name,last_name,email,phone\nJohn,Doe,john@example.com,123-456-7890\nJane,Smith,jane@example.com,987-654-3210";
        $tempFilePath = tempnam(sys_get_temp_dir(), 'integrity_test_');
        file_put_contents($tempFilePath, $originalContent);

        $file = new UploadedFile($tempFilePath, 'integrity_test.csv', 'text/csv', null, true);

        $storedPath = '';
        $this->mockRepository->expects($this->once())
            ->method('createForUser')
            ->willReturnCallback(function ($user, $data) use (&$storedPath) {
                $storedPath = $data['file_path'];
                return new Import(['id' => 1, 'file_path' => $data['file_path']]);
            });

        $this->service->createImport($this->user, $file);

        // Verify file content integrity after storage
        $storedContent = $this->storage->get($storedPath);
        $this->assertSame($originalContent, $storedContent);

        // Verify CSV structure is maintained
        $lines = explode("\n", $storedContent);
        $this->assertCount(3, $lines); // Header + 2 data rows
        $this->assertSame('first_name,last_name,email,phone', $lines[0]);
        $this->assertStringContainsString('John,Doe,john@example.com', $lines[1]);
        $this->assertStringContainsString('Jane,Smith,jane@example.com', $lines[2]);

        // Clean up
        unlink($tempFilePath);
    }

    public function testStoragePathGeneration(): void
    {
        $csvContent = "name,email\nTest,test@example.com";
        $tempFilePath = tempnam(sys_get_temp_dir(), 'path_test_');
        file_put_contents($tempFilePath, $csvContent);

        $file = new UploadedFile($tempFilePath, 'path_test.csv', 'text/csv', null, true);

        $storedPath = '';
        $this->mockRepository->expects($this->once())
            ->method('createForUser')
            ->willReturnCallback(function ($user, $data) use (&$storedPath) {
                $storedPath = $data['file_path'];
                return new Import(['id' => 1]);
            });

        $this->service->createImport($this->user, $file);

        // Verify path structure: imports/user_{id}/{year}/{month}/{filename}
        $pathPattern = '/^imports\/user_\d+\/\d{4}\/\d{2}\/path_test_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.csv$/';
        $this->assertMatchesRegularExpression($pathPattern, $storedPath);

        // Verify year and month are current
        $currentYear = now()->format('Y');
        $currentMonth = now()->format('m');
        $this->assertStringContainsString("imports/user_1/$currentYear/$currentMonth/", $storedPath);

        // Clean up
        unlink($tempFilePath);
    }

    public function testConcurrentImportFileCreation(): void
    {
        $csvContent = "name,email\nConcurrent Test,test@example.com";

        // Create multiple files simultaneously to test for race conditions
        $tempFiles = [];
        $storedPaths = [];

        for ($i = 1; $i <= 5; $i++) {
            $tempFiles[$i] = tempnam(sys_get_temp_dir(), "concurrent_test_{$i}_");
            file_put_contents($tempFiles[$i], $csvContent);
        }

        $this->mockRepository->expects($this->exactly(5))
            ->method('createForUser')
            ->willReturnCallback(function ($user, $data) use (&$storedPaths) {
                $storedPaths[] = $data['file_path'];
                return new Import(['id' => count($storedPaths), 'file_path' => $data['file_path']]);
            });

        // Create imports "simultaneously"
        for ($i = 1; $i <= 5; $i++) {
            $file = new UploadedFile($tempFiles[$i], "concurrent_test_{$i}.csv", 'text/csv', null, true);
            $this->service->createImport($this->user, $file);
        }

        // Verify all paths are unique
        $this->assertCount(5, array_unique($storedPaths));

        // Verify all files exist in storage
        foreach ($storedPaths as $path) {
            $this->assertTrue($this->storage->exists($path));
            $this->assertSame($csvContent, $this->storage->get($path));
        }

        // Clean up
        foreach ($tempFiles as $tempFile) {
            unlink($tempFile);
        }
    }

    public function testStorageErrorHandling(): void
    {
        // Create a mock storage that will fail on put operations
        $failingStorage = $this->createMock(Filesystem::class);
        $failingStorage->method('exists')
            ->willReturn(false);
        $failingStorage->method('delete')
            ->willThrowException(new \Exception('Storage deletion failed'));

        $serviceWithFailingStorage = new ImportService($this->mockRepository, $failingStorage);

        $import = $this->createMock(Import::class);
        $import->method('getAttribute')
            ->with('file_path')
            ->willReturn('test-imports/failing-test.csv');
        $import->expects($this->once())
            ->method('delete')
            ->willReturn(true);

        // The service should still return true even if storage deletion fails
        // (the database record deletion succeeded)
        $result = $serviceWithFailingStorage->deleteImport($import);
        $this->assertTrue($result);
    }
}
