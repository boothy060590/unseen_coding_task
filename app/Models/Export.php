<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * Export model for tracking customer export operations
 *
 * @property int $id
 * @property int $user_id
 * @property string $filename
 * @property string $type
 * @property array|null $filters
 * @property string $format
 * @property string $status
 * @property int $total_records
 * @property string|null $file_path
 * @property string|null $download_url
 * @property Carbon|null $expires_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read User $user
 * @property-read string $filters_description
 */
class Export extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'filename',
        'type',
        'filters',
        'format',
        'status',
        'total_records',
        'file_path',
        'download_url',
        'expires_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'expires_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isDownloadable(): bool
    {
        return $this->isCompleted() && !$this->isExpired() && $this->download_url;
    }

    public function getFiltersDescriptionAttribute(): string
    {
        if (!$this->filters) {
            return 'All customers';
        }

        $descriptions = [];

        if (isset($this->filters['search'])) {
            $descriptions[] = "Search: '{$this->filters['search']}'";
        }

        if (isset($this->filters['organization'])) {
            $descriptions[] = "Organization: '{$this->filters['organization']}'";
        }

        return implode(', ', $descriptions) ?: 'All customers';
    }
}
