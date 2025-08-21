<?php

namespace App\Http\Controllers;

use App\Models\Import;
use App\Models\Export;
use App\Services\ImportService;
use App\Services\ExportService;
use App\Services\SearchService;
use App\Http\Requests\ImportCustomersRequest;
use App\Http\Requests\ExportCustomersRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class ImportExportController extends Controller
{
    public function __construct(
        private ImportService $importService,
        private ExportService $exportService,
        private SearchService $searchService
    ) {}

    /**
     * Display import/export dashboard
     */
    public function index(): View
    {
        $user = auth()->user();
        
        $recentImports = $this->importService->getRecentImports($user, 10);
        $recentExports = $this->exportService->getRecentExports($user, 10);
        $importStats = $this->importService->getImportStatistics($user);
        $exportStats = $this->exportService->getExportStatistics($user);

        return view('import-export.index', compact(
            'recentImports',
            'recentExports', 
            'importStats',
            'exportStats'
        ));
    }

    /**
     * Show import form
     */
    public function showImport(): View
    {
        return view('import-export.import');
    }

    /**
     * Handle file upload and create import
     */
    public function import(ImportCustomersRequest $request): RedirectResponse
    {
        try {
            $import = $this->importService->createImport(
                auth()->user(),
                $request->file('file'),
                [
                    'has_headers' => $request->boolean('has_headers', true),
                    'delimiter' => $request->input('delimiter', ','),
                    'encoding' => $request->input('encoding', 'UTF-8'),
                    'source' => 'web_upload'
                ]
            );

            return redirect()
                ->route('import-export.show-import-status', $import->id)
                ->with('success', 'Import started successfully. Processing file...');

        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return back()
                ->with('error', 'Failed to start import: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show import status
     */
    public function showImportStatus(Import $import): View
    {
        $import->load('user');
        
        return view('import-export.import-status', compact('import'));
    }

    /**
     * Show export form
     */
    public function showExport(Request $request): View
    {
        $user = auth()->user();
        $filters = $request->only(['search', 'organization', 'job_title', 'created_from', 'created_to']);
        
        // Get preview of customers that would be exported
        $previewCustomers = $this->searchService->searchCustomers($user, $filters, 5);
        $totalCount = $this->searchService->getSearchStatistics($user, $filters)['total_results'] ?? 0;

        return view('import-export.export', compact(
            'previewCustomers',
            'totalCount',
            'filters'
        ));
    }

    /**
     * Create export
     */
    public function export(ExportCustomersRequest $request): RedirectResponse
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
                ->route('import-export.show-export-status', $export->id)
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
    public function showExportStatus(Export $export): View
    {
        $export->load('user');
        
        return view('import-export.export-status', compact('export'));
    }

    /**
     * Download export file
     */
    public function downloadExport(Export $export): Response
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
     * Get import progress via AJAX
     */
    public function importProgress(Import $import)
    {
        return response()->json([
            'status' => $import->status,
            'progress' => $import->progress,
            'processed_rows' => $import->processed_rows,
            'total_rows' => $import->total_rows,
            'error_message' => $import->error_message,
            'completed_at' => $import->completed_at?->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get export progress via AJAX
     */
    public function exportProgress(Export $export)
    {
        return response()->json([
            'status' => $export->status,
            'progress' => $export->progress,
            'file_size' => $export->file_size,
            'download_url' => $export->status === 'completed' 
                ? route('import-export.download', $export->id)
                : null,
            'error_message' => $export->error_message,
            'completed_at' => $export->completed_at?->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Cancel import
     */
    public function cancelImport(Import $import): RedirectResponse
    {
        if (!in_array($import->status, ['pending', 'processing'])) {
            return back()->with('error', 'Cannot cancel import in current status');
        }

        $this->importService->cancelImport($import);

        return back()->with('success', 'Import cancelled successfully');
    }

    /**
     * Delete import record
     */
    public function deleteImport(Import $import): RedirectResponse
    {
        $this->importService->deleteImport($import);

        return redirect()
            ->route('import-export.index')
            ->with('success', 'Import deleted successfully');
    }

    /**
     * Delete export record
     */
    public function deleteExport(Export $export): RedirectResponse
    {
        $this->exportService->deleteExport($export);

        return redirect()
            ->route('import-export.index')
            ->with('success', 'Export deleted successfully');
    }
}