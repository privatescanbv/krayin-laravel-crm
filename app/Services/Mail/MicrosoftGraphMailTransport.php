<?php

namespace App\Services\Mail;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\RawMessage;

class MicrosoftGraphMailTransport implements TransportInterface
{
    protected string $baseUrl = 'https://graph.microsoft.com/v1.0';

    protected string $mailbox;

    private bool $enableLog = false;

    public function __construct(private readonly MicrosoftGraphTokenService $tokenService)
    {
        $this->mailbox = config('mail.graph.mailbox');
    }

    /**
     * Check if an email address matches any of the allowed patterns
     */
    public static function matchesAnyPattern(string $emailAddress, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (self::matchesPattern($emailAddress, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an email address matches a wildcard pattern
     *
     * Supports patterns like:
     *
     * - *@privatescan.nl (matches any user at privatescan.nl)
     * - user@privatescan.nl (exact match)
     *
     * - *@*.privatescan.nl (matches any subdomain)
     */
    public static function matchesPattern(string $email, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        // Escape special regex characters except *
        $regexPattern = preg_quote($pattern, '/');
        // Replace \* with .* (match any characters)
        $regexPattern = str_replace('\\*', '.*', $regexPattern);
        // Anchor to start and end for exact matching
        $regexPattern = '/^'.$regexPattern.'$/i';

        return (bool) preg_match($regexPattern, $email);
    }

    /**
     * Send the message
     */
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        try {
            $email = MessageConverter::toEmail($message);

            // Get the sender information
            // Always use the service account mailbox as from address to avoid SendAs permission issues
            // Always use the authenticated user's name for the from name (not from database)
            $fromAddress = $this->getDefaultFromAddress(); // Always use service account
            $fromName = $this->getDefaultFromName(); // Always use current user's name

            // Build recipients
            $toRecipients = [];
            foreach ($email->getTo() as $recipient) {
                $toRecipients[] = [
                    'emailAddress' => [
                        'address' => $recipient->getAddress(),
                        'name'    => $recipient->getName() ?: $recipient->getAddress(),
                    ],
                ];
            }

            $ccRecipients = [];
            foreach ($email->getCc() as $recipient) {
                $ccRecipients[] = [
                    'emailAddress' => [
                        'address' => $recipient->getAddress(),
                        'name'    => $recipient->getName() ?: $recipient->getAddress(),
                    ],
                ];
            }

            $bccRecipients = [];
            foreach ($email->getBcc() as $recipient) {
                $bccRecipients[] = [
                    'emailAddress' => [
                        'address' => $recipient->getAddress(),
                        'name'    => $recipient->getName() ?: $recipient->getAddress(),
                    ],
                ];
            }

            // Validate recipients against allowed patterns (safety net)
            $this->validateRecipients($toRecipients, $ccRecipients, $bccRecipients);

            // Build message payload
            $payload = [
                'message' => [
                    'subject'      => $email->getSubject(),
                    'body'         => [
                        'contentType' => 'HTML',
                        'content'     => $email->getHtmlBody() ?: $email->getTextBody(),
                    ],
                    'toRecipients'  => $toRecipients,
                    'ccRecipients'  => $ccRecipients,
                    'bccRecipients' => $bccRecipients,
                    'from'          => [
                        'emailAddress' => [
                            'address' => $fromAddress,
                            'name'    => $fromName,
                        ],
                    ],
                ],
                'saveToSentItems' => true,
            ];
            if ($this->enableLog) {
                logger()->info('Mail payload ', ['payload'=>$payload]);
            }

            // Add attachments if any
            $attachments = $email->getAttachments();
            if (! empty($attachments)) {
                $payload['message']['attachments'] = [];
                foreach ($attachments as $attachment) {
                    $payload['message']['attachments'][] = [
                        '@odata.type'  => '#microsoft.graph.fileAttachment',
                        'name'         => $attachment->getFilename(),
                        'contentType'  => $attachment->getContentType(),
                        'contentBytes' => base64_encode($attachment->getBody()),
                    ];
                }
            }

            // Send via Graph API
            $accessToken = $this->tokenService->getAccessToken();
            $url = "{$this->baseUrl}/users/{$this->mailbox}/sendMail";

            logger()->info('Sending email via Microsoft Graph', [
                'to'      => collect($toRecipients)->pluck('emailAddress.address')->toArray(),
                'from'    => $fromAddress,
                'subject' => $email->getSubject(),
            ]);
            $response = Http::withToken($accessToken)
                ->post($url, $payload);

            if (! $response->successful()) {
                Log::error('Failed to send email via Microsoft Graph', [
                    'status'   => $response->status(),
                    'response' => substr($response->body(), 50).' ...',
                    'subject'  => $email->getSubject(),
                ]);

                throw new Exception('Failed to send email: '.$response->body());
            }
            // Build default Envelope when not provided
            if ($envelope === null) {
                $senderAddress = new Address($fromAddress, $fromName);
                $allRecipients = array_merge($toRecipients, $ccRecipients, $bccRecipients);
                $recipientAddresses = [];
                foreach ($allRecipients as $recipient) {
                    $addr = $recipient['emailAddress']['address'] ?? null;
                    $name = $recipient['emailAddress']['name'] ?? null;
                    if ($addr) {
                        $recipientAddresses[] = new Address($addr, $name);
                    }
                }

                $envelope = new Envelope($senderAddress, $recipientAddresses);
            }

            return new SentMessage($message, $envelope);
        } catch (Exception $e) {
            Log::error('Microsoft Graph mail transport error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get the default from address
     * Always use the service account mailbox to avoid SendAs permission issues
     */
    protected function getDefaultFromAddress(): string
    {
        // Always use the configured mailbox to avoid SendAs permission issues
        // The from name will still be personalized based on the user
        return config('mail.graph.mailbox', 'crm@privatescan.nl');
    }

    /**
     * Get the default from name
     */
    protected function getDefaultFromName(): string
    {
        $user = auth()->guard('user')->user();

        return $user?->name ?? config('mail.from.name', 'CRM Private Scan');
    }

    /**
     * Validate recipients against allowed email patterns
     *
     * @throws Exception
     */
    protected function validateRecipients(array $toRecipients, array $ccRecipients, array $bccRecipients): void
    {
        $allowedPatterns = config('mail.send_only_accept');

        // If MAIL_SEND_ONLY_ACCEPT is not set, allow everything
        if (empty($allowedPatterns)) {
            // Allow unrestricted sending only in production; block otherwise
            if (config('app.env') == 'production') {
                return;
            }
            throw new Exception('Email sending blocked: no allowed recipient patterns configured and APP_ENV is not production');
        }

        // Remove quotes if present (Laravel env() can return quoted strings)
        $allowedPatterns = trim($allowedPatterns, " \t\n\r\0\x0B'\"");

        // Parse patterns (semicolon-separated)
        $patterns = array_map('trim', explode(';', $allowedPatterns));
        $patterns = array_filter($patterns); // Remove empty patterns

        if (empty($patterns)) {
            throw new Exception('Unexpected error: No valid recipient patterns found in configuration');
        }

        // Collect all recipient email addresses
        $allRecipients = [];
        foreach ($toRecipients as $recipient) {
            $allRecipients[] = $recipient['emailAddress']['address'];
        }
        foreach ($ccRecipients as $recipient) {
            $allRecipients[] = $recipient['emailAddress']['address'];
        }
        foreach ($bccRecipients as $recipient) {
            $allRecipients[] = $recipient['emailAddress']['address'];
        }

        // Validate each recipient
        $invalidRecipients = [];
        foreach ($allRecipients as $emailAddress) {
            if (! $this->matchesAnyPattern($emailAddress, $patterns)) {
                $invalidRecipients[] = $emailAddress;
            }
        }

        // If any recipients are invalid, throw exception
        if (! empty($invalidRecipients)) {
            throw new Exception(
                'Email sending blocked: The following recipients are not allowed: '.implode(', ', $invalidRecipients).
                '. Allowed patterns: '.implode(', ', $patterns)
            );
        }
    }

    /**
     * Get the string representation of the transport
     */
    public function __toString(): string
    {
        return 'microsoft-graph';
    }
}
