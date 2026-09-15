<?php

namespace App\Exceptions;

use RuntimeException;

class CannotDeleteLeadWithSalesException extends RuntimeException
{
    /**
     * @param  array<int, int|string>  $leadIds
     */
    public static function forLeadIds(array $leadIds): self
    {
        return new self(__('messages.lead.delete_blocked_sales', [
            'ids' => implode(', ', $leadIds),
        ]));
    }
}
