<?php

namespace App\Services\Mail;

use InvalidArgumentException;

/**
 * Resolves configured CRM mailboxes and their Microsoft Graph credentials.
 *
 * All mailbox addresses and Graph credentials are defined in config('mail.mailboxes').
 * Adding a third mailbox only requires a new entry in mail.php and .env.
 */
class MailboxConfig
{
    public const MAILBOX_KEY_HEADER = 'X-Crm-Mailbox-Key';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return config('mail.mailboxes', []);
    }

    public static function defaultKey(): ?string
    {
        $key = array_key_first(self::all());

        return is_string($key) ? $key : null;
    }

    public static function get(?string $mailboxKey): ?array
    {
        if ($mailboxKey === null) {
            return null;
        }

        $mailbox = self::all()[$mailboxKey] ?? null;

        return is_array($mailbox) ? $mailbox : null;
    }

    /**
     * Resolve a mailbox key from a From address (mailbox address or configured send_as alias).
     */
    public static function resolveKeyByAddress(string $address): ?string
    {
        foreach (self::all() as $key => $mailbox) {
            $configuredAddress = $mailbox['address'] ?? null;

            if ($configuredAddress && strcasecmp($configuredAddress, $address) === 0) {
                return $key;
            }

            foreach (self::sendAsAddresses($mailbox) as $alias) {
                if (strcasecmp($alias, $address) === 0) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * @return array{mailbox_key: string, tenant_id: string, client_id: string, client_secret: string}
     */
    public static function graphCredentials(?string $mailboxKey = null): array
    {
        $mailboxKey ??= self::defaultKey();

        if ($mailboxKey === null) {
            throw new InvalidArgumentException('No mailbox key provided and no default mailbox configured.');
        }

        $mailbox = self::get($mailboxKey);

        if ($mailbox === null) {
            throw new InvalidArgumentException("Unknown mailbox [{$mailboxKey}].");
        }

        $graph = $mailbox['graph'] ?? null;

        if (! is_array($graph)) {
            throw new InvalidArgumentException("Mailbox [{$mailboxKey}] has no graph configuration.");
        }
        $tenantId = $graph['tenant_id'] ?? null;
        $clientId = $graph['client_id'] ?? null;
        $clientSecret = $graph['client_secret'] ?? null;
        if (! is_string($tenantId) || $tenantId === ''
            || ! is_string($clientId) || $clientId === ''
            || ! is_string($clientSecret) || $clientSecret === '') {
            throw new InvalidArgumentException("Mailbox [{$mailboxKey}] has incomplete Microsoft Graph credentials.");
        }

        return [
            'mailbox_key'   => $mailboxKey,
            'tenant_id'     => $tenantId,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
        ];
    }

    /**
     * The Exchange mailbox address used for Graph API calls (/users/{address}/...).
     */
    public static function address(?string $mailboxKey = null): ?string
    {
        $mailboxKey ??= self::defaultKey();

        return self::get($mailboxKey)['address'] ?? null;
    }

    /**
     * @return list<string>
     */
    private static function sendAsAddresses(array $mailbox): array
    {
        $aliases = $mailbox['send_as'] ?? [];

        return is_array($aliases) ? array_values(array_filter($aliases)) : [];
    }
}
