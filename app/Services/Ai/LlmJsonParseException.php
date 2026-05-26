<?php

namespace App\Services\Ai;

use Exception;

class LlmJsonParseException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $rawContent,
        public readonly string $extractedJson,
    ) {
        parent::__construct($message);
    }
}
