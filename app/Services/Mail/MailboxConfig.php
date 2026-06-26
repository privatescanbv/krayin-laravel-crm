<?php

namespace App\Services\Mail;

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
     * @return array{tenant_id: ?string, client_id: ?string, client_secret: ?string}
     */
    public static function graphCredentials(?string $mailboxKey = null): array
    {
        $mailboxKey ??= self::defaultKey();
        $mailbox = self::get($mailboxKey);
        $graph = is_array($mailbox['graph'] ?? null) ? $mailbox['graph'] : [];

        return [
            'tenant_id'     => $graph['tenant_id'] ?? null,
            'client_id'     => $graph['client_id'] ?? null,
            'client_secret' => $graph['client_secret'] ?? null,
        ];
    }

    /**
     * Client secrets to try for a mailbox, including alternates from other mailboxes
     * that share the same Azure AD app registration (same tenant + client id).
     *
     * @return list<string>
     */
    public static function clientSecretsForMailbox(?string $mailboxKey = null): array
    {
        $credentials = self::graphCredentials($mailboxKey);
        $tenantId = $credentials['tenant_id'];
        $clientId = $credentials['client_id'];
        $secrets = [];

        if (! empty($credentials['client_secret'])) {
            $secrets[] = $credentials['client_secret'];
        }

        if (! $tenantId || ! $clientId) {
            return $secrets;
        }

        foreach (self::all() as $key => $mailbox) {
            if ($key === $mailboxKey) {
                continue;
            }

            $graph = is_array($mailbox['graph'] ?? null) ? $mailbox['graph'] : [];
            $alternateSecret = $graph['client_secret'] ?? null;

            if (
                ($graph['tenant_id'] ?? null) === $tenantId
                && ($graph['client_id'] ?? null) === $clientId
                && is_string($alternateSecret)
                && $alternateSecret !== ''
                && ! in_array($alternateSecret, $secrets, true)
            ) {
                $secrets[] = $alternateSecret;
            }
        }

        return $secrets;
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
