<?php

namespace App\Http\Controllers;

use App\Services\CustomerService;
use App\Services\SearchService;
use App\Services\ImportService;
use App\Services\ExportService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private CustomerService $customerService,
        private SearchService $searchService,
        private ImportService $importService,
        private ExportService $exportService,
        private AuditService $auditService
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
        
        // Get recent activity
        $recentActivity = $this->auditService->getRecentUserActivities($user, 10);
        
        // Get top organizations (placeholder - method needs to be implemented)
        $topOrganizations = collect();

        // Get search statistics if filters are applied
        $searchStats = !empty($filters) 
            ? $this->searchService->getSearchStatistics($user, $filters)
            : null;

        return view('dashboard', [
            'customers' => $dashboardData['customers'],
            'recentCustomers' => $dashboardData['recent_customers'],
            'statistics' => $statistics, 
            'recentImports' => $recentImports,
            'recentExports' => $recentExports,
            'recentActivity' => $recentActivity,
            'topOrganizations' => $topOrganizations,
            'searchStats' => $searchStats,
            'filters' => $filters
        ]);
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

        return view('dashboard', [
            'customers' => $results,
            'recentCustomers' => collect(),
            'statistics' => $searchStats,
            'recentImports' => collect(),
            'recentExports' => collect(),
            'recentActivity' => collect(),
            'topOrganizations' => collect(),
            'searchStats' => $searchStats,
            'filters' => $filters
        ]);
    }

    /**
     * Get search suggestions via AJAX
     */
    public function suggestions(Request $request)
    {
        $user = auth()->user();
        $query = $request->get('query');

        if (!$query || strlen($query) < 2) {
            return response()->json([]);
        }

        $suggestions = $this->searchService->getComprehensiveSuggestions($user, $query, 8);

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
        
        return view('dashboard', [
            'customers' => $recentCustomers,
            'recentCustomers' => $recentCustomers,
            'statistics' => $statistics,
            'recentImports' => collect(),
            'recentExports' => collect(),
            'recentActivity' => collect(),
            'topOrganizations' => collect(),
            'searchStats' => null,
            'filters' => []
        ]);
    }
}
