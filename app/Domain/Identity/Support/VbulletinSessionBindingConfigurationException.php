<?php

namespace App\Domain\Identity\Support;

use InvalidArgumentException;

/**
 * The forum session-binding configuration is malformed or unsafe (D1, 2026-11-09). The forum session-hash cookie is
 * then never accepted (fail closed); the message names the setting and the reason, never a secret.
 */
final class VbulletinSessionBindingConfigurationException extends InvalidArgumentException {}
