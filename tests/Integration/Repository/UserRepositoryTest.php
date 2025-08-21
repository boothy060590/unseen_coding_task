<?php

namespace Tests\Integration\Repository;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UserRepository::class)]
class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    public function testGetAllWithFilters(): void
    {
        // Create users with different attributes
        User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'email_verified_at' => now(),
            'created_at' => now()->subDays(3),
        ]);

        User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@example.com',
            'email_verified_at' => null,
            'created_at' => now()->subDays(1),
        ]);

        User::factory()->create([
            'first_name' => 'Bob',
            'last_name' => 'Johnson',
            'email' => 'bob.johnson@example.com',
            'email_verified_at' => now(),
            'created_at' => now()->subDays(2),
        ]);

        $repository = new UserRepository();

        // Test basic getAllWithFilters (no filters)
        $result = $repository->getAllWithFilters([]);
        $this->assertCount(3, $result);

        // Test search filter (name)
        $result = $repository->getAllWithFilters(['search' => 'John']);
        $this->assertCount(2, $result); // John Doe and Bob Johnson
        $result->each(function (User $user) {
            $this->assertStringContainsString('John', $user->first_name . ' ' . $user->last_name);
        });

        // Test search filter (email)
        $result = $repository->getAllWithFilters(['search' => 'jane.smith']);
        $this->assertCount(1, $result);
        $this->assertSame('jane.smith@example.com', $result->first()->email);

        // Test verified filter (true)
        $result = $repository->getAllWithFilters(['verified' => true]);
        $this->assertCount(2, $result);
        $result->each(function (User $user) {
            $this->assertNotNull($user->email_verified_at);
        });

        // Test verified filter (false)
        $result = $repository->getAllWithFilters(['verified' => false]);
        $this->assertCount(1, $result);
        $this->assertNull($result->first()->email_verified_at);

        // Test limit filter
        $result = $repository->getAllWithFilters(['limit' => 2]);
        $this->assertCount(2, $result);

        // Test sorting
        $result = $repository->getAllWithFilters([
            'sort_by' => 'created_at',
            'sort_direction' => 'asc'
        ]);
        $this->assertCount(3, $result);
        $this->assertTrue($result->first()->created_at <= $result->last()->created_at);

        // Test invalid sort field (should fall back to latest)
        $result = $repository->getAllWithFilters([
            'sort_by' => 'invalid_field',
            'sort_direction' => 'asc'
        ]);
        $this->assertCount(3, $result);
        // Should be ordered by created_at desc (latest) regardless of sort_direction
        $this->assertTrue($result->first()->created_at >= $result->last()->created_at);

        // Test with_counts filter
        $result = $repository->getAllWithFilters(['with_counts' => ['customers']]);
        $this->assertCount(3, $result);
        $result->each(function (User $user) {
            $this->assertTrue(isset($user->customers_count));
        });
    }

    /**
     * @return void
     */
    public function testGetPaginatedWithFilters(): void
    {
        User::factory(25)->create();
        $repository = new UserRepository();

        // Test default pagination
        $result = $repository->getPaginatedWithFilters([]);
        $this->assertCount(15, $result);
        $this->assertSame(25, $result->total());

        // Test custom per page
        $result = $repository->getPaginatedWithFilters([], 10);
        $this->assertCount(10, $result);

        // Test with filters
        User::factory(5)->create(['email_verified_at' => null]);
        
        $result = $repository->getPaginatedWithFilters(['verified' => false], 10);
        $this->assertCount(5, $result);
        $result->each(function (User $user) {
            $this->assertNull($user->email_verified_at);
        });
    }

    /**
     * @return void
     */
    public function testFind(): void
    {
        $user = User::factory()->create();
        $repository = new UserRepository();

        $result = $repository->find($user->id);
        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);

        $result = $repository->find(99999);
        $this->assertNull($result);
    }

    /**
     * @return void
     */
    public function testFindByEmail(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $repository = new UserRepository();

        $result = $repository->findByEmail('test@example.com');
        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);

        $result = $repository->findByEmail('nonexistent@example.com');
        $this->assertNull($result);
    }

    /**
     * @return void
     */
    public function testCreate(): void
    {
        $repository = new UserRepository();
        
        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => bcrypt('password'),
        ];

        $user = $repository->create($userData);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('John', $user->first_name);
        $this->assertSame('Doe', $user->last_name);
        $this->assertSame('john.doe@example.com', $user->email);
        $this->assertDatabaseHas('users', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
        ]);
    }

    /**
     * @return void
     */
    public function testUpdate(): void
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
        
        $repository = new UserRepository();

        $updateData = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ];

        $updatedUser = $repository->update($user, $updateData);

        $this->assertInstanceOf(User::class, $updatedUser);
        $this->assertSame($user->id, $updatedUser->id);
        $this->assertSame('Jane', $updatedUser->first_name);
        $this->assertSame('Smith', $updatedUser->last_name);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);
    }

    /**
     * @return void
     */
    public function testDelete(): void
    {
        $user = User::factory()->create();
        $repository = new UserRepository();

        $result = $repository->delete($user);
        
        $this->assertTrue($result);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /**
     * @return void
     */
    public function testGetAll(): void
    {
        User::factory(3)->create();
        $repository = new UserRepository();

        $result = $repository->getAll();
        
        $this->assertCount(3, $result);
        $result->each(function (User $user) {
            $this->assertInstanceOf(User::class, $user);
        });
    }

    /**
     * @return void
     */
    public function testGetUsersWithCustomerCounts(): void
    {
        User::factory(3)->create();
        $repository = new UserRepository();

        $result = $repository->getUsersWithCustomerCounts();
        
        $this->assertCount(3, $result);
        $result->each(function (User $user) {
            $this->assertTrue(isset($user->customers_count));
            $this->assertIsInt($user->customers_count);
        });
    }

    /**
     * @return void
     */
    public function testGetRecentUsers(): void
    {
        // Create users with different timestamps
        User::factory(15)->create()->each(function ($user, $index) {
            $user->update(['created_at' => now()->subMinutes($index)]);
        });

        $repository = new UserRepository();

        // Test default limit
        $result = $repository->getRecentUsers();
        $this->assertCount(10, $result);

        // Test custom limit
        $result = $repository->getRecentUsers(5);
        $this->assertCount(5, $result);

        // Verify ordering (most recent first)
        $this->assertTrue($result->first()->created_at >= $result->last()->created_at);
    }

    /**
     * @return void
     */
    public function testGetUsersByVerificationStatus(): void
    {
        User::factory(3)->create(['email_verified_at' => now()]);
        User::factory(2)->create(['email_verified_at' => null]);
        
        $repository = new UserRepository();

        // Test verified users
        $result = $repository->getUsersByVerificationStatus(true);
        $this->assertCount(3, $result);
        $result->each(function (User $user) {
            $this->assertNotNull($user->email_verified_at);
        });

        // Test unverified users
        $result = $repository->getUsersByVerificationStatus(false);
        $this->assertCount(2, $result);
        $result->each(function (User $user) {
            $this->assertNull($user->email_verified_at);
        });
    }

    /**
     * @return void
     */
    public function testGetCount(): void
    {
        User::factory(7)->create();
        $repository = new UserRepository();

        $count = $repository->getCount();
        $this->assertSame(7, $count);
    }

    /**
     * @return void
     */
    public function testSearch(): void
    {
        User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
        ]);

        User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@example.com',
        ]);

        User::factory()->create([
            'first_name' => 'Bob',
            'last_name' => 'Johnson',
            'email' => 'bob.johnson@example.com',
        ]);

        $repository = new UserRepository();

        // Test search by name
        $result = $repository->search('John');
        $this->assertCount(2, $result); // John Doe and Bob Johnson

        // Test search by email
        $result = $repository->search('jane.smith');
        $this->assertCount(1, $result);
        $this->assertSame('jane.smith@example.com', $result->first()->email);

        // Test custom limit
        $result = $repository->search('John', 1);
        $this->assertCount(1, $result);

        // Test no results
        $result = $repository->search('nonexistent');
        $this->assertCount(0, $result);
    }

    /**
     * @return void
     */
    public function testEmailExists(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $repository = new UserRepository();

        // Test existing email
        $this->assertTrue($repository->emailExists('test@example.com'));

        // Test non-existing email
        $this->assertFalse($repository->emailExists('nonexistent@example.com'));

        // Test with exclude ID
        $this->assertFalse($repository->emailExists('test@example.com', $user->id));

        // Test with different exclude ID
        $this->assertTrue($repository->emailExists('test@example.com', 99999));
    }
}