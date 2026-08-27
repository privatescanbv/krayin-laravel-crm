<?php

namespace App\Observers;

use App\Models\AnamnesisGvlForm;
use App\Services\Anamnesis\GvlAuditLogger;

class AnamnesisGvlFormObserver
{
    public function __construct(private readonly GvlAuditLogger $logger) {}

    public function created(AnamnesisGvlForm $form): void
    {
        $this->logger->log($form, 'aangemaakt');
    }

    public function deleted(AnamnesisGvlForm $form): void
    {
        $this->logger->log($form, 'verwijderd');
    }
}
