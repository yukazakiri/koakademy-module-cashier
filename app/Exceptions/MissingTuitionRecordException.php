<?php

declare(strict_types=1);

namespace Modules\Cashier\Exceptions;

use RuntimeException;

/**
 * Raised when a tuition payment is posted for a student period that has no
 * tuition assessment to apply it to.
 */
final class MissingTuitionRecordException extends RuntimeException {}
