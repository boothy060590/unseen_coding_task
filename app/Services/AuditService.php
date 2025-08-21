<?php

namespace App\Services;

use App\Contracts\Repositories\AuditRepositoryInterface;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Service for managing audit trails using Spatie Activity Log
 */
class AuditService
{
    /**
     * Constructor
     *
     * @param Filesystem $storage S3 filesystem instance
     * @param AuditRepositoryInterface $auditRepository
     */
    public function __construct(
        private Filesystem $storage,
        private AuditRepositoryInterface $auditRepository
    ) {}
    /**
     * Get audit trail for a specific customer
     *
     * @param User $user
     * @param Customer $customer
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getCustomerAuditTrail(User $user, Customer $customer, int $perPage = 15): LengthAwarePaginator
    {
        return $this->auditRepository->getCustomerAuditTrail($user, $customer, $perPage);
    }

    /**
     * Get all audit activities for a user's customers
     *
     * @param User $user
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUserAuditTrail(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $this->auditRepository->getUserAuditTrail($user, $perPage);
    }

    /**
     * Get recent audit activities for a user
     *
     * @param User $user
     * @param int $limit
     * @param string|null $since
     * @return Collection<int, Activity>
     */
    public function getRecentUserActivities(User $user, int $limit = 10, ?string $since = null): Collection
    {
        if ($since) {
            return $this->auditRepository->getActivitiesSince($user, $since, $limit);
        }
        
        return $this->auditRepository->getRecentUserActivities($user, $limit);
    }

    /**
     * Get audit statistics for a user
     *
     * @param User $user
     * @return array<string, mixed>
     */
    public function getAuditStatistics(User $user): array
    {
        $totalActivities = $this->auditRepository->getActivityCountForUser($user);
        $eventBreakdown = $this->auditRepository->getActivityCountsByEvent($user);
        $mostActiveCustomers = $this->auditRepository->getMostActiveCustomers($user, 5);

        // Get date-based statistics using repository
        $todayActivities = $this->auditRepository->getActivitiesByDateRange(
            $user,
            now()->startOfDay(),
            now()->endOfDay()
        )->count();

        $weekActivities = $this->auditRepository->getActivitiesByDateRange(
            $user,
            now()->startOfWeek(),
            now()->endOfWeek()
        )->count();

        $monthActivities = $this->auditRepository->getActivitiesByDateRange(
            $user,
            now()->startOfMonth(),
            now()->endOfMonth()
        )->count();

        return [
            'total_activities' => $totalActivities,
            'today_activities' => $todayActivities,
            'week_activities' => $weekActivities,
            'month_activities' => $monthActivities,
            'event_breakdown' => $eventBreakdown,
            'most_active_customers' => $mostActiveCustomers,
        ];
    }

    /**
     * Get activity summary for a specific customer
     *
     * @param User $user
     * @param Customer $customer
     * @return array<string, mixed>
     */
    public function getCustomerActivitySummary(User $user, Customer $customer): array
    {
        $activities = $this->auditRepository->getCustomerAuditTrail($user, $customer, 1000);
        $eventBreakdown = $activities->countBy('event');
        
        $firstActivity = $activities->sortBy('created_at')->first();
        $lastActivity = $activities->sortByDesc('created_at')->first();

        return [
            'total_activities' => $activities->count(),
            'first_activity' => $firstActivity?->created_at,
            'last_activity' => $lastActivity?->created_at,
            'event_breakdown' => $eventBreakdown->toArray(),
            'most_recent_event' => $lastActivity?->event,
            'activity_frequency' => $this->calculateActivityFrequency($activities),
        ];
    }

    /**
     * Format activity for display
     *
     * @param Activity $activity
     * @return array<string, mixed>
     */
    public function formatActivity(Activity $activity): array
    {
        $changes = $this->formatChanges($activity);
        
        return [
            'id' => $activity->id,
            'event' => $activity->event,
            'description' => $activity->description,
            'changes' => $changes,
            'causer' => $activity->causer?->full_name ?? 'System',
            'subject' => $activity->subject?->name ?? 'Unknown',
            'created_at' => $activity->created_at,
            'ip_address' => $activity->properties['ip_address'] ?? null,
            'user_agent' => $activity->properties['user_agent'] ?? null,
        ];
    }

