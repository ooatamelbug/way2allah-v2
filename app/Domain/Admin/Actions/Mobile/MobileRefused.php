<?php

namespace App\Domain\Admin\Actions\Mobile;

use RuntimeException;

/** Phase 7 — an operation OLD refused (duplicate URL, duplicate category title, …). */
final class MobileRefused extends RuntimeException {}
