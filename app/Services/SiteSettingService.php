<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Central accessor for key/value site settings. Values are cached so public
 * pages avoid a DB round-trip for every setting lookup.
 */
class SiteSettingService
{
    private const CACHE_KEY = 'site_settings.all';

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return SiteSetting::query()
                ->pluck('value', 'key')
                ->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * Whether the public chat API is blocked (403 to callers, widget hidden).
     */
    public function chatApiBlocked(): bool
    {
        return (bool) $this->get('chat_api_blocked', false);
    }

    /**
     * Whether the chat widget is hidden on the public site (API still enabled).
     */
    public function chatHidden(): bool
    {
        return (bool) $this->get('chat_hidden', false);
    }

    /**
     * Whether any visitor-facing chat UI should be shown at all.
     */
    public function chatVisible(): bool
    {
        return ! $this->chatApiBlocked() && ! $this->chatHidden();
    }

    public function set(string $key, mixed $value, string $group = 'general', string $type = 'text'): void
    {
        SiteSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group, 'type' => $type],
        );

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
