<?php

namespace App\Http\Controllers;

use App\Models\Import;
use App\Services\ImportService;
use App\Http\Requests\ImportCustomersRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class ImportController extends Controller
{
    public function __construct(
        private ImportService $importService
    ) {}

    /**
     * Display import dashboard
     */
    public function index(): View
    {
        $user = auth()->user();
        $imports = $this->importService->getPaginatedImports($user);
        $recentImports = $this->importService->getRecentImports($user);
        $importStats = $this->importService->getImportStatistics($user, $recentImports);

        return view('imports.index', compact('imports', 'recentImports', 'importStats'));
    }

    /**
     * Show import form
     */
    public function create(): View
    {
        return view('imports.create');
    }

    /**
     * Handle file upload and create import
     */
    public function store(ImportCustomersRequest $request): RedirectResponse
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
                ->route('imports.show', $import->id)
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
    public function show(Import $import): View
    {
        $import->load('user');

        return view('imports.show', compact('import'));
    }

    /**
     * Get import progress via AJAX
     */
    public function progress(Import $import)
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
     * Cancel import
     */
    public function cancel(Import $import): RedirectResponse
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
    public function destroy(Import $import): RedirectResponse
    {
        $this->importService->deleteImport($import);

        return redirect()
            ->route('imports.index')
            ->with('success', 'Import deleted successfully');
    }
}
