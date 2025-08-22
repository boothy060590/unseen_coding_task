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
        try {
            // Mark import as processing
            $this->import = $importService->startProcessing($this->user, $this->import);

            // Process the import file
            $result = $importService->processImportFile($this->user, $this->import, $customerService);

            // Mark import as completed
            $importService->completeImport($this->user, $this->import, $result);
        } catch (Exception $e) {
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
