<?php

namespace App\Exceptions;

use RuntimeException;

class CannotMergePersonWithPortalException extends RuntimeException
{
    /**
     * @param  array<int, int|string>  $personIds
     */
    public static function forPersonIds(array $personIds): self
    {
        $list = implode(', ', $personIds);

        return new self(
            "Persoon/personen {$list} hebben een patiëntportaalaccount. Trek het account eerst in, of maak die persoon primair."
        );
    }
}
