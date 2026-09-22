<?php

namespace App\Traits;

use App\Services\LeadStatusTransitionValidator;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

/**
 * For Lead and Sales lead
 */
trait SelectsBestContactPerson
{
    public function getContactPersonOrFirstPerson(): ?Person
    {
        if ($this->hasContactPerson()) {
            return $this->contactPerson;
        }

        $persons = $this->persons()->with('address')->get();
        if ($persons->isEmpty()) {
            return null;
        }

        $lead = $this->getLeadForScoring();
        if (! $lead) {
            return $persons->first();
        }

        $scored = $persons->map(fn (Person $person) => [
            'person' => $person,
            'score'  => LeadStatusTransitionValidator::calculateMatchScore($lead, $person),
        ]);

        return $scored
            ->sort(function (array $a, array $b): int {
                $byScore = $b['score'] <=> $a['score'];

                return $byScore !== 0 ? $byScore : $b['person']->id <=> $a['person']->id;
            })
            ->first()['person'];
    }

    /**
     * Emails to pre-fill when composing a mail for this entity: the best contact
     * person's emails, or none when no person is linked.
     *
     * @return array<int, mixed>
     */
    public function resolveDefaultEmails(): array
    {
        return $this->getContactPersonOrFirstPerson()?->emails ?? [];
    }

    abstract protected function getLeadForScoring(): ?Lead;
}
