<?php

namespace App\Policies;

use App\Models\Export;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ExportPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Users can view their own exports
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Export $export): bool
    {
        return $export->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true; // Authenticated users can create exports
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Export $export): bool
    {
        return $export->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Export $export): bool
    {
        return $export->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Export $export): bool
    {
        return $export->user_id === $user->id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Export $export): bool
    {
        return $export->user_id === $user->id;
    }
}
