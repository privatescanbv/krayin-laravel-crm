<?php

namespace App\Services\Ai;

use Exception;

/**
 * Thrown when the LLM backend returned a transient status (429/5xx) and the
 * retryTransient() budget was exhausted (or too small to fit a retry). Callers
 * should treat this like a ConnectionException: let the queue retry the job
 * instead of recording a permanent generation failure.
 */
class LlmTransientException extends Exception {}
