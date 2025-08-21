<?php

namespace App\Repositories;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Repository for User operations
 */
class UserRepository implements UserRepositoryInterface
{
    /**
     * Get all users with optional filtering
     *
     * @param array<string, mixed> $filters
     * @return Collection<int, User>
     */
    public function getAllWithFilters(array $filters = []): Collection
    {
        $query = User::query();
        
        $this->applyFilters($query, $filters);
        
        return $query->get();
    }

    /**
     * Get paginated users with optional filtering
     *
     * @param array<string, mixed> $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::query();
        
        $this->applyFilters($query, $filters);
        
        return $query->paginate($perPage);
    }

    /**
     * Apply filters to query
     *
     * @param Builder $query
     * @param array<string, mixed> $filters
     * @return void
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $builder) use ($search) {
                $builder->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        if (isset($filters['verified'])) {
            if ($filters['verified']) {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
        }

        if (isset($filters['with_counts']) && is_array($filters['with_counts'])) {
            foreach ($filters['with_counts'] as $relation) {
                $query->withCount($relation);
            }
        }

        if (isset($filters['limit'])) {
            $query->limit((int) $filters['limit']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        
        $validSorts = ['id', 'first_name', 'last_name', 'email', 'created_at', 'updated_at'];
        
        if (in_array($sortBy, $validSorts, true)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->latest();
        }
    }

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
        return $this->getAllWithFilters([]);
    }

    /**
     * Get users with customer counts
     *
     * @return Collection<int, User>
     */
    public function getUsersWithCustomerCounts(): Collection
    {
        return $this->getAllWithFilters(['with_counts' => ['customers']]);
    }

    /**
     * Get recently registered users
     *
     * @param int $limit
     * @return Collection<int, User>
     */
    public function getRecentUsers(int $limit = 10): Collection
    {
        return $this->getAllWithFilters([
            'limit' => $limit,
            'sort_by' => 'created_at',
            'sort_direction' => 'desc'
        ]);
    }

    /**
     * Get users by verification status
     *
     * @param bool $verified
     * @return Collection<int, User>
     */
    public function getUsersByVerificationStatus(bool $verified = true): Collection
    {
        return $this->getAllWithFilters(['verified' => $verified]);
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
        return $this->getAllWithFilters([
            'search' => $query,
            'limit' => $limit
        ]);
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