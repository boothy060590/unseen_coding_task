<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property string $name
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
        'name',
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
        static::creating(function (Customer $customer) {
            if (!$customer->user_id && auth()->check()) {
                $customer->user_id = auth()->id();
            }
        });

        static::addGlobalScope('user', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('user_id', auth()->id());
            }
        });
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(50);
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


    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
