<?php

namespace App\Models;

use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Custom Activity model extending Spatie's Activity Log model
 * Adds Laravel factory support and any custom functionality we need
 */
class Activity extends SpatieActivity
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return ActivityFactory::new();
    }
}