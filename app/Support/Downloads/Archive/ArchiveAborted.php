<?php

namespace App\Support\Downloads\Archive;

use RuntimeException;

/** A run stopped deliberately by the archive code; its message is fixed text with no row data. */
final class ArchiveAborted extends RuntimeException {}
