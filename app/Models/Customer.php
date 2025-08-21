<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Carbon\Carbon;

/**
 * Customer model representing customer records owned by users
 *
 * @property int $id
 * @property int $user_id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property string|null $organization
 * @property string|null $job_title
 * @property Carbon|null $birthdate
 * @property string|null $notes
 * @property string $slug
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read User $user
 */
class Customer extends Model
{
    use HasFactory;
    use HasSlug;
    use LogsActivity;

    protected $fillable = [
        'first_name',
        'last_name',
        'user_id',
        'email',
        'phone',
        'organization',
        'job_title',
        'birthdate',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('user', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('user_id', auth()->id());
            }
        });
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(['first_name', 'last_name'])
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(50)
            ->usingSeparator('-');
    }

    /**
     * Generate slug with random suffix to ensure uniqueness
     */
    protected function generateNonUniqueSlug(): string
    {
        $baseSlug = Str::slug($this->full_name);
        $randomSuffix = Str::random(8);

        return $baseSlug . '-' . strtolower($randomSuffix);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the customer's full name
     *
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get the customer's display name (falls back to email if no names)
     *
     * @return string
     */
    public function getDisplayNameAttribute(): string
    {
        $fullName = $this->getFullNameAttribute();
        return $fullName ?: $this->email;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
