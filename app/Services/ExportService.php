<?php

namespace App\Services;

use App\Contracts\Repositories\ExportRepositoryInterface;
use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Models\Export;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service for Export business logic and operations
 */
class ExportService
{
    /**
     * Constructor
     *
     * @param ExportRepositoryInterface $exportRepository
     * @param CustomerRepositoryInterface $customerRepository
     * @param Filesystem $storage
     */
    public function __construct(
        private ExportRepositoryInterface $exportRepository,
        private CustomerRepositoryInterface $customerRepository,
        private Filesystem $storage
    ) {}

    /**
     * Get export dashboard data for a user
     *
     * @param User $user
     * @return array<string, mixed>
     */
    public function getDashboardData(User $user): array
    {
        $exports = $this->exportRepository->getPaginatedForUser($user, []);
        $downloadableExports = $this->exportRepository->getDownloadableForUser($user);
        $recentExports = $this->exportRepository->getRecentForUser($user, 5);
        $stats = $this->getExportStatistics($user, $downloadableExports, $recentExports);

        return [
            'exports' => $exports,
            'downloadable_exports' => $downloadableExports,
            'recent_exports' => $recentExports,
            'stats' => $stats,
        ];
    }

    /**
     * Create a new export request
     *
     * @param User $user
     * @param string $format
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $options
     * @return Export
     */
    public function createExport(
        User $user,
        string $format = 'csv',
        array $filters = [],
        array $options = []
    ): Export {
        // Validate format
        $this->validateExportFormat($format);

        // Count records to be exported
        $customers = $this->customerRepository->getAllForUser($user, $filters);
        $totalRecords = $customers->count();

        // Generate filename
        $filename = $this->generateExportFilename($format, $filters);

        // Calculate expiration (default 7 days)
        $expiresAt = now()->addDays($options['expires_days'] ?? 7);

        $exportData = [
            'filename' => $filename,
            'type' => 'customers',
            'format' => $format,
            'status' => 'pending',
            'filters' => $filters,
            'total_records' => $totalRecords,
            'expires_at' => $expiresAt,
        ];

        return $this->exportRepository->createForUser($user, $exportData);
    }

