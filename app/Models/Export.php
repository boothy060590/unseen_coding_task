<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

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

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeNotExpired($query)
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
