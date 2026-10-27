<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppLog;
use App\Services\Rag\IpHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Tests\TestCase;

class LogPruneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanExports();
    }

    protected function tearDown(): void
    {
        $this->cleanExports();
        parent::tearDown();
    }

    private function cleanExports(): void
    {
        $directory = storage_path('logs/exports');

        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.'/app_logs-*.json.gz.enc') ?: [] as $file) {
            @unlink($file);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function log(array $overrides = []): AppLog
    {
        return AppLog::forceCreate(array_merge([
            'level' => 'error',
            'message' => 'Something broke',
            'context' => ['token' => 'secret'],
            'exception' => RuntimeException::class,
            'trace' => '#0 /app/foo.php(12)',
            'url' => 'https://example.com/posts/foo?token=abc123',
            'method' => 'GET',
            'ip_address' => '203.0.113.7',
            'user_agent' => 'Mozilla/5.0 (Test)',
            'created_at' => now(),
        ], $overrides));
    }

    public function test_old_logs_are_deleted_and_recent_kept(): void
    {
        $old = $this->log(['created_at' => now()->subDays(40)]);
        $recent = $this->log();

        $this->artisan('logs:prune', ['--days' => 30])->assertSuccessful();

        $this->assertDatabaseMissing('app_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('app_logs', ['id' => $recent->id]);
    }

    public function test_export_writes_an_encrypted_minimised_dump(): void
    {
        $this->log(['created_at' => now()->subDays(40)]);
        $this->log();

        $this->artisan('logs:prune', ['--days' => 30, '--export' => true])->assertSuccessful();

        $file = $this->exportFile();
        $this->assertNotNull($file);

        $raw = (string) file_get_contents($file);
        $this->assertStringNotContainsString('Something broke', $raw);

        $records = json_decode(gzdecode(Crypt::decryptString($raw)), true);
        $this->assertIsArray($records);
        $this->assertCount(1, $records);

        $record = $records[0];
        $this->assertSame('Something broke', $record['message']);
        $this->assertSame('/posts/foo', $record['path']);
        $this->assertSame('GET', $record['method']);
        $this->assertArrayNotHasKey('ip_address', $record);
        $this->assertArrayNotHasKey('user_agent', $record);
        $this->assertArrayNotHasKey('url', $record);
        $this->assertArrayNotHasKey('context', $record);
        $this->assertArrayNotHasKey('trace', $record);
        $this->assertSame(app(IpHasher::class)->hash('203.0.113.7'), $record['ip_hash']);
    }

    public function test_full_export_keeps_the_raw_fields_encrypted(): void
    {
        $this->log(['created_at' => now()->subDays(40)]);

        $this->artisan('logs:prune', ['--days' => 30, '--export' => true, '--full' => true])->assertSuccessful();

        $file = $this->exportFile();
        $this->assertNotNull($file);

        $records = json_decode(gzdecode(Crypt::decryptString((string) file_get_contents($file))), true);
        $record = $records[0];

        $this->assertSame('203.0.113.7', $record['ip_address']);
        $this->assertSame('Mozilla/5.0 (Test)', $record['user_agent']);
        $this->assertStringContainsString('?token=abc123', $record['url']);
        $this->assertSame(['token' => 'secret'], $record['context']);
        $this->assertSame('#0 /app/foo.php(12)', $record['trace']);
    }

    public function test_stale_exports_are_removed(): void
    {
        $directory = storage_path('logs/exports');

        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        $stale = $directory.'/app_logs-'.now()->subDays(120)->format('Y-m-d').'.json.gz.enc';
        file_put_contents($stale, 'x');
        touch($stale, now()->subDays(120)->getTimestamp());

        $this->log(['created_at' => now()->subDays(40)]);

        $this->artisan('logs:prune', ['--days' => 30])->assertSuccessful();

        $this->assertFileDoesNotExist($stale);
    }

    private function exportFile(): ?string
    {
        $files = glob(storage_path('logs/exports/app_logs-*.json.gz.enc')) ?: [];

        return $files[0] ?? null;
    }
}
