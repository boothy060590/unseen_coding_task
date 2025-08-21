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

        // Get recent activities
        $recentActivities = $this->auditService->getRecentUserActivities($user, 20);

        // Get audit statistics
        $statistics = $this->auditService->getAuditStatistics($user);

        // Get filtered activities if filters are applied
        $filteredActivities = null;
        if (!empty($filters)) {
            $filteredActivities = $this->getFilteredActivities($user, $filters);
        }

        return view('audit.index', compact(
            'recentActivities',
            'statistics',
            'filteredActivities',
            'filters'
        ));
    }

    /**
     * Show audit trail for specific customer
     */
    public function customer(Customer $customer): View
    {
        $user = auth()->user();

        $auditTrail = $this->auditService->getCustomerAuditTrail($user, $customer, 50);
        $customerStats = $this->auditService->getCustomerActivitySummary($user, $customer);

        return view('audit.customer', compact('customer', 'auditTrail', 'customerStats'));
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

        return view('audit.search', compact('activities', 'statistics', 'filters'));
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

        return view('audit.activity', compact('activity'));
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
        $activities = collect();

        // Filter by event type
        if (!empty($filters['event'])) {
            $activities = $this->auditService->getActivitiesByEvent($user, $filters['event']);
        }

        // Filter by date range
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $fromDate = Carbon::parse($filters['date_from']);
            $toDate = Carbon::parse($filters['date_to']);

            if ($activities->isEmpty()) {
                $activities = $this->auditService->getActivitiesByDateRange($user, $fromDate, $toDate);
            } else {
                $activities = $activities->whereBetween('created_at', [$fromDate, $toDate]);
            }
        }

        // Filter by customer
        if (!empty($filters['customer_id'])) {
            $activities = $activities->where('subject_id', $filters['customer_id']);
        }

        // Text search in descriptions
        if (!empty($filters['search'])) {
            $searchTerm = strtolower($filters['search']);
            $activities = $activities->filter(function ($activity) use ($searchTerm) {
                return str_contains(strtolower($activity->description), $searchTerm) ||
                       str_contains(strtolower($activity->event), $searchTerm);
            });
        }

        // If no specific filters, get recent activities
        if ($activities->isEmpty() && empty($filters)) {
            $activities = $this->auditService->getRecentUserActivities($user, 50);
        }

        return $activities;
    }
}
