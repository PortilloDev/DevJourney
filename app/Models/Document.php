<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An uploaded reference document (PDF) that is indexed into the vector store.
 *
 * @property int $id
 * @property string $title
 * @property int|null $post_id
 * @property string $filename
 * @property string $path
 * @property string|null $mime_type
 * @property int $size
 * @property int|null $pages
 * @property string $status
 * @property Carbon|null $indexed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Document extends Model
{
    protected $fillable = [
        'title',
        'post_id',
        'filename',
        'path',
        'mime_type',
        'size',
        'pages',
        'status',
        'indexed_at',
    ];

    protected function casts(): array
    {
        return [
            'post_id' => 'integer',
            'size' => 'integer',
            'pages' => 'integer',
            'indexed_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
