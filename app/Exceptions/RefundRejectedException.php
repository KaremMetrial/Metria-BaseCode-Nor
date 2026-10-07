<?php

namespace App\Exceptions;

use RuntimeException;

/** The provider confirms that this refund request did not create a refund. */
final class RefundRejectedException extends RuntimeException {}
