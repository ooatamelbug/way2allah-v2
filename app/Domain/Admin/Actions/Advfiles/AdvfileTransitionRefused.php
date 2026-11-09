<?php

namespace App\Domain\Admin\Actions\Advfiles;

use RuntimeException;

/** Phase 5 — a transition OLD refused (or never offered) from the item's current state. */
final class AdvfileTransitionRefused extends RuntimeException {}
