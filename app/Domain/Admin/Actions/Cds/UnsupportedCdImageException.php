<?php

namespace App\Domain\Admin\Actions\Cds;

use InvalidArgumentException;

/**
 * A CD release image whose server-detected type is not unambiguously JPEG, PNG or GIF (S1, P0 2026-11-09).
 * Extends InvalidArgumentException so existing cleanup paths treat it like any other rejected input.
 */
final class UnsupportedCdImageException extends InvalidArgumentException {}
