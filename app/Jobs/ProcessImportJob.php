<?php

namespace App\Jobs;

use App\Models\Import;
use App\Models\User;
use App\Services\ImportService;
use App\Services\CustomerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Job to process customer imports in the background
 */
class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Import $import,
        public User $user
    ) {
        $this->onQueue('import-export');
    }

    /**
     * Execute the job.
     */
    public function handle(ImportService $importService, CustomerService $customerService): void
    {
        Log::info('Starting import processing', [
            'import_id' => $this->import->id,
            'user_id' => $this->user->id,
            'filename' => $this->import->original_filename
        ]);

        try {
            // Mark import as processing
            $this->import = $importService->startProcessing($this->user, $this->import);

            // Process the import file
            $result = $importService->processImportFile($this->user, $this->import, $customerService);

            // Mark import as completed
            $importService->completeImport($this->user, $this->import, $result);

            Log::info('Import processing completed successfully', [
                'import_id' => $this->import->id,
                'processed_rows' => $result['processed_rows'],
                'successful_rows' => $result['successful_rows'],
                'failed_rows' => $result['failed_rows']
            ]);

        } catch (Exception $e) {
            Log::error('Import processing failed', [
                'import_id' => $this->import->id,
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Mark import as failed
            $importService->failImport($this->user, $this->import, $e->getMessage());

            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Exception $exception): void
    {
        Log::error('Import job failed permanently', [
            'import_id' => $this->import->id,
            'user_id' => $this->user->id,
            'attempts' => $this->attempts(),
            'error' => $exception?->getMessage()
        ]);

        // Try to mark import as failed if not already done
        try {
            app(ImportService::class)->failImport($this->user, $this->import,
                $exception?->getMessage() ?? 'Job failed after maximum attempts');
        } catch (Exception $e) {
            Log::error('Failed to mark import as failed', [
                'import_id' => $this->import->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