    /**
     * Start processing an export
     *
     * @param User $user
     * @param Export $export
     * @return Export
     */
    public function startProcessing(User $user, Export $export): Export
    {
        $this->validateUserOwnership($user, $export);

        return $this->exportRepository->updateForUser($user, $export, [
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Complete an export with file details
     *
     * @param User $user
     * @param Export $export
     * @param array<string, mixed> $result
     * @return Export
     */
    public function completeExport(User $user, Export $export, array $result): Export
    {
        $this->validateUserOwnership($user, $export);

        $updateData = [
            'status' => 'completed',
            'file_path' => $result['file_path'],
            'completed_at' => now(),
        ];

        if (isset($result['file_size'])) {
            $updateData['file_size'] = $result['file_size'];
        }

        return $this->exportRepository->updateForUser($user, $export, $updateData);
    }

    /**
     * Mark export as failed
     *
     * @param User $user
     * @param Export $export
     * @param string $errorMessage
     * @return Export
     */
    public function markAsFailed(User $user, Export $export, string $errorMessage = ''): Export
    {
        $this->validateUserOwnership($user, $export);

        return $this->exportRepository->updateForUser($user, $export, [
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    /**
     * Fail export with error message
     *
     * @param User $user
     * @param Export $export
     * @param string $errorMessage
     * @return Export
     */
    public function failExport(User $user, Export $export, string $errorMessage): Export
    {
        return $this->markAsFailed($user, $export, $errorMessage);
    }

    /**
     * Generate export file
     *
     * @param User $user
     * @param Export $export
     * @param CustomerService $customerService
     * @return array<string, mixed>
     * @throws \Exception
     */
    public function generateExportFile(User $user, Export $export, CustomerService $customerService): array
    {
        $this->validateUserOwnership($user, $export);

        // Get customers based on export filters
        $filters = $export->filters ?? [];
        $customers = $this->customerRepository->getAllForUser($user, $filters);

        if ($customers->isEmpty()) {
            throw new \Exception('No customers found to export');
        }

        // Generate filename
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "customers_export_{$timestamp}.{$export->format}";
        $filePath = "exports/{$user->id}/{$filename}";

        // Generate file content based on format
        $content = match ($export->format) {
            'csv' => $this->generateCsvContent($customers),
            'json' => $this->generateJsonContent($customers),
            'xlsx' => $this->generateXlsxContent($customers),
            default => throw new \Exception('Unsupported export format: ' . $export->format)
        };

        // Store the file
        $this->storage->put($filePath, $content);

        // Calculate file size
        $fileSize = strlen($content);

        return [
            'file_path' => $filePath,
            'filename' => $filename,
            'total_records' => $customers->count(),
            'file_size' => $fileSize
        ];
    }

    /**
     * Generate CSV content
     *
     * @param \Illuminate\Database\Eloquent\Collection $customers
     * @return string
     */
    private function generateCsvContent($customers): string
    {
        $output = fopen('php://temp', 'r+');

        // Header
        fputcsv($output, [
            'first_name', 'last_name', 'email', 'phone',
            'organization', 'job_title', 'birthdate', 'notes', 'created_at'
        ]);

        // Data rows
        foreach ($customers as $customer) {
            fputcsv($output, [
                $customer->first_name,
                $customer->last_name,
                $customer->email,
                $customer->phone,
                $customer->organization,
                $customer->job_title,
                $customer->birthdate?->format('Y-m-d'),
                $customer->notes,
                $customer->created_at->format('Y-m-d H:i:s')
            ]);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    /**
     * Generate JSON content
     *
     * @param \Illuminate\Database\Eloquent\Collection $customers
     * @return string
     */
    private function generateJsonContent($customers): string
    {
        $data = $customers->map(function ($customer) {
            return [
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'full_name' => $customer->full_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'organization' => $customer->organization,
                'job_title' => $customer->job_title,
                'birthdate' => $customer->birthdate?->format('Y-m-d'),
                'notes' => $customer->notes,
                'created_at' => $customer->created_at->toISOString(),
                'updated_at' => $customer->updated_at?->toISOString()
            ];
        });

        return json_encode([
            'export_date' => now()->toISOString(),
            'total_records' => $customers->count(),
            'customers' => $data
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Generate XLSX content (simplified - returns CSV for now)
     *
     * @param \Illuminate\Database\Eloquent\Collection $customers
     * @return string
     */
    private function generateXlsxContent($customers): string
    {
        // For now, return CSV content
        // In a real implementation, you'd use a library like PhpSpreadsheet
        return $this->generateCsvContent($customers);
    }

    /**
     * Get export statistics for a user
     *
     * @param User $user
     * @param Collection $downloadableExports
     * @param Collection $recentExports
     * @return array<string, mixed>
     */
    public function getExportStatistics(User $user, Collection $downloadableExports, Collection $recentExports): array
    {
        $allExports = $this->exportRepository->getAllForUser($user, []);
        $completedExports = $allExports->where('status', 'completed');

        $totalRecordsExported = $completedExports->sum('total_records');

        // Group by format
        $formatStats = $completedExports->groupBy('format')->map(fn($exports) => $exports->count());

        return [
            'total_exports' => $allExports->count(),
            'completed_exports' => $completedExports->count(),
            'downloadable_exports' => $downloadableExports->count(),
            'total_records_exported' => $totalRecordsExported,
            'format_breakdown' => $formatStats,
            'recent_exports' => $recentExports,
        ];
    }

    /**
     * Generate export file content
     *
     * @param User $user
     * @param Export $export
     * @return string
     */
    public function generateExportContent(User $user, Export $export): string
    {
        $this->validateUserOwnership($user, $export);

        // Get filtered customers
        $customers = $this->customerRepository->getAllForUser($user, $export->filters ?? []);

        return match($export->format) {
            'csv' => $this->generateCsvContent($customers),
            'json' => $this->generateJsonContent($customers),
            'xlsx' => $this->generateXlsxContent($customers),
            default => throw new \InvalidArgumentException("Unsupported export format: {$export->format}"),
        };
    }

    /**
     * Clean up expired exports
     *
     * @return int Number of cleaned up exports
     */
    public function cleanupExpiredExports(): int
    {
        return $this->exportRepository->cleanupExpiredExports();
    }

    /**
     * Get export by ID for user
     *
     * @param User $user
     * @param int $id
     * @return Export|null
     */
    public function getExport(User $user, int $id): ?Export
    {
        return $this->exportRepository->findForUser($user, $id);
    }

    /**
     * Get paginated exports for user
     *
     * @param User $user
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedExports(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $this->exportRepository->getPaginatedForUser($user, [], $perPage);
    }

    /**
     * Get recent exports for user
     *
     * @param User $user
     * @param int $limit
     * @return Collection
     */
    public function getRecentExports(User $user, int $limit = 10): Collection
    {
        return $this->exportRepository->getRecentForUser($user, $limit);
    }

    /**
     * Get downloadable exports for a user
     *
     * @param User $user
     * @return Collection<int, Export>
     */
    public function getDownloadableExports(User $user): Collection
    {
        return $this->exportRepository->getDownloadableForUser($user);
    }

    /**
     * Check if export file exists
     *
     * @param Export $export
     * @return bool
     */
    public function fileExists(Export $export): bool
    {
        return $export->file_path && $this->storage->exists($export->file_path);
    }

    /**
     * Download export file
     *
     * @param Export $export
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadExport(Export $export): \Symfony\Component\HttpFoundation\Response
    {
        if (!$this->fileExists($export)) {
            abort(404, 'Export file not found');
        }

        $filePath = $export->file_path;
        $fileName = $export->filename ?? basename($filePath);

        // Get file content from storage
        $content = $this->storage->get($filePath);

        // Determine content type based on format
        $contentType = match($export->format) {
            'csv' => 'text/csv',
            'json' => 'application/json',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream'
        };

        return response($content, 200)
            ->header('Content-Type', $contentType)
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
            ->header('Content-Length', strlen($content));
    }

    /**
     * Delete export and its file
     *
     * @param Export $export
     * @return bool
     */
    public function deleteExport(Export $export): bool
    {
        // Delete file if it exists
        if ($this->fileExists($export)) {
            $this->storage->delete($export->file_path);
        }

        return $export->delete();
    }

    /**
     * Validate export format
     *
     * @param string $format
     * @return void
     * @throws \InvalidArgumentException
     */
    private function validateExportFormat(string $format): void
    {
        $allowedFormats = ['csv', 'json', 'xlsx'];

        if (!in_array($format, $allowedFormats, true)) {
            throw new \InvalidArgumentException("Unsupported export format: {$format}");
        }
    }

    /**
     * Generate export filename
     *
     * @param string $format
     * @param array<string, mixed> $filters
     * @return string
     */
    private function generateExportFilename(string $format, array $filters): string
    {
        $timestamp = now()->format('Y_m_d_H_i_s');
        $filterSuffix = '';

        if (!empty($filters['organization'])) {
            $org = preg_replace('/[^a-zA-Z0-9_-]/', '', $filters['organization']);
            $filterSuffix = "_{$org}";
        }

        return "customers_export{$filterSuffix}_{$timestamp}.{$format}";
    }


    /**
     * Validate user ownership of export
     *
     * @param User $user
     * @param Export $export
     * @return void
     * @throws \InvalidArgumentException
     */
    private function validateUserOwnership(User $user, Export $export): void
    {
        if ($export->user_id !== $user->id) {
            throw new \InvalidArgumentException('Export does not belong to the specified user');
        }
    }
}
