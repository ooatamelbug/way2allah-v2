<?php

namespace App\Domain\Content\Services;

use RuntimeException;

/**
 * Thrown by ThumbnailService when ext-gd is not loaded. Distinct from
 * ThumbnailRejectedException because this is a server/runtime condition,
 * not a bad request — ThumbnailController maps it to HTTP 503, not 400.
 */
class ThumbnailGdUnavailableException extends RuntimeException
{
}
