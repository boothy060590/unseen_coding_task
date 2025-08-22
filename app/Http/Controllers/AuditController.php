<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use App\Services\AuditService;
use App\Http\Requests\AuditExportRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Carbon\Carbon;

class AuditController extends Controller
{
    public function __construct(
        private AuditService $auditService
    ) {}

    /**
     * Display audit dashboard
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = auth()->user();
        $filters = $request->only(['event', 'customer_id', 'date_from', 'date_to']);

        // Get activities based on filters
        if (!empty($filters)) {
            $activities = $this->getFilteredActivities($user, $filters);
        } else {
            $activities = $this->auditService->getRecentUserActivities($user, 20);
        }

        // Get audit statistics
        $statistics = $this->auditService->getAuditStatistics($user);

        return view('audit.index', compact(
            'activities',
            'statistics',
            'filters'
        ));
    }

    /**
     * Show audit trail for specific customer
     */
    public function customer(Customer $customer): View
    {
        $user = auth()->user();

        $activities = $this->auditService->getCustomerAuditTrail($user, $customer, 50);
        $customerStats = $this->auditService->getCustomerActivitySummary($user, $customer);
        $statistics = $customerStats; // Use customer stats as statistics
        $filters = ['customer' => $customer]; // Add customer context

        return view('audit.index', compact('customer', 'activities', 'statistics', 'filters'));
    }

    /**
     * Search audit logs
     */
    public function search(Request $request): View
    {
        $user = auth()->user();
        $filters = $request->only(['event', 'customer_id', 'date_from', 'date_to', 'search']);

        $activities = $this->getFilteredActivities($user, $filters);
        $statistics = $this->auditService->getFilteredStatistics($user, $filters);

        return view('audit.index', compact('activities', 'statistics', 'filters'));
    }

    /**
     * Export audit logs
     */
    public function export(AuditExportRequest $request): Response
    {
        $user = auth()->user();
        $fromDate = Carbon::parse($request->get('date_from'));
        $toDate = Carbon::parse($request->get('date_to'));
        $format = $request->get('format');

        return $this->auditService->exportAuditTrail($user, $fromDate, $toDate, $format);
    }

    /**
     * Show detailed activity information
     */
    public function activity(int $activityId): View
    {
        $user = auth()->user();

        $activity = $this->auditService->getDetailedActivity($user, $activityId);

        if (!$activity) {
            abort(404, 'Activity not found');
        }

        $activities = collect([$activity]); // Wrap single activity in collection
        $statistics = []; // Empty stats for single activity view
        $filters = ['activity_id' => $activityId];

        return view('audit.index', compact('activities', 'statistics', 'filters'));
    }

    /**
     * Get audit statistics for dashboard widgets
     */
    public function statistics(Request $request)
    {
        $user = auth()->user();
        $period = $request->get('period', '7days'); // 7days, 30days, 90days

        $statistics = $this->auditService->getStatisticsForPeriod($user, $period);

        return response()->json($statistics);
    }

    /**
     * Get recent activities for live updates
     */
    public function recent(Request $request)
    {
        $user = auth()->user();
        $limit = min($request->get('limit', 10), 50);
        $since = $request->get('since'); // timestamp for updates since last check

        $activities = $this->auditService->getRecentUserActivities($user, $limit, $since);

        return response()->json([
            'activities' => $activities->map(function ($activity) {
                return $this->auditService->formatActivityForApi($activity);
            }),
            'count' => $activities->count(),
            'timestamp' => now()->timestamp,
        ]);
    }

    /**
     * Archive old audit logs to S3
     */
    public function archive(Request $request): Response
    {
        $request->validate([
            'date_before' => 'required|date|before:today',
        ]);

        $user = auth()->user();
        $dateBefore = Carbon::parse($request->get('date_before'));

        try {
            $archivedCount = $this->auditService->archiveOldActivities($user, $dateBefore);

            return response()->json([
                'success' => true,
                'message' => "Successfully archived {$archivedCount} activities",
                'archived_count' => $archivedCount,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to archive activities: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get filtered activities based on request filters
     */
    private function getFilteredActivities($user, array $filters)
    {
        // Convert request filters to repository filters
        $repositoryFilters = [];

        if (!empty($filters['event'])) {
            $repositoryFilters['event'] = $filters['event'];
        }

        if (!empty($filters['date_from'])) {
            $repositoryFilters['date_from'] = Carbon::parse($filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $repositoryFilters['date_to'] = Carbon::parse($filters['date_to']);
        }

        if (!empty($filters['customer_id'])) {
            $repositoryFilters['customer_ids'] = [(int) $filters['customer_id']];
        }

        if (!empty($filters['search'])) {
            $repositoryFilters['search'] = $filters['search'];
        }

        // Set default limit if no specific filters
        if (empty($repositoryFilters)) {
            $repositoryFilters['limit'] = 50;
            $repositoryFilters['sort_by'] = 'created_at';
            $repositoryFilters['sort_direction'] = 'desc';
        }

        return $this->auditService->getFilteredActivities($user, $repositoryFilters);
    }
}
