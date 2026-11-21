<?php

namespace App\Support\Downloads\Telemetry\Aggregation;

use RuntimeException;

/**
 * A rollup run stopped deliberately by the telemetry code itself (e.g. the advisory lock's ownership was lost before
 * COMMIT). Its message is written by this code base and carries no event data, so — unlike any other exception — it
 * may appear in the run report (RollupRunner::describe()).
 */
final class RollupAborted extends RuntimeException {}
