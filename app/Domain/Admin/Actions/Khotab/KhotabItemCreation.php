<?php

namespace App\Domain\Admin\Actions\Khotab;

/**
 * What one «إضافة مادة» submission created (multi-author creation, 2026-11-25): the primary item, the independent
 * copies made for additional sheikhs, and — after the commit — which records did not get an archive.org image.
 */
final class KhotabItemCreation
{
    /**
     * @param  array<int, int>  $copies  additional author id => new item id, in ascending author id
     * @param  array<int, list<string>>  $missingImages  item id => image kinds ('gif', 'frame') that could not be stored
     */
    public function __construct(
        public readonly int $id,
        public readonly array $copies = [],
        public readonly array $missingImages = [],
    ) {}

    public function hasCopies(): bool
    {
        return $this->copies !== [];
    }

    /** @return list<int> every created item id, the primary first */
    public function ids(): array
    {
        return [$this->id, ...array_values($this->copies)];
    }
}
