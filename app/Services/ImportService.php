<?php

namespace App\Services;

use App\Contracts\Repositories\ImportRepositoryInterface;
use App\Models\Import;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Service for Import business logic and operations
 */
class ImportService
{
    /**
     * Constructor
     *
     * @param ImportRepositoryInterface $importRepository
     * @param Filesystem $storage
     */
    public function __construct(
        private ImportRepositoryInterface $importRepository,
        private Filesystem $storage
    ) {}

    /**
     * Get import dashboard data for a user
     *
     * @param User $user
     * @return array<string, mixed>
     */
    public function getDashboardData(User $user): array
    {
        $imports = $this->importRepository->getPaginatedForUser($user);
        $recentImports = $this->importRepository->getRecentForUser($user, 5);
        $stats = $this->getImportStatistics($user);

        return [
            'imports' => $imports,
            'recent_imports' => $recentImports,
            'stats' => $stats,
        ];
    }

    /**
     * Create a new import from uploaded file
     *
     * @param User $user
     * @param UploadedFile $file
     * @param array<string, mixed> $options
     * @return Import
     * @throws ValidationException
     */
    public function createImport(User $user, UploadedFile $file, array $options = []): Import
    {
        // Validate file
        $this->validateImportFile($file);
        
        // Store file
        $filePath = $this->storeImportFile($file, $user);
        
        // Create import record
        $importData = [
            'filename' => $this->generateUniqueFilename($file->getClientOriginalName()),
            'original_filename' => $file->getClientOriginalName(),
            'status' => 'pending',
            'file_path' => $filePath,
            'total_rows' => 0,
            'processed_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
        ];

        return $this->importRepository->createForUser($user, $importData);
    }

    /**
     * Start processing an import
     *
     * @param User $user
     * @param Import $import
     * @return Import
     */
    public function startProcessing(User $user, Import $import): Import
    {
        $this->validateUserOwnership($user, $import);

        return $this->importRepository->updateForUser($user, $import, [
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Update import progress
     *
     * @param User $user
     * @param Import $import
     * @param int $processedRows
     * @param int $successfulRows
     * @param int $failedRows
     * @param array<string, mixed>|null $errors
     * @return Import
     */
    public function updateProgress(
        User $user, 
        Import $import, 
        int $processedRows, 
        int $successfulRows, 
        int $failedRows,
        ?array $errors = null
    ): Import {
        $this->validateUserOwnership($user, $import);

        $updateData = [
            'processed_rows' => $processedRows,
            'successful_rows' => $successfulRows,
            'failed_rows' => $failedRows,
        ];

        if ($errors !== null) {
            $updateData['row_errors'] = $errors;
        }

        return $this->importRepository->updateForUser($user, $import, $updateData);
    }

    /**
     * Complete an import
     *
     * @param User $user
     * @param Import $import
     * @param array<string, mixed> $finalStats
     * @return Import
     */
    public function completeImport(User $user, Import $import, array $finalStats = []): Import
    {
        $this->validateUserOwnership($user, $import);

        $updateData = array_merge($finalStats, [
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $this->importRepository->updateForUser($user, $import, $updateData);
    }

    /**
     * Mark import as failed
     *
     * @param User $user
     * @param Import $import
     * @param array<string, mixed> $errors
     * @return Import
     */
    public function markAsFailed(User $user, Import $import, array $errors = []): Import
    {
        $this->validateUserOwnership($user, $import);

        return $this->importRepository->updateForUser($user, $import, [
            'status' => 'failed',
            'validation_errors' => $errors,
            'completed_at' => now(),
        ]);
    }

    /**
     * Get import statistics for a user
     *
     * @param User $user
     * @return array<string, mixed>
     */
    public function getImportStatistics(User $user): array
    {
        $allImports = $this->importRepository->getAllForUser($user);
        $completedImports = $this->importRepository->getCompletedForUser($user);
        $failedImports = $this->importRepository->getFailedForUser($user);

        $totalCustomersImported = $completedImports->sum('successful_rows');
        $totalRowsProcessed = $completedImports->sum('processed_rows');
        $overallSuccessRate = $totalRowsProcessed > 0 
            ? ($totalCustomersImported / $totalRowsProcessed) * 100 
            : 0;

        return [
            'total_imports' => $allImports->count(),
            'completed_imports' => $completedImports->count(),
            'failed_imports' => $failedImports->count(),
            'total_customers_imported' => $totalCustomersImported,
            'overall_success_rate' => round($overallSuccessRate, 2),
            'recent_imports' => $this->importRepository->getRecentForUser($user, 5),
        ];
    }

    /**
     * Get import by ID for user
     *
     * @param User $user
     * @param int $id
     * @return Import|null
     */
    public function getImport(User $user, int $id): ?Import
    {
        return $this->importRepository->findForUser($user, $id);
    }

    /**
     * Get paginated imports for user
     *
     * @param User $user
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedImports(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $this->importRepository->getPaginatedForUser($user, $perPage);
    }

    /**
     * Get recent imports for user
     *
     * @param User $user
     * @param int $limit
     * @return Collection
     */
    public function getRecentImports(User $user, int $limit = 10): Collection
    {
        return $this->importRepository->getRecentForUser($user, $limit);
    }

    /**
     * Cancel import
     *
     * @param Import $import
     * @return bool
     */
    public function cancelImport(Import $import): bool
    {
        if (!in_array($import->status, ['pending', 'processing'])) {
            return false;
        }

        return $import->update(['status' => 'cancelled']);
    }

    /**
     * Delete import and its file
     *
     * @param Import $import
     * @return bool
     */
    public function deleteImport(Import $import): bool
    {
        // Delete file if it exists
        if ($import->file_path && $this->storage->exists($import->file_path)) {
            $this->storage->delete($import->file_path);
        }

        return $import->delete();
    }

    /**
     * Validate import file
     *
     * @param UploadedFile $file
     * @return void
     * @throws ValidationException
     */
    private function validateImportFile(UploadedFile $file): void
    {
        $maxSize = 10 * 1024 * 1024; // 10MB
        $allowedMimes = ['text/csv', 'application/csv', 'text/plain'];
        $allowedExtensions = ['csv', 'txt'];

        if ($file->getSize() > $maxSize) {
            throw ValidationException::withMessages([
                'file' => ['File size cannot exceed 10MB.'],
            ]);
        }

        if (!in_array($file->getMimeType(), $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'file' => ['File must be a CSV file.'],
            ]);
        }

        if (!in_array(strtolower($file->getClientOriginalExtension()), $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'file' => ['File must have a .csv extension.'],
            ]);
        }
    }

    /**
     * Store import file in secure location
     *
     * @param UploadedFile $file
     * @param User $user
     * @return string
     */
    private function storeImportFile(UploadedFile $file, User $user): string
    {
        $directory = "imports/user_{$user->id}/" . now()->format('Y/m');
        $filename = $this->generateUniqueFilename($file->getClientOriginalName());
        
        return $file->storeAs($directory, $filename, 'local');
    }

    /**
     * Generate unique filename
     *
     * @param string $originalName
     * @return string
     */
    private function generateUniqueFilename(string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $timestamp = now()->format('Y_m_d_H_i_s');
        
        return "{$name}_{$timestamp}." . strtolower($extension);
    }

    /**
     * Validate user ownership of import
     *
     * @param User $user
     * @param Import $import
     * @return void
     * @throws \InvalidArgumentException
     */
    private function validateUserOwnership(User $user, Import $import): void
    {
        if ($import->user_id !== $user->id) {
            throw new \InvalidArgumentException('Import does not belong to the specified user');
        }
    }
}