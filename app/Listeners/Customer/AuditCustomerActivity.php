<?php

namespace App\Listeners\Customer;

use App\Services\AuditService;
use App\Events\Customer\CustomerEvent;
use App\Events\Customer\CustomerCreated;
use App\Events\Customer\CustomerDeleted;
use App\Events\Customer\CustomerUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Listener to handle customer activity auditing
 */
class AuditCustomerActivity implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to
     *
     * @var string|null
     */
    public string $queue = 'audit';

    /**
     * The time (seconds) before the job should be processed
     *
     * @var int
     */
    public int $delay = 0;

    /**
     * Constructor
     *
     * @param AuditService $auditService
     */
    public function __construct(
        private AuditService $auditService
    ) {}

    /**
     * Handle customer events
     *
     * @param CustomerEvent $event
     * @return void
     */
    public function handle(CustomerEvent $event): void
    {
        match (get_class($event)) {
            CustomerCreated::class => $this->handleCustomerCreated($event),
            CustomerUpdated::class => $this->handleCustomerUpdated($event),
            CustomerDeleted::class => $this->handleCustomerDeleted($event),
            default => null,
        };
    }

    /**
     * Handle customer created event
     *
     * @param CustomerCreated $event
     * @return void
     */
    private function handleCustomerCreated(CustomerCreated $event): void
    {
        $properties = array_merge([
            'customer_id' => $event->customer->id,
            'customer_name' => $event->customer->name,
            'customer_email' => $event->customer->email,
            'source' => $event->context['source'] ?? 'web',
        ], $this->getRequestContext());

        $this->auditService->logCustomerActivity(
            $event->user,
            $event->customer,
            'created',
            "Customer '{$event->customer->name}' was created",
            $properties
        );
    }

    /**
     * Handle customer updated event
     *
     * @param CustomerUpdated $event
     * @return void
     */
    private function handleCustomerUpdated(CustomerUpdated $event): void
    {
        // Determine what changed
        $changes = $this->getChanges($event->originalData, $event->customer->toArray());
        $changedFields = array_keys($changes);

        if (empty($changedFields)) {
            return; // No actual changes, skip audit
        }

        $properties = array_merge([
            'customer_id' => $event->customer->id,
            'customer_name' => $event->customer->name,
            'changed_fields' => $changedFields,
            'changes' => $changes,
            'source' => $event->context['source'] ?? 'web',
        ], $this->getRequestContext());

        $fieldsText = implode(', ', $changedFields);
        $description = "Customer '{$event->customer->name}' was updated. Changed fields: {$fieldsText}";

        $this->auditService->logCustomerActivity(
            $event->user,
            $event->customer,
            'updated',
            $description,
            $properties
        );
    }

    /**
     * Handle customer deleted event
     *
     * @param CustomerDeleted $event
     * @return void
     */
    private function handleCustomerDeleted(CustomerDeleted $event): void
    {
        $properties = array_merge([
            'customer_id' => $event->customer->id,
            'customer_name' => $event->customer->name,
            'customer_email' => $event->customer->email,
            'deleted_data' => $event->customer->toArray(),
            'source' => $event->context['source'] ?? 'web',
        ], $this->getRequestContext());

        $this->auditService->logCustomerActivity(
            $event->user,
            $event->customer,
            'deleted',
            "Customer '{$event->customer->name}' was deleted",
            $properties
        );
    }

    /**
     * Get request context information
     *
     * @return array<string, mixed>
     */
    private function getRequestContext(): array
    {
        if (!request()) {
            return [];
        }

        return [
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
        ];
    }

    /**
     * Get changes between original and current data
     *
     * @param array<string, mixed> $original
     * @param array<string, mixed> $current
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function getChanges(array $original, array $current): array
    {
        $changes = [];

        // Fields we want to track changes for
        $trackableFields = [
            'name', 'email', 'phone', 'organization', 
            'job_title', 'birthdate', 'notes'
        ];

        foreach ($trackableFields as $field) {
            $oldValue = $original[$field] ?? null;
            $newValue = $current[$field] ?? null;

            // Compare values (handle null vs empty string)
            if ($this->valuesAreDifferent($oldValue, $newValue)) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Check if two values are meaningfully different
     *
     * @param mixed $old
     * @param mixed $new
     * @return bool
     */
    private function valuesAreDifferent(mixed $old, mixed $new): bool
    {
        // Normalize null/empty values
        $old = $old === '' ? null : $old;
        $new = $new === '' ? null : $new;

        return $old !== $new;
    }
}