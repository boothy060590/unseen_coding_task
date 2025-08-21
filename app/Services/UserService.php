<?php

namespace App\Services;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Service for User business logic and operations
 */
class UserService
{
    /**
     * Constructor
     *
     * @param UserRepositoryInterface $userRepository
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private CustomerRepositoryInterface $customerRepository
    ) {}

    /**
     * Create a new user
     *
     * @param array<string, mixed> $data
     * @return User
     * @throws ValidationException
     */
    public function createUser(array $data): User
    {
        // Validate unique email
        if ($this->userRepository->emailExists($data['email'])) {
            throw ValidationException::withMessages([
                'email' => ['The email has already been taken.'],
            ]);
        }

        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return $this->userRepository->create($data);
    }

    /**
     * Update user profile
     *
     * @param User $user
     * @param array<string, mixed> $data
     * @return User
     * @throws ValidationException
     */
    public function updateUser(User $user, array $data): User
    {
        // Validate unique email if changing
        if (isset($data['email']) && $data['email'] !== $user->email) {
            if ($this->userRepository->emailExists($data['email'], $user->id)) {
                throw ValidationException::withMessages([
                    'email' => ['The email has already been taken.'],
                ]);
            }
        }

        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return $this->userRepository->update($user, $data);
    }

    /**
     * Get user by ID
     *
     * @param int $id
     * @return User|null
     */
    public function getUser(int $id): ?User
    {
        return $this->userRepository->find($id);
    }

    /**
     * Get user by email
     *
     * @param string $email
     * @return User|null
     */
    public function getUserByEmail(string $email): ?User
    {
        return $this->userRepository->findByEmail($email);
    }

    /**
     * Get all users with optional filtering
     *
     * @param array<string, mixed> $filters
     * @return Collection<int, User>
     */
    public function getUsers(array $filters = []): Collection
    {
        return $this->userRepository->getAllWithFilters($filters);
    }

    /**
     * Get paginated users with optional filtering
     *
     * @param array<string, mixed> $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedUsers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->userRepository->getPaginatedWithFilters($filters, $perPage);
    }

    /**
     * Search users by name or email
     *
     * @param string $query
     * @param int $limit
     * @return Collection<int, User>
     */
    public function searchUsers(string $query, int $limit = 50): Collection
    {
        return $this->userRepository->search($query, $limit);
    }

    /**
     * Get users with customer counts
     *
     * @return Collection<int, User>
     */
    public function getUsersWithStats(): Collection
    {
        return $this->userRepository->getUsersWithCustomerCounts();
    }

    /**
     * Get recent users
     *
     * @param int $limit
     * @return Collection<int, User>
     */
    public function getRecentUsers(int $limit = 10): Collection
    {
        return $this->userRepository->getRecentUsers($limit);
    }

    /**
     * Get users by verification status
     *
     * @param bool $verified
     * @return Collection<int, User>
     */
    public function getUsersByVerificationStatus(bool $verified = true): Collection
    {
        return $this->userRepository->getUsersByVerificationStatus($verified);
    }

    /**
     * Get total user count
     *
     * @return int
     */
    public function getUserCount(): int
    {
        return $this->userRepository->getCount();
    }

    /**
     * Delete a user
     *
     * @param User $user
     * @return bool
     */
    public function deleteUser(User $user): bool
    {
        return $this->userRepository->delete($user);
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
        return $this->userRepository->emailExists($email, $excludeId);
    }

    /**
     * Mark user as verified
     *
     * @param User $user
     * @return User
     */
    public function markAsVerified(User $user): User
    {
        return $this->userRepository->update($user, [
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Get user dashboard data
     *
     * @param User $user
     * @return array<string, mixed>
     */
    public function getDashboardData(User $user): array
    {
        // This could be expanded based on what dashboard data is needed
        return [
            'user' => $user,
            'stats' => [
                'total_customers' => $this->customerRepository->getCountForUser($user),
                'recent_customers' => $this->customerRepository->getRecentForUser($user),
                'recent_activity' => [], // Could integrate with audit repository
            ],
        ];
    }
}
