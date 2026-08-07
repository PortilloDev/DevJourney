<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Small value-bag that public controllers populate and the layout renders into
 * <title>, meta description, Open Graph, Twitter cards and JSON-LD tags.
 */
class SeoMetaService
{
    public string $title = 'DevJourney';

    public string $description = '';

    public ?string $image = null;

    public string $type = 'website';

    public ?string $canonical = null;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $jsonLd = [];

    public function set(
        ?string $title = null,
        ?string $description = null,
        ?string $image = null,
        ?string $type = null,
        ?string $canonical = null,
    ): self {
        if ($title !== null) {
            $this->title = $title;
        }
        if ($description !== null) {
            $this->description = $description;
        }
        if ($image !== null) {
            $this->image = $image;
        }
        if ($type !== null) {
            $this->type = $type;
        }
        if ($canonical !== null) {
            $this->canonical = $canonical;
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addJsonLd(array $data): self
    {
        $this->jsonLd[] = $data;

        return $this;
    }

    public function fullTitle(string $siteName = 'DevJourney'): string
    {
        return $this->title === $siteName
            ? $this->title
            : "{$this->title} · {$siteName}";
    }
}
