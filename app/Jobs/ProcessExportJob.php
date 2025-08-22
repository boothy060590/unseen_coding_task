<?php

namespace App\Jobs;

use App\Models\Export;
use App\Models\User;
use App\Services\ExportService;
use App\Services\CustomerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Job to process customer exports in the background
 */
class ProcessExportJob implements ShouldQueue
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
        public Export $export,
        public User $user
    ) {
        $this->onQueue('import-export');
    }

    /**
     * Execute the job.
     */
    public function handle(ExportService $exportService, CustomerService $customerService): void
    {
        Log::info('Starting export processing', [
            'export_id' => $this->export->id,
            'user_id' => $this->user->id,
            'format' => $this->export->format
        ]);

        try {
            // Mark export as processing
            $this->export = $exportService->startProcessing($this->user, $this->export);

            // Generate the export file
            $result = $exportService->generateExportFile($this->user, $this->export, $customerService);

            // Mark export as completed
            $exportService->completeExport($this->user, $this->export, $result);

            Log::info('Export processing completed successfully', [
                'export_id' => $this->export->id,
                'total_records' => $result['total_records'],
                'file_size' => $result['file_size'] ?? null,
                'file_path' => $result['file_path'] ?? null
            ]);

        } catch (Exception $e) {
            Log::error('Export processing failed', [
                'export_id' => $this->export->id,
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Mark export as failed
            $exportService->failExport($this->user, $this->export, $e->getMessage());

            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Exception $exception): void
    {
        Log::error('Export job failed permanently', [
            'export_id' => $this->export->id,
            'user_id' => $this->user->id,
            'attempts' => $this->attempts(),
            'error' => $exception?->getMessage()
        ]);

        // Try to mark export as failed if not already done
        try {
            app(ExportService::class)->failExport($this->user, $this->export,
                $exception?->getMessage() ?? 'Job failed after maximum attempts');
        } catch (Exception $e) {
            Log::error('Failed to mark export as failed', [
                'export_id' => $this->export->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
