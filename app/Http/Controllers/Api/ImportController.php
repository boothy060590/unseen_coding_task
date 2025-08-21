<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Import;
use App\Services\ImportService;
use App\Http\Requests\ImportCustomersRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ImportController extends Controller
{
    public function __construct(
        private ImportService $importService
    ) {}

    /**
     * Display a listing of imports
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min($request->get('per_page', 15), 100);
        
        $imports = $this->importService->getPaginatedImports($user, $perPage);
        
        return response()->json([
            'data' => $imports->items(),
            'meta' => [
                'current_page' => $imports->currentPage(),
                'per_page' => $imports->perPage(),
                'total' => $imports->total(),
                'last_page' => $imports->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created import
     */
    public function store(ImportCustomersRequest $request): JsonResponse
    {
        try {
            $import = $this->importService->createImport(
                $request->user(),
                $request->file('file'),
                [
                    'has_headers' => $request->boolean('has_headers', true),
                    'delimiter' => $request->input('delimiter', ','),
                    'encoding' => $request->input('encoding', 'UTF-8'),
                    'source' => 'api_upload'
                ]
            );

            return response()->json([
                'message' => 'Import started successfully',
                'data' => $import,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to start import',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified import
     */
    public function show(Import $import): JsonResponse
    {
        return response()->json([
            'data' => $import,
        ]);
    }

    /**
     * Remove the specified import
     */
    public function destroy(Import $import): JsonResponse
    {
        $this->importService->deleteImport($import);

        return response()->json([
            'message' => 'Import deleted successfully',
        ]);
    }

    /**
     * Get import progress
     */
    public function progress(Import $import): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $import->id,
                'status' => $import->status,
                'progress' => $import->progress,
                'processed_rows' => $import->processed_rows,
                'successful_rows' => $import->successful_rows,
                'failed_rows' => $import->failed_rows,
                'total_rows' => $import->total_rows,
                'error_message' => $import->error_message,
                'started_at' => $import->started_at?->toISOString(),
                'completed_at' => $import->completed_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Cancel import
     */
    public function cancel(Import $import): JsonResponse
    {
        if (!in_array($import->status, ['pending', 'processing'])) {
            return response()->json([
                'message' => 'Cannot cancel import in current status',
                'current_status' => $import->status,
            ], 400);
        }

        $cancelled = $this->importService->cancelImport($import);

        if ($cancelled) {
            return response()->json([
                'message' => 'Import cancelled successfully',
                'data' => $import->fresh(),
            ]);
        }

        return response()->json([
            'message' => 'Failed to cancel import',
        ], 500);
    }

    /**
     * Get import statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();
        $statistics = $this->importService->getImportStatistics($user);

        return response()->json([
            'data' => $statistics,
        ]);
    }

    /**
     * Get recent imports
     */
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min($request->get('limit', 10), 50);
        
        $imports = $this->importService->getRecentImports($user, $limit);

        return response()->json([
            'data' => $imports->map(function($import) {
                return [
                    'id' => $import->id,
                    'filename' => $import->filename,
                    'status' => $import->status,
                    'total_rows' => $import->total_rows,
                    'successful_rows' => $import->successful_rows,
                    'created_at' => $import->created_at->toISOString(),
                    'completed_at' => $import->completed_at?->toISOString(),
                ];
            }),
            'count' => $imports->count(),
        ]);
    }
}