<?php

namespace App\Exceptions\Mail;

use Exception;

/**
 * Raised when outbound mail is blocked by the MAIL_SEND_ONLY_ACCEPT allowlist.
 *
 * Expected in non-production environments; should be logged as warning, not error.
 */
class EmailSendingBlockedException extends Exception {}
