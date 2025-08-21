<?php

namespace Tests\Integration\Repository;

use App\Models\Export;
use App\Models\User;
use App\Repositories\ExportRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ExportRepository::class)]
class ExportRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    public function testGetAllForUser(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        // Create exports for the user
        Export::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'format' => 'csv',
            'created_at' => now()->subDays(2),
        ]);

        Export::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'format' => 'xlsx',
            'created_at' => now()->subDay(),
        ]);

        // Create export for another user (should not be returned)
        Export::factory()->create(['user_id' => $anotherUser->id]);

        $repository = new ExportRepository();

        // Test basic getAllForUser
        $result = $repository->getAllForUser($user);
        $this->assertCount(2, $result);
        $result->each(function (Export $export) use ($user) {
            $this->assertSame($user->id, $export->user_id);
        });

        // Test with status filter
        $result = $repository->getAllForUser($user, ['status' => 'completed']);
        $this->assertCount(1, $result);
        $this->assertSame('completed', $result->first()->status);

        // Test with format filter
        $result = $repository->getAllForUser($user, ['format' => 'csv']);
        $this->assertCount(1, $result);
        $this->assertSame('csv', $result->first()->format);

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

        // Test downloadable filter with a fresh user to avoid interference
        $freshUser = User::factory()->create();
        Export::factory()->create([
            'user_id' => $freshUser->id,
            'status' => 'completed',
            'download_url' => 'https://example.com/download/123',
            'expires_at' => now()->addDays(5),
        ]);

        $result = $repository->getAllForUser($freshUser, ['downloadable' => true]);
        $this->assertCount(1, $result);
        $this->assertSame('completed', $result->first()->status);
        $this->assertNotNull($result->first()->download_url);
    }

    /**
     * @return void
     */
    public function testGetPaginatedForUser(): void
    {
        $user = User::factory()->create();

        // Create multiple exports
        Export::factory(20)->create(['user_id' => $user->id]);

        $repository = new ExportRepository();

        // Test default pagination
        $result = $repository->getPaginatedForUser($user);
        $this->assertCount(15, $result);
        $this->assertSame(20, $result->total());

        // Test custom per page
        $result = $repository->getPaginatedForUser($user, [], 10);
        $this->assertCount(10, $result);

        // Test with filters
        Export::factory(5)->create([
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

        $export = Export::factory()->create(['user_id' => $user->id]);
        $anotherExport = Export::factory()->create(['user_id' => $anotherUser->id]);

        $repository = new ExportRepository();

        // Should find user's export
        $result = $repository->findForUser($user, $export->id);
        $this->assertNotNull($result);
        $this->assertSame($export->id, $result->id);

        // Should not find another user's export
        $result = $repository->findForUser($user, $anotherExport->id);
        $this->assertNull($result);

        // Should return null for non-existent export
        $result = $repository->findForUser($user, 99999);
        $this->assertNull($result);
    }

    /**
     * @return void
     */
    public function testCreateForUser(): void
    {
        $user = User::factory()->create();
        $repository = new ExportRepository();

        $exportData = [
            'filename' => 'test_export.csv',
            'type' => 'filtered',
            'format' => 'csv',
            'status' => 'pending',
            'total_records' => 100,
        ];

        $export = $repository->createForUser($user, $exportData);

        $this->assertInstanceOf(Export::class, $export);
        $this->assertSame($user->id, $export->user_id);
        $this->assertSame('test_export.csv', $export->filename);
        $this->assertSame('filtered', $export->type);
        $this->assertSame('csv', $export->format);
        $this->assertSame('pending', $export->status);
        $this->assertSame(100, $export->total_records);
        $this->assertDatabaseHas('exports', [
            'user_id' => $user->id,
            'filename' => 'test_export.csv',
            'format' => 'csv',
        ]);
    }

    /**
     * @return void
     */
    public function testUpdateForUser(): void
    {
        $user = User::factory()->create();
        $export = Export::factory()->create(['user_id' => $user->id]);
        $repository = new ExportRepository();

        $updateData = [
            'status' => 'completed',
            'file_path' => 'exports/completed_export.csv',
            'download_url' => 'https://example.com/download/123',
        ];

        $updatedExport = $repository->updateForUser($user, $export, $updateData);

        $this->assertInstanceOf(Export::class, $updatedExport);
        $this->assertSame($export->id, $updatedExport->id);
        $this->assertSame('completed', $updatedExport->status);
        $this->assertSame('exports/completed_export.csv', $updatedExport->file_path);
        $this->assertSame('https://example.com/download/123', $updatedExport->download_url);
        $this->assertDatabaseHas('exports', [
            'id' => $export->id,
            'status' => 'completed',
            'file_path' => 'exports/completed_export.csv',
        ]);
    }

    /**
     * @return void
     */
    public function testUpdateForUserThrowsExceptionForWrongUser(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();
        $export = Export::factory()->create(['user_id' => $anotherUser->id]);
        $repository = new ExportRepository();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Export does not belong to the specified user');

        $repository->updateForUser($user, $export, ['status' => 'completed']);
    }

    /**
     * @return void
     */
    public function testGetByStatusForUser(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        Export::factory(2)->create([
            'user_id' => $user->id,
            'status' => 'completed'
        ]);

        Export::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending'
        ]);

        Export::factory()->create([
            'user_id' => $anotherUser->id,
            'status' => 'completed'
        ]);

        $repository = new ExportRepository();

        $result = $repository->getByStatusForUser($user, 'completed');
        $this->assertCount(2, $result);
        $result->each(function (Export $export) use ($user) {
            $this->assertSame($user->id, $export->user_id);
            $this->assertSame('completed', $export->status);
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

        // Create exports with different timestamps
        Export::factory(15)->create([
            'user_id' => $user->id,
        ])->each(function ($export, $index) {
            $export->update(['created_at' => now()->subMinutes($index)]);
        });

        Export::factory()->create(['user_id' => $anotherUser->id]);

        $repository = new ExportRepository();

        // Test default limit
        $result = $repository->getRecentForUser($user);
        $this->assertCount(10, $result);

        // Test custom limit
        $result = $repository->getRecentForUser($user, 5);
        $this->assertCount(5, $result);

        // Verify ordering (most recent first)
        $this->assertTrue($result->first()->created_at >= $result->last()->created_at);

        // Ensure only user's exports are returned
        $result->each(function (Export $export) use ($user) {
            $this->assertSame($user->id, $export->user_id);
        });
    }

    /**
     * @return void
     */
    public function testGetCountForUser(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        Export::factory(3)->create(['user_id' => $user->id]);
        Export::factory(2)->create(['user_id' => $anotherUser->id]);

        $repository = new ExportRepository();

        $count = $repository->getCountForUser($user);
        $this->assertSame(3, $count);

        $anotherCount = $repository->getCountForUser($anotherUser);
        $this->assertSame(2, $anotherCount);
    }

    /**
     * @return void
     */
    public function testGetDownloadableForUser(): void
    {
        $user = User::factory()->create();

        // Downloadable export (completed, not expired, has download_url)
        $downloadableExport = Export::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'download_url' => 'https://example.com/download/123',
            'expires_at' => now()->addDays(5),
        ]);

        // Not downloadable - not completed
        Export::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'download_url' => 'https://example.com/download/456',
            'expires_at' => now()->addDays(5),
        ]);

        // Not downloadable - expired
        Export::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'download_url' => 'https://example.com/download/789',
            'expires_at' => now()->subDay(),
        ]);

        // Not downloadable - no download_url
        Export::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'download_url' => null,
            'expires_at' => now()->addDays(5),
        ]);

        // Downloadable - no expiry date
        $downloadableExportNoExpiry = Export::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'download_url' => 'https://example.com/download/999',
            'expires_at' => null,
        ]);

        $repository = new ExportRepository();

        $result = $repository->getDownloadableForUser($user);
        $this->assertCount(2, $result);

        $downloadableIds = $result->pluck('id')->toArray();
        $this->assertContains($downloadableExport->id, $downloadableIds);
        $this->assertContains($downloadableExportNoExpiry->id, $downloadableIds);
    }

    /**
     * @return void
     */
    public function testGetExpiredExports(): void
    {
        $user = User::factory()->create();

        // Expired export with file
        $expiredExport = Export::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->subDay(),
            'file_path' => 'exports/expired.csv',
        ]);

        // Expired export without file (should not be included)
        Export::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->subDay(),
            'file_path' => null,
        ]);

        // Not expired export
        Export::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->addDay(),
            'file_path' => 'exports/not_expired.csv',
        ]);

        $repository = new ExportRepository();

        $result = $repository->getExpiredExports();
        $this->assertCount(1, $result);
        $this->assertSame($expiredExport->id, $result->first()->id);
    }

    /**
     * @return void
     */
    public function testCleanupExpiredExports(): void
    {
        $user = User::factory()->create();

        // Create a temporary file to simulate export file
        $testFilePath = storage_path('app/test_export.csv');
        file_put_contents($testFilePath, 'test content');

        // Expired export with existing file
        $expiredExport = Export::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->subDay(),
            'file_path' => 'test_export.csv',
            'download_url' => 'https://example.com/download/123',
            'status' => 'completed',
        ]);

        // Expired export with non-existing file
        $expiredExportNoFile = Export::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->subDay(),
            'file_path' => 'non_existent.csv',
            'download_url' => 'https://example.com/download/456',
            'status' => 'completed',
        ]);

        $repository = new ExportRepository();

        $cleanedCount = $repository->cleanupExpiredExports();
        $this->assertSame(2, $cleanedCount);

        // Verify file was deleted
        $this->assertFalse(file_exists($testFilePath));

        // Verify database records were updated
        $expiredExport->refresh();
        $this->assertNull($expiredExport->file_path);
        $this->assertNull($expiredExport->download_url);
        $this->assertSame('failed', $expiredExport->status);

        $expiredExportNoFile->refresh();
        $this->assertNull($expiredExportNoFile->file_path);
        $this->assertNull($expiredExportNoFile->download_url);
        $this->assertSame('failed', $expiredExportNoFile->status);
    }
}
