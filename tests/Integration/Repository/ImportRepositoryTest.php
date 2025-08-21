<?php

namespace Tests\Integration\Repository;

use App\Models\Import;
use App\Models\User;
use App\Repositories\ImportRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ImportRepository::class)]
class ImportRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    public function testGetAllForUser(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        // Create imports for the user
        Import::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'created_at' => now()->subDays(2),
        ]);

        Import::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'created_at' => now()->subDay(),
        ]);

        // Create import for another user (should not be returned)
        Import::factory()->create(['user_id' => $anotherUser->id]);

        $repository = new ImportRepository();

        // Test basic getAllForUser
        $result = $repository->getAllForUser($user);
        $this->assertCount(2, $result);
        $result->each(function (Import $import) use ($user) {
            $this->assertSame($user->id, $import->user_id);
        });

        // Test with status filter
        $result = $repository->getAllForUser($user, ['status' => 'completed']);
        $this->assertCount(1, $result);
        $this->assertSame('completed', $result->first()->status);

        // Test with date filters
        $result = $repository->getAllForUser($user, [
            'date_from' => now()->subDay()->subHour(),
            'date_to' => now()->addHour(),
        ]);
        $this->assertCount(1, $result);
        $this->assertSame('pending', $result->first()->status);

        // Test with limit
        $result = $repository->getAllForUser($user, ['limit' => 1]);
        $this->assertCount(1, $result);

        // Test with sorting
        $result = $repository->getAllForUser($user, [
            'sort_by' => 'created_at',
            'sort_direction' => 'asc'
        ]);
        $this->assertCount(2, $result);
        $this->assertTrue($result->first()->created_at <= $result->last()->created_at);

        // Test with invalid sort_by field (should fall back to latest())
        $result = $repository->getAllForUser($user, [
            'sort_by' => 'invalid_field',
            'sort_direction' => 'asc'
        ]);
        $this->assertCount(2, $result);
        // Should be ordered by created_at desc (latest) regardless of sort_direction
        $this->assertTrue($result->first()->created_at >= $result->last()->created_at);
    }

    /**
     * @return void
     */
    public function testGetPaginatedForUser(): void
    {
        $user = User::factory()->create();

        // Create multiple imports
        Import::factory(20)->create(['user_id' => $user->id]);

        $repository = new ImportRepository();

        // Test default pagination
        $result = $repository->getPaginatedForUser($user);
        $this->assertCount(15, $result);
        $this->assertSame(20, $result->total());

        // Test custom per page
        $result = $repository->getPaginatedForUser($user, [], 10);
        $this->assertCount(10, $result);

        // Test with filters
        Import::factory(5)->create([
            'user_id' => $user->id,
            'status' => 'failed'
        ]);

        $result = $repository->getPaginatedForUser($user, ['status' => 'failed'], 5);
        $this->assertCount(5, $result);
    }

    /**
     * @return void
     */
    public function testFindForUser(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        $import = Import::factory()->create(['user_id' => $user->id]);
        $anotherImport = Import::factory()->create(['user_id' => $anotherUser->id]);

        $repository = new ImportRepository();

        // Should find user's import
        $result = $repository->findForUser($user, $import->id);
        $this->assertNotNull($result);
        $this->assertSame($import->id, $result->id);

        // Should not find another user's import
        $result = $repository->findForUser($user, $anotherImport->id);
        $this->assertNull($result);

        // Should return null for non-existent import
        $result = $repository->findForUser($user, 99999);
        $this->assertNull($result);
    }

    /**
     * @return void
     */
    public function testCreateForUser(): void
    {
        $user = User::factory()->create();
        $repository = new ImportRepository();

        $importData = [
            'filename' => 'test_import.csv',
            'original_filename' => 'customers.csv',
            'status' => 'pending',
            'total_rows' => 100,
            'processed_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
            'file_path' => 'imports/test_import.csv',
        ];

        $import = $repository->createForUser($user, $importData);

        $this->assertInstanceOf(Import::class, $import);
        $this->assertSame($user->id, $import->user_id);
        $this->assertSame('test_import.csv', $import->filename);
        $this->assertSame('customers.csv', $import->original_filename);
        $this->assertSame('pending', $import->status);
        $this->assertSame(100, $import->total_rows);
        $this->assertSame('imports/test_import.csv', $import->file_path);
        $this->assertDatabaseHas('imports', [
            'user_id' => $user->id,
            'filename' => 'test_import.csv',
            'status' => 'pending',
        ]);
    }

    /**
     * @return void
     */
    public function testUpdateForUser(): void
    {
        $user = User::factory()->create();
        $import = Import::factory()->create(['user_id' => $user->id]);
        $repository = new ImportRepository();

        $updateData = [
            'status' => 'completed',
            'processed_rows' => 100,
            'successful_rows' => 95,
            'failed_rows' => 5,
            'completed_at' => now(),
        ];

        $updatedImport = $repository->updateForUser($user, $import, $updateData);

        $this->assertInstanceOf(Import::class, $updatedImport);
        $this->assertSame($import->id, $updatedImport->id);
        $this->assertSame('completed', $updatedImport->status);
        $this->assertSame(100, $updatedImport->processed_rows);
        $this->assertSame(95, $updatedImport->successful_rows);
        $this->assertSame(5, $updatedImport->failed_rows);
        $this->assertNotNull($updatedImport->completed_at);
        $this->assertDatabaseHas('imports', [
            'id' => $import->id,
            'status' => 'completed',
            'successful_rows' => 95,
        ]);
    }

    /**
     * @return void
     */
    public function testUpdateForUserThrowsExceptionForWrongUser(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();
        $import = Import::factory()->create(['user_id' => $anotherUser->id]);
        $repository = new ImportRepository();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Import does not belong to the specified user');

        $repository->updateForUser($user, $import, ['status' => 'completed']);
    }

    /**
     * @return void
     */
    public function testGetByStatusForUser(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        Import::factory(2)->create([
            'user_id' => $user->id,
            'status' => 'completed'
        ]);

        Import::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending'
        ]);

        Import::factory()->create([
            'user_id' => $anotherUser->id,
            'status' => 'completed'
        ]);

        $repository = new ImportRepository();

        $result = $repository->getByStatusForUser($user, 'completed');
        $this->assertCount(2, $result);
        $result->each(function (Import $import) use ($user) {
            $this->assertSame($user->id, $import->user_id);
            $this->assertSame('completed', $import->status);
        });

        $result = $repository->getByStatusForUser($user, 'pending');
        $this->assertCount(1, $result);
        $this->assertSame('pending', $result->first()->status);
    }

    /**
     * @return void
     */
    public function testGetRecentForUser(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        // Create imports with different timestamps
        Import::factory(15)->create([
            'user_id' => $user->id,
        ])->each(function ($import, $index) {
            $import->update(['created_at' => now()->subMinutes($index)]);
        });

        Import::factory()->create(['user_id' => $anotherUser->id]);

        $repository = new ImportRepository();

        // Test default limit
        $result = $repository->getRecentForUser($user);
        $this->assertCount(10, $result);

        // Test custom limit
        $result = $repository->getRecentForUser($user, 5);
        $this->assertCount(5, $result);

        // Verify ordering (most recent first)
        $this->assertTrue($result->first()->created_at >= $result->last()->created_at);

        // Ensure only user's imports are returned
        $result->each(function (Import $import) use ($user) {
            $this->assertSame($user->id, $import->user_id);
        });
    }

    /**
     * @return void
     */
    public function testGetCountForUser(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        Import::factory(3)->create(['user_id' => $user->id]);
        Import::factory(2)->create(['user_id' => $anotherUser->id]);

        $repository = new ImportRepository();

        $count = $repository->getCountForUser($user);
        $this->assertSame(3, $count);

        $anotherCount = $repository->getCountForUser($anotherUser);
        $this->assertSame(2, $anotherCount);
    }

    /**
     * @return void
     */
    public function testGetCompletedForUser(): void
    {
        $user = User::factory()->create();

        Import::factory(2)->create([
            'user_id' => $user->id,
            'status' => 'completed'
        ]);

        Import::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending'
        ]);

        Import::factory()->create([
            'user_id' => $user->id,
            'status' => 'failed'
        ]);

        $repository = new ImportRepository();

        $result = $repository->getCompletedForUser($user);
        $this->assertCount(2, $result);
        $result->each(function (Import $import) use ($user) {
            $this->assertSame($user->id, $import->user_id);
            $this->assertSame('completed', $import->status);
        });
    }

    /**
     * @return void
     */
    public function testGetFailedForUser(): void
    {
        $user = User::factory()->create();

        Import::factory(2)->create([
            'user_id' => $user->id,
            'status' => 'failed'
        ]);

        Import::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed'
        ]);

        Import::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending'
        ]);

        $repository = new ImportRepository();

        $result = $repository->getFailedForUser($user);
        $this->assertCount(2, $result);
        $result->each(function (Import $import) use ($user) {
            $this->assertSame($user->id, $import->user_id);
            $this->assertSame('failed', $import->status);
        });
    }
}
