<?php

namespace App\Domain\Admin\Support\Constraints;

/**
 * The Khotab section (audio / video) an operation targets when there is no section-bearing record yet — the author-level
 * "add group / series / item" forms, whose `{media}` segment chooses the section (OLD `AvAdmin($vedio)` on insert).
 */
final class KhotabMediaTarget
{
    public function __construct(public readonly int $vedio) {}

    public static function fromMedia(string $media): self
    {
        return new self($media === 'video' ? 1 : 0);
    }
}
