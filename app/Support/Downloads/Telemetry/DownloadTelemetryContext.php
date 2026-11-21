<?php

namespace App\Support\Downloads\Telemetry;

/** What a download is, as the controller already knows it (module, resource, variant). Plain values only. */
final class DownloadTelemetryContext
{
    public function __construct(
        public readonly string $module,
        public readonly string $resourceType,
        public readonly int $resourceId,
        public readonly string $variant,
        public readonly ?int $variantId = null,
    ) {}
}
