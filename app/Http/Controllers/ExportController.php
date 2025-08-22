<?php

namespace App\Http\Controllers;

use App\Models\Export;
use App\Services\ExportService;
use App\Services\SearchService;
use App\Http\Requests\ExportCustomersRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ExportController extends Controller
{
    public function __construct(
        private ExportService $exportService,
        private SearchService $searchService
    ) {}

    /**
     * Display export dashboard
     */
    public function index(): View
    {
        $user = auth()->user();
        
        $exports = $this->exportService->getPaginatedExports($user, 15);
        $recentExports = $this->exportService->getRecentExports($user, 10);
        $downloadableExports = $this->exportService->getDownloadableExports($user);
        $exportStats = $this->exportService->getExportStatistics($user, $downloadableExports, $recentExports);

        return view('exports.index', compact('exports', 'recentExports', 'downloadableExports', 'exportStats'));
    }

    /**
     * Show export form
     */
    public function create(Request $request): View
    {
        $user = auth()->user();
        $filters = $request->only(['search', 'organization', 'job_title', 'created_from', 'created_to']);
        
        // Get preview of customers that would be exported
        $previewCustomers = $this->searchService->searchCustomers($user, $filters, 5);
        $totalCount = $this->searchService->getSearchStatistics($user, $filters)['total_results'] ?? 0;

        return view('exports.create', compact(
            'previewCustomers',
            'totalCount',
            'filters'
        ));
    }

    /**
     * Create export
     */
    public function store(ExportCustomersRequest $request): RedirectResponse
    {
        try {
            $export = $this->exportService->createExport(
                auth()->user(),
                $request->get('format', 'csv'),
                $request->get('filters', []),
                [
                    'include_notes' => $request->boolean('include_notes', false),
                    'include_audit_trail' => $request->boolean('include_audit_trail', false)
                ]
            );

            return redirect()
                ->route('exports.show', $export->id)
                ->with('success', 'Export started successfully. Generating file...');

        } catch (\Exception $e) {
            return back()
                ->with('error', 'Failed to start export: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show export status
     */
    public function show(Export $export): View
    {
        $export->load('user');
        
        return view('exports.show', compact('export'));
    }

    /**
     * Download export file
     */
    public function download(Export $export): Response
    {
        if ($export->status !== 'completed') {
            abort(404, 'Export not ready for download');
        }

        if (!$export->file_path || !$this->exportService->fileExists($export)) {
            abort(404, 'Export file not found');
        }

        return $this->exportService->downloadExport($export);
    }

    /**
     * Get export progress via AJAX
     */
    public function progress(Export $export)
    {
        return response()->json([
            'status' => $export->status,
            'progress' => $export->progress,
            'file_size' => $export->file_size,
            'download_url' => $export->status === 'completed' 
                ? route('exports.download', $export->id)
                : null,
            'error_message' => $export->error_message,
            'completed_at' => $export->completed_at?->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Delete export record
     */
    public function destroy(Export $export): RedirectResponse
    {
        $this->exportService->deleteExport($export);

        return redirect()
            ->route('exports.index')
            ->with('success', 'Export deleted successfully');
    }
}