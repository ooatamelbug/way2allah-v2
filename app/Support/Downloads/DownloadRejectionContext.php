<?php

namespace App\Support\Downloads;

/**
 * What a refused download was, as the caller already knows it — the row it
 * loaded before asking the guard for a slot. Built from plain values only:
 * nothing here may be fetched, opened or probed to fill it, because a refused
 * request must cost less than an accepted one.
 *
 * - `module`: the content area (`khotab`, …).
 * - `resourceType` / `resourceId`: what the visitor asked for. For a mirror
 *   this is the PARENT item, so refusals per item stay one index lookup.
 * - `variant` / `variantId`: which delivery of it (`primary`, `mirror` + the
 *   mirror id, …).
 * - `resolvedDownloadUrl`: the stored link the transfer would have opened.
 * - `storedSize`: the size the database already records. Missing or zero
 *   means unknown — never looked up.
 */
final class DownloadRejectionContext
{
    public const SIZE_DB = 'db';

    public const SIZE_UNKNOWN = 'unknown';

    public readonly ?int $fileSize;

    public readonly string $fileSizeSource;

    public function __construct(
        public readonly string $module,
        public readonly string $resourceType,
        public readonly int $resourceId,
        public readonly string $variant,
        public readonly ?int $variantId = null,
        public readonly ?string $resolvedDownloadUrl = null,
        ?int $storedSize = null,
        // Phase 3D: the material's title at refusal time, from the record the controller already loaded (no lookup).
        public readonly ?string $resourceTitle = null,
    ) {
        $this->fileSize = $storedSize !== null && $storedSize > 0 ? $storedSize : null;
        $this->fileSizeSource = $this->fileSize !== null ? self::SIZE_DB : self::SIZE_UNKNOWN;
    }
}
