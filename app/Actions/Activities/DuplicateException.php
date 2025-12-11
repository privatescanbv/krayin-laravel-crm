<?php

namespace App\Actions\Activities;

use Exception;

class DuplicateException extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
