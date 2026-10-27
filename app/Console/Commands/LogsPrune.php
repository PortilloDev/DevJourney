<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AppLog;
use App\Services\Rag\IpHasher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * Deletes application logs older than the retention window, optionally
 * exporting them first.
 *
 * The export is written to `storage/logs/exports` (outside the public disk),
 * compressed and encrypted with the application key, with restrictive file
 * permissions. By default personal data is minimised: the IP is replaced by a
 * one-way hash, the URL is reduced to path + method and the free-form context
 * and stack trace are dropped. `--full` keeps the raw values, still encrypted.
 */
class LogsPrune extends Command
{
    protected $signature = 'logs:prune
        {--days= : Retention window in days (defaults to logging.retention_days)}
        {--export : Dump the deleted rows to an encrypted file before removing them}
        {--full : Keep raw PII in the export (still encrypted at rest)}';

    protected $description = 'Delete application logs older than the retention window, optionally exporting them first.';

    public function handle(IpHasher $hasher): int
    {
        $days = $this->option('days') !== null && $this->option('days') !== ''
            ? (int) $this->option('days')
            : (int) config('logging.retention_days', 30);

        $cutoff = Carbon::now()->subDays($days);

        if ($this->option('export')) {
            $this->export($hasher, $cutoff, (bool) $this->option('full'));
        }

        $deleted = 0;

        do {
            $count = AppLog::query()
                ->where('created_at', '<', $cutoff)
                ->limit(1000)
                ->delete();
            $deleted += $count;
        } while ($count > 0);

        $this->pruneExports();

        $this->info("Deleted {$deleted} log(s) older than {$days} days.");

        return self::SUCCESS;
    }

    private function export(IpHasher $hasher, Carbon $cutoff, bool $full): void
    {
        $records = [];

        AppLog::query()
            ->where('created_at', '<', $cutoff)
            ->select(['id', 'level', 'message', 'context', 'exception', 'trace', 'url', 'method', 'ip_address', 'user_agent', 'user_id', 'created_at'])
            ->chunkById(1000, function ($logs) use (&$records, $hasher, $full): void {
                foreach ($logs as $log) {
                    $records[] = $this->toExport($log, $hasher, $full);
                }
            });

        if ($records === []) {
            $this->line('Nothing to export.');

            return;
        }

        $directory = storage_path('logs/exports');

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            $this->error("Could not create export directory [{$directory}].");

            return;
        }

        $payload = Crypt::encryptString(gzencode(json_encode($records, JSON_UNESCAPED_UNICODE), 9));
        $path = $directory.'/app_logs-'.now()->format('Y-m-d').'.json.gz.enc';

        if (file_put_contents($path, $payload) === false) {
            $this->error("Could not write export file [{$path}].");

            return;
        }

        chmod($path, 0600);

        $this->info('Exported '.count($records)." log(s) to {$path}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function toExport(AppLog $log, IpHasher $hasher, bool $full): array
    {
        $row = [
            'id' => $log->id,
            'level' => $log->level,
            'message' => $log->message,
            'exception' => $log->exception,
            'path' => $this->pathFromUrl($log->url),
            'method' => $log->method,
            'user_id' => $log->user_id,
            'created_at' => $log->created_at->toIso8601String(),
        ];

        if ($full) {
            $row['ip_address'] = $log->ip_address;
            $row['user_agent'] = $log->user_agent;
            $row['url'] = $log->url;
            $row['context'] = $log->context;
            $row['trace'] = $log->trace;
        } else {
            $row['ip_hash'] = $log->ip_address !== null ? $hasher->hash($log->ip_address) : null;
        }

        return $row;
    }

    private function pathFromUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) ? $path : null;
    }

    private function pruneExports(): void
    {
        $directory = storage_path('logs/exports');

        if (! is_dir($directory)) {
            return;
        }

        $retention = (int) config('logging.export_retention_days', 90);
        $cutoff = now()->subDays($retention)->getTimestamp();
        $removed = 0;

        foreach (glob($directory.'/app_logs-*.json.gz.enc') ?: [] as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->info("Removed {$removed} stale export(s).");
        }
    }
}
