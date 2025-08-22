<?php

namespace Tests\Integration\Services;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\ExportRepositoryInterface;
use App\Models\Customer;
use App\Models\Export;
use App\Models\User;
use App\Services\ExportService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ExportServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private ExportService $service;
    private ExportRepositoryInterface&MockObject $mockExportRepository;
    private CustomerRepositoryInterface&MockObject $mockCustomerRepository;
    private Filesystem $storage; // Real storage for testing
    private User $user;

    /**
     * @throws BindingResolutionException
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->mockExportRepository = $this->createMock(ExportRepositoryInterface::class);
        $this->mockCustomerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->storage = $this->app->make('filesystem.disk', ['local']); // Use real local storage

        $this->service = new ExportService(
            $this->mockExportRepository,
            $this->mockCustomerRepository,
            $this->storage
        );

        $this->user = new User(['first_name' => 'Test', 'last_name' => 'User']);
        $this->user->setAttribute('id', 1);
    }

    protected function tearDown(): void
    {
        // Clean up any test files created during storage operations
        $testFiles = $this->storage->files('test-exports');
        foreach ($testFiles as $file) {
            $this->storage->delete($file);
        }

        parent::tearDown();
    }

    public function testGenerateExportContentCreatesValidCsvFile(): void
    {
        $export = new Export([
            'id' => 1,
            'user_id' => 1,
            'format' => 'csv',
            'filters' => ['organization' => 'Acme']
        ]);

        $customer = new Customer([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '123-456-7890',
            'organization' => 'Acme Corp',
            'job_title' => 'Developer',
            'birthdate' => '1990-01-01',
            'notes' => 'Test customer with "quotes" and, commas',
        ]);

        $customer->setAttribute('id', 1);
        $customer->setAttribute('created_at', now());

        $customers = new Collection([$customer]);

        $this->mockCustomerRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, ['organization' => 'Acme'])
            ->willReturn($customers);

        $result = $this->service->generateExportContent($this->user, $export);

        // Test CSV structure and content
        $lines = explode("\n", trim($result));
        $this->assertGreaterThanOrEqual(2, count($lines)); // Header + at least one data row

        // Test header
        $header = str_getcsv($lines[0]);
        $this->assertContains('first_name', $header);
        $this->assertContains('last_name', $header);
        $this->assertContains('email', $header);
        $this->assertContains('phone', $header);

        // Test data row
        $dataRow = str_getcsv($lines[1]);
        $this->assertContains('John', $dataRow);
        $this->assertContains('Doe', $dataRow);
        $this->assertContains('john@example.com', $dataRow);
        $this->assertContains('123-456-7890', $dataRow);
        $this->assertContains('Acme Corp', $dataRow);

        // Test CSV escaping of special characters
        $this->assertStringContainsString('"Test customer with ""quotes"" and, commas"', $result);
    }

    public function testGenerateExportContentCreatesValidJsonFile(): void
    {
        $export = new Export([
            'id' => 1,
            'user_id' => 1,
            'format' => 'json',
            'filters' => []
        ]);

        $customer = new Customer([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'phone' => '987-654-3210',
            'organization' => 'Beta Inc',
        ]);

        $customer->setAttribute('id', 1);
        $customer->setAttribute('created_at', now());

        $customers = new Collection([$customer]);

        $this->mockCustomerRepository->expects($this->once())
            ->method('getAllForUser')
            ->with($this->user, [])
            ->willReturn($customers);

        $result = $this->service->generateExportContent($this->user, $export);

        // Test JSON structure
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('customers', $decoded);
        $this->assertArrayHasKey('total_records', $decoded);
        $this->assertArrayHasKey('export_date', $decoded);

        $this->assertSame(1, $decoded['total_records']);
        $this->assertCount(1, $decoded['customers']);

        $customer = $decoded['customers'][0];
        $this->assertSame('Jane', $customer['first_name']);
        $this->assertSame('Smith', $customer['last_name']);
        $this->assertSame('jane@example.com', $customer['email']);
        $this->assertSame('Beta Inc', $customer['organization']);
    }

    public function testFileExistsWithRealStorage(): void
    {
        // Create a test file in storage
        $testFilePath = 'test-exports/test-export.csv';
        $testContent = "Name,Email\nJohn Doe,john@example.com";

        $this->storage->put($testFilePath, $testContent);

        $export = new Export(['file_path' => $testFilePath]);

        $this->assertTrue($this->service->fileExists($export));

        // Test with non-existent file
        $nonExistentExport = new Export(['file_path' => 'test-exports/non-existent.csv']);
        $this->assertFalse($this->service->fileExists($nonExistentExport));

        // Test with null file path
        $nullPathExport = new Export(['file_path' => null]);
        $this->assertFalse($this->service->fileExists($nullPathExport));
    }

    public function testDeleteExportRemovesFileFromStorage(): void
    {
        // Create a test file in storage
        $testFilePath = 'test-exports/delete-test.csv';
        $testContent = "Name,Email\nTest User,test@example.com";

        $this->storage->put($testFilePath, $testContent);
        $this->assertTrue($this->storage->exists($testFilePath));

        $export = $this->createPartialMock(Export::class, ['getAttribute', 'delete']);
        $export->method('getAttribute')->with('file_path')->willReturn($testFilePath);
        $export->method('delete')->willReturn(true);

        $result = $this->service->deleteExport($export);

        $this->assertTrue($result);
        $this->assertFalse($this->storage->exists($testFilePath));
    }

    public function testDeleteExportWithoutFileStillDeletesRecord(): void
    {
        $export = $this->createMock(Export::class);
        $export->method('getAttribute')
            ->with('file_path')
            ->willReturn(null);
        $export->expects($this->once())
            ->method('delete')
            ->willReturn(true);

        $result = $this->service->deleteExport($export);

        $this->assertTrue($result);
    }

    public function testCompleteExportWithStorageIntegration(): void
    {
        $export = new Export([
            'id' => 1,
            'user_id' => 1,
            'status' => 'processing'
        ]);

        $testFilePath = 'test-exports/completed-export.json';
        $testContent = '{"test": "data"}';

        // Simulate storage operation
        $this->storage->put($testFilePath, $testContent);
        $downloadUrl = 'https://example.com/download/completed-export.json';

        $completedExport = new Export([
            'id' => 1,
            'status' => 'completed',
            'file_path' => $testFilePath,
            'download_url' => $downloadUrl
        ]);

        $this->mockExportRepository->expects($this->once())
            ->method('updateForUser')
            ->with($this->user, $export, $this->callback(function ($data) use ($testFilePath, $downloadUrl) {
                return $data['status'] === 'completed' &&
                       $data['file_path'] === $testFilePath &&
                       isset($data['completed_at']);
            }))
            ->willReturn($completedExport);

        $result = $this->service->completeExport($this->user, $export, [
            'file_path' => $testFilePath,
            'download_url' => $downloadUrl
        ]);

        $this->assertSame($completedExport, $result);
        $this->assertTrue($this->storage->exists($testFilePath));
        $this->assertSame($testContent, $this->storage->get($testFilePath));
    }

    public function testExportFilenameGeneration(): void
    {
        $customers = new Collection([new Customer(['id' => 1])]);

        $this->mockCustomerRepository->expects($this->once())
            ->method('getAllForUser')
            ->willReturn($customers);

        $this->mockExportRepository->expects($this->once())
            ->method('createForUser')
            ->with($this->user, $this->callback(function ($data) {
                $filename = $data['filename'];

                // Test filename format: customers_export_FilterValue_YYYY_MM_DD_HH_II_SS.format
                $this->assertStringStartsWith('customers_export_TestOrg_', $filename);
                $this->assertStringEndsWith('.csv', $filename);
                $this->assertMatchesRegularExpression('/\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}/', $filename);

                return true;
            }))
            ->willReturn(new Export());

        $this->service->createExport($this->user, 'csv', ['organization' => 'Test Org!@#']);
    }

    public function testLargeExportContentHandling(): void
    {
        $export = new Export([
            'id' => 1,
            'user_id' => 1,
            'format' => 'csv',
            'filters' => []
        ]);

        // Create a large collection of customers to test memory handling
        $customers = new Collection();
        for ($i = 1; $i <= 1000; $i++) {
            $customer = new Customer([
                'first_name' => "Customer{$i}",
                'last_name' => 'Test',
                'email' => "customer{$i}@example.com",
                'phone' => '123-456-7890',
                'organization' => 'Test Corp',
            ]);

            $customer->setAttribute('id', $i);
            $customer->setAttribute('created_at', now());
            $customers->push($customer);
        }

        $this->mockCustomerRepository->expects($this->once())
            ->method('getAllForUser')
            ->willReturn($customers);

        $result = $this->service->generateExportContent($this->user, $export);

        // Verify the export contains all records
        $lines = explode("\n", trim($result));
        $this->assertSame(1001, count($lines)); // Header + 1000 data rows

        // Verify header
        $this->assertStringStartsWith('first_name,last_name,email,phone,organization', $lines[0]);

        // Verify a few sample rows
        $this->assertStringContainsString('Customer1,Test,customer1@example.com', $lines[1]);
        $this->assertStringContainsString('Customer500,Test,customer500@example.com', $lines[500]);
        $this->assertStringContainsString('Customer1000,Test,customer1000@example.com', $lines[1000]);
    }

    public function testStorageErrorHandling(): void
    {
        // Create a mock storage that will fail
        $failingStorage = $this->createMock(Filesystem::class);
        $failingStorage->method('exists')
            ->willThrowException(new \Exception('Storage connection failed'));

        $serviceWithFailingStorage = new ExportService(
            $this->mockExportRepository,
            $this->mockCustomerRepository,
            $failingStorage
        );

        $export = new Export(['file_path' => 'test.csv']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Storage connection failed');

        $serviceWithFailingStorage->fileExists($export);
    }
}
