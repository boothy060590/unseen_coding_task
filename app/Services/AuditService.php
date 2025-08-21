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
     * @return Collection<int, Activity>
     */
    public function getRecentUserActivities(User $user, int $limit = 10): Collection
    {
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
}