<?php

namespace App\Domain\Content\Services;

use RuntimeException;

/**
 * Thrown by ThumbnailService for any client-caused rejection (bad src,
 * traversal attempt, missing/unsupported file, malformed dimensions).
 * Maps to a generic HTTP 400 in ThumbnailController — the message here is
 * for logs/tests only and must never be echoed back to the response body.
 */
class ThumbnailRejectedException extends RuntimeException
{
}