    /**
     * Store audit trail to S3 for archival
     *
     * @param User $user
     * @param \DateTimeInterface|null $fromDate
     * @param \DateTimeInterface|null $toDate
     * @return string S3 file path
     */
    public function storeAuditToS3(
        User $user, 
        ?\DateTimeInterface $fromDate = null, 
        ?\DateTimeInterface $toDate = null
    ): string {
        $fromDate = $fromDate ?? now()->subYear();
        $toDate = $toDate ?? now();

        // Get activities for the date range
        $activities = $this->auditRepository->getActivitiesByDateRange($user, $fromDate, $toDate);

        // Format data for JSON storage
        $auditData = [
            'user_id' => $user->id,
            'user_name' => $user->full_name,
            'user_email' => $user->email,
            'export_date' => now()->toISOString(),
            'date_range' => [
                'from' => $fromDate->format('Y-m-d H:i:s'),
                'to' => $toDate->format('Y-m-d H:i:s'),
            ],
            'total_activities' => $activities->count(),
            'activities' => $activities->map(function (Activity $activity) {
                return $this->formatActivity($activity);
            })->toArray(),
        ];

        // Generate filename
        $filename = sprintf(
            'audit-trails/user_%d/audit_%s_to_%s_%s.json',
            $user->id,
            $fromDate->format('Y_m_d'),
            $toDate->format('Y_m_d'),
            now()->format('Y_m_d_H_i_s')
        );

        // Store to S3
        $this->storage->put($filename, json_encode($auditData, JSON_PRETTY_PRINT));

        return $filename;
    }

    /**
     * Retrieve stored audit from S3
     *
     * @param string $filePath
     * @return array<string, mixed>
     */
    public function retrieveAuditFromS3(string $filePath): array
    {
        if (!$this->storage->exists($filePath)) {
            throw new \InvalidArgumentException('Audit file not found in S3');
        }

        $content = $this->storage->get($filePath);
        return json_decode($content, true);
    }

    /**
     * List stored audit files for a user
     *
     * @param User $user
     * @return array<string>
     */
    public function listStoredAudits(User $user): array
    {
        $directory = "audit-trails/user_{$user->id}/";
        
        return $this->storage->files($directory);
    }

