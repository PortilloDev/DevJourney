<?php

declare(strict_types=1);

namespace App\Logging;

use App\Models\AppLog;
use Illuminate\Support\Facades\Auth;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

/**
 * Writes every Monolog record into the `app_logs` table so application errors
 * and events can be reviewed from the admin panel.
 *
 * The write is wrapped in a try/catch so logging can never break the request —
 * if the database is unavailable the record is silently dropped.
 */
class DatabaseLogHandler extends AbstractProcessingHandler
{
    public function __construct(Level|int $level = Level::Info, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        try {
            $context = $this->stringifyExceptionlessContext($record->context);

            AppLog::create([
                'level' => strtolower($record->level->getName()),
                'message' => $record->message,
                'context' => $context ?: null,
                'exception' => $this->exceptionClass($record->context),
                'trace' => $this->exceptionTrace($record->context),
                'url' => $this->requestUrl(),
                'method' => $this->requestMethod(),
                'ip_address' => $this->requestIp(),
                'user_agent' => $this->requestUserAgent(),
                'user_id' => Auth::hasUser() ? Auth::id() : null,
            ]);
        } catch (Throwable) {
            // Ignore — logging must never throw.
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function stringifyExceptionlessContext(array $context): array
    {
        $result = [];

        foreach ($context as $key => $value) {
            if ($value instanceof Throwable) {
                continue;
            }

            $result[$key] = $this->stringify($value);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function exceptionClass(array $context): ?string
    {
        foreach ($context as $value) {
            if ($value instanceof Throwable) {
                return get_class($value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function exceptionTrace(array $context): ?string
    {
        foreach ($context as $value) {
            if ($value instanceof Throwable) {
                return $value->getTraceAsString();
            }
        }

        return null;
    }

    protected function stringify(mixed $value): mixed
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
        }

        return $value;
    }

    protected function requestUrl(): ?string
    {
        return request()->fullUrl();
    }

    protected function requestMethod(): ?string
    {
        return request()->method();
    }

    protected function requestIp(): ?string
    {
        return request()->ip();
    }

    protected function requestUserAgent(): ?string
    {
        $ua = request()->userAgent();

        return $ua === null ? null : substr($ua, 0, 255);
    }
}
