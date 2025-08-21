<?php

namespace Tests\Unit\Services;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(UserService::class)]
class UserServiceTest extends TestCase
{
    private UserService $service;
    private UserRepositoryInterface&MockObject $mockRepository;
    private CustomerRepositoryInterface&MockObject $mockCustomerRepo;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = $this->createMock(UserRepositoryInterface::class);
        $this->mockCustomerRepo = $this->createMock(CustomerRepositoryInterface::class);
        $this->service = new UserService($this->mockRepository, $this->mockCustomerRepo);
        $this->user = new User([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com'
        ]);
        $this->user->setAttribute('id', 1);

        Hash::shouldReceive('make')->andReturn('hashed_password');
    }

    public function testCreateUser(): void
    {
        $userData = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'password' => 'password123'
        ];

        Hash::shouldReceive('isHashed')->andReturn(true);
        Hash::shouldReceive('verifyConfiguration')->andReturn(true);
        $expectedUser = new User(array_merge($userData, ['id' => 2, 'password' => 'hashed_password']));

        $this->mockRepository->expects($this->once())
            ->method('emailExists')
            ->with('jane@example.com')
            ->willReturn(false);

        $this->mockRepository->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($data) {
                return $data['first_name'] === 'Jane' &&
                       $data['last_name'] === 'Smith' &&
                       $data['email'] === 'jane@example.com' &&
                       $data['password'] === 'hashed_password';
            }))
            ->willReturn($expectedUser);

        Hash::shouldReceive('make')->with('password123')->andReturn('hashed_password');

        $result = $this->service->createUser($userData);

        $this->assertSame($expectedUser, $result);
    }

    public function testCreateUserWithDuplicateEmailThrowsException(): void
    {
        $userData = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'existing@example.com',
            'password' => 'password123'
        ];

        $this->mockRepository->expects($this->once())
            ->method('emailExists')
            ->with('existing@example.com')
            ->willReturn(true);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The email has already been taken.');

        $this->service->createUser($userData);
    }

    public function testCreateUserWithoutPassword(): void
    {
        $userData = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com'
        ];

        $expectedUser = new User($userData + ['id' => 2]);

        $this->mockRepository->expects($this->once())
            ->method('emailExists')
            ->willReturn(false);

        $this->mockRepository->expects($this->once())
            ->method('create')
            ->with($userData)
            ->willReturn($expectedUser);

        $result = $this->service->createUser($userData);

        $this->assertSame($expectedUser, $result);
    }

    public function testUpdateUser(): void
    {
        $updateData = [
            'first_name' => 'Johnny',
            'email' => 'johnny@example.com',
            'password' => 'newpassword123'
        ];

        $updatedUser = new User([
            'id' => 1,
            'first_name' => 'Johnny',
            'last_name' => 'Doe',
            'email' => 'johnny@example.com'
        ]);

        $this->mockRepository->expects($this->once())
            ->method('emailExists')
            ->with('johnny@example.com', 1)
            ->willReturn(false);

        $this->mockRepository->expects($this->once())
            ->method('update')
            ->with($this->user, $this->callback(function ($data) {
                return $data['first_name'] === 'Johnny' &&
                       $data['email'] === 'johnny@example.com' &&
                       $data['password'] === 'hashed_password';
            }))
            ->willReturn($updatedUser);

        $result = $this->service->updateUser($this->user, $updateData);

        $this->assertSame($updatedUser, $result);
    }

    public function testUpdateUserWithSameEmail(): void
    {
        $updateData = [
            'first_name' => 'Johnny',
            'email' => 'john@example.com' // Same email as current user
        ];

        $updatedUser = new User([
            'id' => 1,
            'first_name' => 'Johnny',
            'email' => 'john@example.com'
        ]);

        $this->mockRepository->expects($this->never())
            ->method('emailExists');

        $this->mockRepository->expects($this->once())
            ->method('update')
            ->with($this->user, $updateData)
            ->willReturn($updatedUser);

        $result = $this->service->updateUser($this->user, $updateData);

        $this->assertSame($updatedUser, $result);
    }

    public function testUpdateUserWithDuplicateEmailThrowsException(): void
    {
        $updateData = ['email' => 'existing@example.com'];

        $this->mockRepository->expects($this->once())
            ->method('emailExists')
            ->with('existing@example.com', 1)
            ->willReturn(true);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The email has already been taken.');

        $this->service->updateUser($this->user, $updateData);
    }

    public function testGetUser(): void
    {
        $userId = 1;
        $expectedUser = new User(['id' => $userId]);

        $this->mockRepository->expects($this->once())
            ->method('find')
            ->with($userId)
            ->willReturn($expectedUser);

        $result = $this->service->getUser($userId);

        $this->assertSame($expectedUser, $result);
    }

    public function testGetUserByEmail(): void
    {
        $email = 'test@example.com';
        $expectedUser = new User(['email' => $email]);

        $this->mockRepository->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($expectedUser);

        $result = $this->service->getUserByEmail($email);

        $this->assertSame($expectedUser, $result);
    }

    public function testGetUsers(): void
    {
        $filters = ['status' => 'active'];
        $expectedUsers = new Collection([new User(['id' => 1])]);

        $this->mockRepository->expects($this->once())
            ->method('getAllWithFilters')
            ->with($filters)
            ->willReturn($expectedUsers);

        $result = $this->service->getUsers($filters);

        $this->assertSame($expectedUsers, $result);
    }

    public function testGetPaginatedUsers(): void
    {
        $filters = ['status' => 'active'];
        $perPage = 20;
        $paginatedUsers = $this->createMock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class);

        $this->mockRepository->expects($this->once())
            ->method('getPaginatedWithFilters')
            ->with($filters, $perPage)
            ->willReturn($paginatedUsers);

        $result = $this->service->getPaginatedUsers($filters, $perPage);

        $this->assertSame($paginatedUsers, $result);
    }

    public function testSearchUsers(): void
    {
        $query = 'John';
        $limit = 25;
        $expectedUsers = new Collection([new User(['first_name' => 'John'])]);

        $this->mockRepository->expects($this->once())
            ->method('search')
            ->with($query, $limit)
            ->willReturn($expectedUsers);

        $result = $this->service->searchUsers($query, $limit);

        $this->assertSame($expectedUsers, $result);
    }

    public function testGetUsersWithStats(): void
    {
        $expectedUsers = new Collection([new User(['id' => 1])]);

        $this->mockRepository->expects($this->once())
            ->method('getUsersWithCustomerCounts')
            ->willReturn($expectedUsers);

        $result = $this->service->getUsersWithStats();

        $this->assertSame($expectedUsers, $result);
    }

    public function testGetRecentUsers(): void
    {
        $limit = 15;
        $expectedUsers = new Collection([new User(['id' => 1])]);

        $this->mockRepository->expects($this->once())
            ->method('getRecentUsers')
            ->with($limit)
            ->willReturn($expectedUsers);

        $result = $this->service->getRecentUsers($limit);

        $this->assertSame($expectedUsers, $result);
    }

    public function testGetUsersByVerificationStatus(): void
    {
        $verified = false;
        $expectedUsers = new Collection([new User(['id' => 1, 'email_verified_at' => null])]);

        $this->mockRepository->expects($this->once())
            ->method('getUsersByVerificationStatus')
            ->with($verified)
            ->willReturn($expectedUsers);

        $result = $this->service->getUsersByVerificationStatus($verified);

        $this->assertSame($expectedUsers, $result);
    }

    public function testGetUserCount(): void
    {
        $expectedCount = 42;

        $this->mockRepository->expects($this->once())
            ->method('getCount')
            ->willReturn($expectedCount);

        $result = $this->service->getUserCount();

        $this->assertSame($expectedCount, $result);
    }

    public function testDeleteUser(): void
    {
        $this->mockRepository->expects($this->once())
            ->method('delete')
            ->with($this->user)
            ->willReturn(true);

        $result = $this->service->deleteUser($this->user);

        $this->assertTrue($result);
    }

    public function testEmailExists(): void
    {
        $email = 'test@example.com';
        $excludeId = 5;

        $this->mockRepository->expects($this->once())
            ->method('emailExists')
            ->with($email, $excludeId)
            ->willReturn(true);

        $result = $this->service->emailExists($email, $excludeId);

        $this->assertTrue($result);
    }

    public function testMarkAsVerified(): void
    {
        $verifiedUser = new User(['id' => 1, 'email_verified_at' => now()]);

        $this->mockRepository->expects($this->once())
            ->method('update')
            ->with($this->user, $this->callback(function ($data) {
                return isset($data['email_verified_at']);
            }))
            ->willReturn($verifiedUser);

        $result = $this->service->markAsVerified($this->user);

        $this->assertSame($verifiedUser, $result);
    }

    public function testGetDashboardData(): void
    {
        $userMock = $this->createMock(User::class);

        $this->mockCustomerRepo->method('getCountForUser')->with($userMock)->willReturn(10);
        $this->mockCustomerRepo->method('getRecentForUser')->with($userMock, 10)->willReturn(new Collection());

        $result = $this->service->getDashboardData($userMock);

        $this->assertSame($userMock, $result['user']);
        $this->assertSame(10, $result['stats']['total_customers']);
        $this->assertInstanceOf(Collection::class, $result['stats']['recent_customers']);
        $this->assertSame([], $result['stats']['recent_activity']);
    }

    public function testUpdateUserWithoutPassword(): void
    {
        $updateData = [
            'first_name' => 'Johnny',
            'last_name' => 'Updated'
        ];

        $updatedUser = new User([
            'id' => 1,
            'first_name' => 'Johnny',
            'last_name' => 'Updated',
            'email' => 'john@example.com'
        ]);

        $this->mockRepository->expects($this->once())
            ->method('update')
            ->with($this->user, $updateData)
            ->willReturn($updatedUser);

        $result = $this->service->updateUser($this->user, $updateData);

        $this->assertSame($updatedUser, $result);
    }

    public function testGetUsersWithDefaultFilters(): void
    {
        $expectedUsers = new Collection([new User(['id' => 1])]);

        $this->mockRepository->expects($this->once())
            ->method('getAllWithFilters')
            ->with([])
            ->willReturn($expectedUsers);

        $result = $this->service->getUsers();

        $this->assertSame($expectedUsers, $result);
    }

    public function testGetPaginatedUsersWithDefaults(): void
    {
        $paginatedUsers = $this->createMock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class);

        $this->mockRepository->expects($this->once())
            ->method('getPaginatedWithFilters')
            ->with([], 15)
            ->willReturn($paginatedUsers);

        $result = $this->service->getPaginatedUsers();

        $this->assertSame($paginatedUsers, $result);
    }

    public function testSearchUsersWithDefaultLimit(): void
    {
        $query = 'John';
        $expectedUsers = new Collection([new User(['first_name' => 'John'])]);

        $this->mockRepository->expects($this->once())
            ->method('search')
            ->with($query, 50)
            ->willReturn($expectedUsers);

        $result = $this->service->searchUsers($query);

        $this->assertSame($expectedUsers, $result);
    }

    public function testGetRecentUsersWithDefaultLimit(): void
    {
        $expectedUsers = new Collection([new User(['id' => 1])]);

        $this->mockRepository->expects($this->once())
            ->method('getRecentUsers')
            ->with(10)
            ->willReturn($expectedUsers);

        $result = $this->service->getRecentUsers();

        $this->assertSame($expectedUsers, $result);
    }

    public function testGetUsersByVerificationStatusWithDefaultValue(): void
    {
        $expectedUsers = new Collection([new User(['id' => 1, 'email_verified_at' => now()])]);

        $this->mockRepository->expects($this->once())
            ->method('getUsersByVerificationStatus')
            ->with(true)
            ->willReturn($expectedUsers);

        $result = $this->service->getUsersByVerificationStatus();

        $this->assertSame($expectedUsers, $result);
    }

    public function testEmailExistsWithoutExcludeId(): void
    {
        $email = 'test@example.com';

        $this->mockRepository->expects($this->once())
            ->method('emailExists')
            ->with($email, null)
            ->willReturn(false);

        $result = $this->service->emailExists($email);

        $this->assertFalse($result);
    }
}
