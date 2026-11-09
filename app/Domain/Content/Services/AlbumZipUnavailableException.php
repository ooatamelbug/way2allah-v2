<?php

namespace App\Domain\Content\Services;

use RuntimeException;

/**
 * An album ZIP cannot be offered: no valid image file remains, or the album exceeds the archive limits
 * (decision-log #107). The message is for logs only — callers answer visitors with a generic response.
 */
class AlbumZipUnavailableException extends RuntimeException {}
