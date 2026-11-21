<?php

namespace App\Domain\Admin\Actions\Staff;

use RuntimeException;

/** An administrator-management operation the rules refuse; the message is shown to the actor as-is. */
class AdminManagementRefused extends RuntimeException {}
