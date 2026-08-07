<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\SiteSettingService;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    protected static function booted(): void
    {
        $flush = fn () => app(SiteSettingService::class)->flush();

        static::saved($flush);
        static::deleted($flush);
    }
}
