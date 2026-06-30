<?php

namespace App\Services\Mail;

use App\Models\Order;
use App\Models\SalesLead;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;

/**
 * Resolves the correct outbound mailbox for a given context.
 *
 * Central place for all mailbox-selection logic so blade templates stay thin
 * and the rules are testable in isolation.
 */
class MailboxResolver
{
    /**
     * Return configured mailboxes as a flat list for Vue components.
     *
     * @return list<array{key: string, address: string, display_name: string}>
     */
    public function getMailboxList(): array
    {
        $mailboxes = config('mail.mailboxes', []);

        return array_values(array_map(
            fn ($key, $cfg) => [
                'key'          => $key,
                'address'      => $cfg['address'],
                'display_name' => $cfg['display_name'],
            ],
            array_keys($mailboxes),
            $mailboxes
        ));
    }

    /**
     * The first configured mailbox address.
     */
    public function getDefaultAddress(): string
    {
        return MailboxConfig::address() ?? '';
    }

    /**
     * Resolve the From address for an outbound email composed from an entity view.
     *
     * Uses the entity's department to pick the correct mailbox.
     * Supports Lead, Order, and SalesLead; falls back to the first mailbox.
     */
    public function resolveAddressFromEntity(?Model $entity): string
    {
        $key = $this->resolveKeyFromEntityPrivate($entity);

        if ($key !== null) {
            return config('mail.mailboxes')[$key]['address'];
        }

        return $this->getDefaultAddress();
    }

    /**
     * Resolve the From address when replying in an email thread.
     *
     * Priority:
     *  1. email.mailbox_key  – reply from the same mailbox the message arrived on
     *  2. email.lead.department – fall back to the linked lead's department
     *  3. first configured mailbox
     */
    public function resolveAddressFromEmail(Email $email): string
    {
        $key = $this->resolveKeyFromEmail($email);

        if ($key !== null) {
            return config('mail.mailboxes')[$key]['address'];
        }

        return $this->getDefaultAddress();
    }

    /**
     * Resolve the mailbox key that matches a raw From address string.
     *
     * Used to tag outbound Email records with their mailbox_key after sending.
     */
    public function resolveKeyFromAddress(string $address): ?string
    {
        return MailboxConfig::resolveKeyByAddress($address);
    }

    /**
     * Resolve mailbox key from a linked entity's department.
     */
    public function resolveKeyFromEntity(?Model $entity): ?string
    {
        return $this->resolveKeyFromEntityPrivate($entity);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function resolveKeyFromEntityPrivate(?Model $entity): ?string
    {
        if ($entity === null) {
            return null;
        }

        try {
            $name = $this->departmentNameForEntity($entity);

            if ($name !== null) {
                $key = strtolower($name);
                if (isset(config('mail.mailboxes', [])[$key])) {
                    return $key;
                }
            }
        } catch (Throwable) {
            // Department resolution failed; caller falls back to default
        }

        return null;
    }

    private function resolveKeyFromEmail(Email $email): ?string
    {
        $mailboxes = config('mail.mailboxes', []);

        if ($email->mailbox_key && isset($mailboxes[$email->mailbox_key])) {
            return $email->mailbox_key;
        }

        if ($email->lead && $email->lead->department) {
            $key = strtolower($email->lead->department->name);
            if (isset($mailboxes[$key])) {
                return $key;
            }
        }

        return null;
    }

    private function departmentNameForEntity(Model $entity): ?string
    {
        if ($entity instanceof Lead && $entity->department_id) {
            return $entity->department?->name;
        }

        if ($entity instanceof Order) {
            return $entity->getPipelineDepartment()->name;
        }

        if ($entity instanceof SalesLead) {
            $dept = $entity->department_id ? $entity->department : $entity->lead?->department;

            return $dept?->name;
        }

        return null;
    }
}
