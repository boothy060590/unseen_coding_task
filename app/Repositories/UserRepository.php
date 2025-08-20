<?php

namespace App\Repositories;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

/**
 * Repository for User operations
 */
class UserRepository implements UserRepositoryInterface
{
    /**
     * Find a user by ID
     *
     * @param int $id
     * @return User|null
     */
    public function find(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * Find a user by email
     *
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * Create a new user
     *
     * @param array<string, mixed> $data
     * @return User
     */
    public function create(array $data): User
    {
        return User::create($data);
    }

    /**
     * Update a user
     *
     * @param User $user
     * @param array<string, mixed> $data
     * @return User
     */
    public function update(User $user, array $data): User
    {
        $user->update($data);
        
        return $user->fresh();
    }

    /**
     * Delete a user
     *
     * @param User $user
     * @return bool
     */
    public function delete(User $user): bool
    {
        return (bool) $user->delete();
    }

    /**
     * Get all users
     *
     * @return Collection<int, User>
     */
    public function getAll(): Collection
    {
        return User::all();
    }

    /**
     * Get users with customer counts
     *
     * @return Collection<int, User>
     */
    public function getUsersWithCustomerCounts(): Collection
    {
        return User::withCount('customers')->get();
    }

    /**
     * Get recently registered users
     *
     * @param int $limit
     * @return Collection<int, User>
     */
    public function getRecentUsers(int $limit = 10): Collection
    {
        return User::latest()->limit($limit)->get();
    }

    /**
     * Get users by verification status
     *
     * @param bool $verified
     * @return Collection<int, User>
     */
    public function getUsersByVerificationStatus(bool $verified = true): Collection
    {
        return User::when($verified, function (Builder $query) {
            $query->whereNotNull('email_verified_at');
        }, function (Builder $query) {
            $query->whereNull('email_verified_at');
        })->get();
    }

    /**
     * Get user count
     *
     * @return int
     */
    public function getCount(): int
    {
        return User::count();
    }

    /**
     * Search users by name or email
     *
     * @param string $query
     * @param int $limit
     * @return Collection<int, User>
     */
    public function search(string $query, int $limit = 50): Collection
    {
        return User::where(function (Builder $builder) use ($query) {
            $builder->where('name', 'LIKE', "%{$query}%")
                ->orWhere('email', 'LIKE', "%{$query}%");
        })
        ->limit($limit)
        ->get();
    }

    /**
     * Check if email exists
     *
     * @param string $email
     * @param int|null $excludeId
     * @return bool
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = User::where('email', $email);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return $query->exists();
    }
}