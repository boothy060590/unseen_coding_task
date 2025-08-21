<?php

namespace App\Services;

use App\Contracts\Repositories\ExportRepositoryInterface;
use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Models\Export;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

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
     */
    public function __construct(
        private ExportRepositoryInterface $exportRepository,
        private CustomerRepositoryInterface $customerRepository
    ) {}

    /**
     * Get export dashboard data for a user
     *
     * @param User $user
     * @return array<string, mixed>
     */
    public function getDashboardData(User $user): array
    {
        $exports = $this->exportRepository->getPaginatedForUser($user);
        $downloadableExports = $this->exportRepository->getDownloadableForUser($user);
        $recentExports = $this->exportRepository->getRecentForUser($user, 5);
        $stats = $this->getExportStatistics($user);

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
     * @param string $filePath
     * @param string $downloadUrl
     * @return Export
     */
    public function completeExport(User $user, Export $export, string $filePath, string $downloadUrl): Export
    {
        $this->validateUserOwnership($user, $export);

        return $this->exportRepository->updateForUser($user, $export, [
            'status' => 'completed',
            'file_path' => $filePath,
            'download_url' => $downloadUrl,
            'completed_at' => now(),
        ]);
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
            'completed_at' => now(),
        ]);
    }

    /**
     * Get export statistics for a user
     *
     * @param User $user
     * @return array<string, mixed>
     */
    public function getExportStatistics(User $user): array
    {
        $allExports = $this->exportRepository->getAllForUser($user);
        $completedExports = $allExports->where('status', 'completed');
        $downloadableExports = $this->exportRepository->getDownloadableForUser($user);

        $totalRecordsExported = $completedExports->sum('total_records');
        
        // Group by format
        $formatStats = $completedExports->groupBy('format')->map(fn($exports) => $exports->count());

        return [
            'total_exports' => $allExports->count(),
            'completed_exports' => $completedExports->count(),
            'downloadable_exports' => $downloadableExports->count(),
            'total_records_exported' => $totalRecordsExported,
            'format_breakdown' => $formatStats,
            'recent_exports' => $this->exportRepository->getRecentForUser($user, 5),
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
            'xlsx' => $this->generateExcelContent($customers),
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
        return $this->exportRepository->getPaginatedForUser($user, $perPage);
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
     * Generate CSV content
     *
     * @param Collection $customers
     * @return string
     */
    private function generateCsvContent(Collection $customers): string
    {
        $headers = ['Name', 'Email', 'Phone', 'Organization', 'Job Title', 'Birthdate', 'Notes', 'Created At'];
        $content = implode(',', $headers) . "\n";
        
        foreach ($customers as $customer) {
            $row = [
                $this->escapeCsvField($customer->full_name),
                $this->escapeCsvField($customer->email),
                $this->escapeCsvField($customer->phone ?? ''),
                $this->escapeCsvField($customer->organization ?? ''),
                $this->escapeCsvField($customer->job_title ?? ''),
                $customer->birthdate?->format('Y-m-d') ?? '',
                $this->escapeCsvField($customer->notes ?? ''),
                $customer->created_at->format('Y-m-d H:i:s'),
            ];
            
            $content .= implode(',', $row) . "\n";
        }
        
        return $content;
    }

    /**
     * Generate JSON content
     *
     * @param Collection $customers
     * @return string
     */
    private function generateJsonContent(Collection $customers): string
    {
        $data = $customers->map(function ($customer) {
            return [
                'name' => $customer->full_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'organization' => $customer->organization,
                'job_title' => $customer->job_title,
                'birthdate' => $customer->birthdate?->format('Y-m-d'),
                'notes' => $customer->notes,
                'created_at' => $customer->created_at->format('Y-m-d H:i:s'),
                'slug' => $customer->slug,
            ];
        });
        
        return json_encode([
            'customers' => $data,
            'total' => $customers->count(),
            'exported_at' => now()->toISOString(),
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Generate Excel content (simplified - would typically use a library like PhpSpreadsheet)
     *
     * @param Collection $customers
     * @return string
     */
    private function generateExcelContent(Collection $customers): string
    {
        // For now, return CSV content - in production, use PhpSpreadsheet
        return $this->generateCsvContent($customers);
    }

    /**
     * Escape CSV field
     *
     * @param string $field
     * @return string
     */
    private function escapeCsvField(string $field): string
    {
        if (str_contains($field, ',') || str_contains($field, '"') || str_contains($field, "\n")) {
            return '"' . str_replace('"', '""', $field) . '"';
        }
        
        return $field;
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