    /**
     * Clean up old audit files from S3
     *
     * @param User $user
     * @param int $daysToKeep
     * @return int Number of files deleted
     */
    public function cleanupOldAudits(User $user, int $daysToKeep = 365): int
    {
        $directory = "audit-trails/user_{$user->id}/";
        $files = $this->storage->files($directory);
        $deletedCount = 0;
        $cutoffDate = now()->subDays($daysToKeep);

        foreach ($files as $file) {
            $lastModified = $this->storage->lastModified($file);
            
            if ($lastModified && $lastModified < $cutoffDate->getTimestamp()) {
                $this->storage->delete($file);
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * Log custom activity for a customer
     *
     * @param User $user
     * @param Customer $customer
     * @param string $event
     * @param string $description
     * @param array<string, mixed> $properties
     * @return Activity
     */
    public function logCustomerActivity(
        User $user,
        Customer $customer,
        string $event,
        string $description,
        array $properties = []
    ): Activity {
        return $this->auditRepository->logCustomerActivity($user, $customer, $event, $description, $properties);
    }


    /**
     * Format activity for API response
     *
     * @param Activity $activity
     * @return array<string, mixed>
     */
    public function formatActivityForApi(Activity $activity): array
    {
        return [
            'id' => $activity->id,
            'event' => $activity->event,
            'description' => $activity->description,
            'customer_name' => $activity->subject?->full_name ?? 'Unknown',
            'customer_slug' => $activity->subject?->slug ?? null,
            'created_at' => $activity->created_at->toISOString(),
            'created_at_human' => $activity->created_at->diffForHumans(),
        ];
    }

    /**
     * Get detailed activity information
     *
     * @param User $user
     * @param int $activityId
     * @return Activity|null
     */
    public function getDetailedActivity(User $user, int $activityId): ?Activity
    {
        return $this->auditRepository->getActivityById($user, $activityId);
    }

    /**
     * Get activities by event type
     *
     * @param User $user
     * @param string $event
     * @return Collection
     */
    public function getActivitiesByEvent(User $user, string $event): Collection
    {
        return $this->auditRepository->getActivitiesByEvent($user, $event);
    }

    /**
     * Get activities by date range
     *
     * @param User $user
     * @param \DateTimeInterface $fromDate
     * @param \DateTimeInterface $toDate
     * @return Collection
     */
    public function getActivitiesByDateRange(User $user, \DateTimeInterface $fromDate, \DateTimeInterface $toDate): Collection
    {
        return $this->auditRepository->getActivitiesByDateRange($user, $fromDate, $toDate);
    }

    /**
     * Get filtered statistics
     *
     * @param User $user
     * @param array $filters
     * @return array<string, mixed>
     */
    public function getFilteredStatistics(User $user, array $filters): array
    {
        // This would use the repository to get filtered statistics
        // For now, return basic stats
        return [
            'total_activities' => $this->auditRepository->getActivityCountForUser($user),
            'filtered_count' => 0, // Would be calculated based on filters
        ];
    }

    /**
     * Export audit trail
     *
     * @param User $user
     * @param \DateTimeInterface $fromDate
     * @param \DateTimeInterface $toDate
     * @param string $format
     * @return \Illuminate\Http\Response
     */
    public function exportAuditTrail(User $user, \DateTimeInterface $fromDate, \DateTimeInterface $toDate, string $format): \Illuminate\Http\Response
    {
        $activities = $this->getActivitiesByDateRange($user, $fromDate, $toDate);
        
        $filename = sprintf('audit-trail-%s-to-%s.%s', 
            $fromDate->format('Y-m-d'), 
            $toDate->format('Y-m-d'), 
            $format
        );

        if ($format === 'csv') {
            return $this->exportToCsv($activities, $filename);
        } else {
            return $this->exportToJson($activities, $filename);
        }
    }

    /**
     * Get statistics for specific period
     *
     * @param User $user
     * @param string $period
     * @return array<string, mixed>
     */
    public function getStatisticsForPeriod(User $user, string $period): array
    {
        $fromDate = match($period) {
            '7days' => now()->subDays(7),
            '30days' => now()->subDays(30),
            '90days' => now()->subDays(90),
            default => now()->subDays(7),
        };

        $activities = $this->getActivitiesByDateRange($user, $fromDate, now());
        
        return [
            'period' => $period,
            'total_activities' => $activities->count(),
            'event_breakdown' => $activities->countBy('event')->toArray(),
            'daily_breakdown' => $activities->groupBy(function($activity) {
                return $activity->created_at->format('Y-m-d');
            })->map->count()->toArray(),
        ];
    }

    /**
     * Archive old activities
     *
     * @param User $user
     * @param \DateTimeInterface $dateBefore
     * @return int
     */
    public function archiveOldActivities(User $user, \DateTimeInterface $dateBefore): int
    {
        $activities = $this->auditRepository->getActivitiesByDateRange($user, now()->subYears(10), $dateBefore);
        
        if ($activities->isNotEmpty()) {
            $this->storeAuditToS3($user, now()->subYears(10), $dateBefore);
            return $activities->count();
        }
        
        return 0;
    }

    /**
     * Format changes from activity log
     *
     * @param Activity $activity
     * @return array<string, mixed>
     */
    private function formatChanges(Activity $activity): array
    {
        $changes = [];
        
        if ($activity->properties->has('old') && $activity->properties->has('attributes')) {
            $old = $activity->properties->get('old', []);
            $new = $activity->properties->get('attributes', []);
            
            foreach ($new as $key => $value) {
                if (isset($old[$key]) && $old[$key] !== $value) {
                    $changes[$key] = [
                        'old' => $old[$key],
                        'new' => $value,
                    ];
                }
            }
        }
        
        return $changes;
    }

    /**
     * Calculate activity frequency for a customer
     *
     * @param Collection $activities
     * @return array<string, mixed>
     */
    private function calculateActivityFrequency(Collection $activities): array
    {
        if ($activities->isEmpty()) {
            return ['per_day' => 0, 'per_week' => 0, 'per_month' => 0];
        }

        $firstActivity = $activities->sortBy('created_at')->first();
        $lastActivity = $activities->sortByDesc('created_at')->first();
        
        $daysDiff = max(1, $lastActivity->created_at->diffInDays($firstActivity->created_at));
        $totalActivities = $activities->count();
        
        return [
            'per_day' => round($totalActivities / $daysDiff, 2),
            'per_week' => round(($totalActivities / $daysDiff) * 7, 2),
            'per_month' => round(($totalActivities / $daysDiff) * 30, 2),
        ];
    }

    /**
     * Export activities to CSV
     *
     * @param Collection $activities
     * @param string $filename
     * @return \Illuminate\Http\Response
     */
    private function exportToCsv(Collection $activities, string $filename): \Illuminate\Http\Response
    {
        $csvData = [];
        $csvData[] = ['ID', 'Event', 'Description', 'Customer', 'Date', 'User', 'IP Address'];
        
        foreach ($activities as $activity) {
            $csvData[] = [
                $activity->id,
                $activity->event,
                $activity->description,
                $activity->subject?->full_name ?? 'Unknown',
                $activity->created_at->format('Y-m-d H:i:s'),
                $activity->causer?->full_name ?? 'System',
                $activity->properties['ip_address'] ?? 'N/A',
            ];
        }

        $output = fopen('php://temp', 'r+');
        foreach ($csvData as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Export activities to JSON
     *
     * @param Collection $activities
     * @param string $filename
     * @return \Illuminate\Http\Response
     */
    private function exportToJson(Collection $activities, string $filename): \Illuminate\Http\Response
    {
        $data = [
            'export_date' => now()->toISOString(),
            'total_activities' => $activities->count(),
            'activities' => $activities->map(function($activity) {
                return $this->formatActivity($activity);
            })->toArray(),
        ];

        return response(json_encode($data, JSON_PRETTY_PRINT))
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}