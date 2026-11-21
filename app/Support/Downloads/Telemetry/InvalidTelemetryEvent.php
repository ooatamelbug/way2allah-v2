<?php

namespace App\Support\Downloads\Telemetry;

use InvalidArgumentException;

/** An event that does not satisfy the versioned schema (TelemetryEvent). The writer counts it as dropped. */
final class InvalidTelemetryEvent extends InvalidArgumentException {}
