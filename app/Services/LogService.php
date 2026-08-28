<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Structured application logging. Every call is written through the default
 * log stack, which includes the `applog` channel that persists records to the
 * database for review in the admin panel.
 */
class LogService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $message, array $context = []): void
    {
        Log::info($message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $message, array $context = []): void
    {
        Log::warning($message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $message, array $context = []): void
    {
        Log::error($message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function critical(string $message, array $context = []): void
    {
        Log::critical($message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function exception(Throwable $e, string $message = 'Unhandled exception', array $context = []): void
    {
        Log::error($message, array_merge($context, ['exception' => $e]));
    }

    /**
     * Record a business audit event, e.g. a model being created/updated/deleted
     * or a security-related action such as a login.
     *
     * @param  array<string, mixed>  $context
     */
    public function audit(string $action, ?Model $subject = null, array $context = []): void
    {
        $context = [
            'action' => $action,
            'subject' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
        ] + $context;

        Log::info("audit: {$action}", $context);
    }
}
