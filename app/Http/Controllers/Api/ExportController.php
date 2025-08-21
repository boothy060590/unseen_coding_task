<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Export;
use App\Services\ExportService;
use App\Services\SearchService;
use App\Http\Requests\ExportCustomersRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ExportController extends Controller
{
    public function __construct(
        private ExportService $exportService,
        private SearchService $searchService
    ) {}

    /**
     * Display a listing of exports
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min($request->get('per_page', 15), 100);
        
        $exports = $this->exportService->getPaginatedExports($user, $perPage);
        
        return response()->json([
            'data' => $exports->items(),
            'meta' => [
                'current_page' => $exports->currentPage(),
                'per_page' => $exports->perPage(),
                'total' => $exports->total(),
                'last_page' => $exports->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created export
     */
    public function store(ExportCustomersRequest $request): JsonResponse
    {
        try {
            $export = $this->exportService->createExport(
                $request->user(),
                $request->get('format', 'csv'),
                $request->get('filters', []),
                [
                    'include_notes' => $request->boolean('include_notes', false),
                    'include_audit_trail' => $request->boolean('include_audit_trail', false),
                    'source' => 'api'
                ]
            );

            return response()->json([
                'message' => 'Export started successfully',
                'data' => $export,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to start export',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified export
     */
    public function show(Export $export): JsonResponse
    {
        return response()->json([
            'data' => $export,
        ]);
    }

    /**
     * Remove the specified export
     */
    public function destroy(Export $export): JsonResponse
    {
        $this->exportService->deleteExport($export);

        return response()->json([
            'message' => 'Export deleted successfully',
        ]);
    }

    /**
     * Download export file
     */
    public function download(Export $export): Response|JsonResponse
    {
        if ($export->status !== 'completed') {
            return response()->json([
                'message' => 'Export not ready for download',
                'current_status' => $export->status,
            ], 400);
        }

        if (!$export->file_path || !$this->exportService->fileExists($export)) {
            return response()->json([
                'message' => 'Export file not found',
            ], 404);
        }

        return $this->exportService->downloadExport($export);
    }

    /**
     * Get export progress
     */
    public function progress(Export $export): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $export->id,
                'status' => $export->status,
                'progress' => $export->progress,
                'file_size' => $export->file_size,
                'total_records' => $export->total_records,
                'format' => $export->format,
                'download_url' => $export->status === 'completed' 
                    ? route('api.exports.download', $export->id)
                    : null,
                'error_message' => $export->error_message,
                'started_at' => $export->started_at?->toISOString(),
                'completed_at' => $export->completed_at?->toISOString(),
                'expires_at' => $export->expires_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Get export statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();
        $statistics = $this->exportService->getExportStatistics($user);

        return response()->json([
            'data' => $statistics,
        ]);
    }

    /**
     * Get recent exports
     */
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min($request->get('limit', 10), 50);
        
        $exports = $this->exportService->getRecentExports($user, $limit);

        return response()->json([
            'data' => $exports->map(function($export) {
                return [
                    'id' => $export->id,
                    'filename' => $export->filename,
                    'format' => $export->format,
                    'status' => $export->status,
                    'total_records' => $export->total_records,
                    'file_size' => $export->file_size,
                    'download_url' => $export->status === 'completed' 
                        ? route('api.exports.download', $export->id)
                        : null,
                    'created_at' => $export->created_at->toISOString(),
                    'completed_at' => $export->completed_at?->toISOString(),
                    'expires_at' => $export->expires_at?->toISOString(),
                ];
            }),
            'count' => $exports->count(),
        ]);
    }

    /**
     * Preview export data
     */
    public function preview(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['search', 'organization', 'job_title', 'created_from', 'created_to']);
        $limit = min($request->get('limit', 5), 20);
        
        $previewCustomers = $this->searchService->searchCustomers($user, $filters, $limit);
        $statistics = $this->searchService->getSearchStatistics($user, $filters);

        return response()->json([
            'preview' => $previewCustomers->items(),
            'total_count' => $statistics['total_results'] ?? 0,
            'filters_applied' => $filters,
        ]);
    }
}