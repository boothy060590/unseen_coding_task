<?php

namespace App\Http\Controllers;

use App\Services\CustomerService;
use App\Services\SearchService;
use App\Services\ImportService;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private CustomerService $customerService,
        private SearchService $searchService,
        private ImportService $importService,
        private ExportService $exportService
    ) {}

    /**
     * Display the main dashboard
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $filters = $request->only(['search', 'organization', 'job_title', 'created_from', 'created_to']);

        // Get dashboard data
        $dashboardData = $this->customerService->getDashboardData($user, $filters);
        
        // Get customer statistics
        $statistics = $this->customerService->getCustomerStatistics($user);
        
        // Get recent imports/exports
        $recentImports = $this->importService->getRecentImports($user, 5);
        $recentExports = $this->exportService->getRecentExports($user, 5);

        // Get search statistics if filters are applied
        $searchStats = !empty($filters) 
            ? $this->searchService->getSearchStatistics($user, $filters)
            : null;

        return view('dashboard.index', compact(
            'dashboardData',
            'statistics', 
            'recentImports',
            'recentExports',
            'searchStats',
            'filters'
        ));
    }

    /**
     * Advanced search endpoint
     */
    public function search(Request $request): View
    {
        $user = auth()->user();
        $filters = $request->only(['search', 'organization', 'job_title', 'created_from', 'created_to', 'email_domain']);
        
        $results = $this->searchService->searchCustomers($user, $filters, 25);
        $searchStats = $this->searchService->getSearchStatistics($user, $filters);

        return view('dashboard.search', compact('results', 'searchStats', 'filters'));
    }

    /**
     * Get search suggestions via AJAX
     */
    public function suggestions(Request $request)
    {
        $user = auth()->user();
        $field = $request->get('field');
        $query = $request->get('query');

        if (!$field || !$query || strlen($query) < 2) {
            return response()->json([]);
        }

        $suggestions = $this->searchService->getSearchSuggestions($user, $field, $query, 10);

        return response()->json($suggestions);
    }

    /**
     * Customer overview with quick stats
     */
    public function overview(): View
    {
        $user = auth()->user();
        
        $statistics = $this->customerService->getCustomerStatistics($user);
        $recentCustomers = $this->customerService->searchCustomers($user, '', 10);
        
        return view('dashboard.overview', compact('statistics', 'recentCustomers'));
    }
}
