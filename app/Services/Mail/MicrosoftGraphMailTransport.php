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
    protected ?string $accessToken = null;

    protected string $baseUrl = 'https://graph.microsoft.com/v1.0';

    protected string $mailbox;

    public function __construct()
    {
        $this->mailbox = config('mail.graph.mailbox');
    }

    /**
     * Send the message
     */
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        try {
            $email = MessageConverter::toEmail($message);

            $serviceMailbox = $this->getDefaultFromAddress();
            $senderAddress = $email->getFrom()[0] ?? null;
            $fromName = $senderAddress?->getName() ?? $this->getDefaultFromName();

            $replyTo = [];
            if ($senderAddress && strcasecmp($senderAddress->getAddress(), $serviceMailbox) !== 0) {
                $replyTo = $this->formatRecipients([$senderAddress]);
            }

            $toRecipients = $this->formatRecipients($email->getTo());
            $ccRecipients = $this->formatRecipients($email->getCc());
            $bccRecipients = $this->formatRecipients($email->getBcc());

            $contentType = $email->getHtmlBody() ? 'HTML' : 'Text';
            $content = $email->getHtmlBody() ?? $email->getTextBody() ?? '';

            $payload = [
                'message' => [
                    'subject'      => $email->getSubject(),
                    'body'         => [
                        'contentType' => $contentType,
                        'content'     => $content,
                    ],
                    'toRecipients'  => $toRecipients,
                    'ccRecipients'  => $ccRecipients,
                    'bccRecipients' => $bccRecipients,
                    'from'          => [
                        'emailAddress' => [
                            'address' => $serviceMailbox,
                            'name'    => $fromName,
                        ],
                    ],
                ],
                'saveToSentItems' => true,
            ];

            if (! empty($replyTo)) {
                $payload['message']['replyTo'] = $replyTo;
            }

            $customHeaders = $this->buildCustomHeaders($email);
            if (! empty($customHeaders)) {
                $payload['message']['internetMessageHeaders'] = $customHeaders;
            }

            $attachments = $email->getAttachments();
            if (! empty($attachments)) {
                $payload['message']['attachments'] = [];
                foreach ($attachments as $attachment) {
                    $payload['message']['attachments'][] = [
                        '@odata.type'  => '#microsoft.graph.fileAttachment',
                        'name'         => $attachment->getFilename(),
                        'contentType'  => $attachment->getContentType(),
                        'contentBytes' => base64_encode($this->getAttachmentContent($attachment)),
                    ];
                }
            }

            $accessToken = $this->getAccessToken();
            $url = "{$this->baseUrl}/users/{$this->mailbox}/sendMail";

            logger()->info('Sending email via Microsoft Graph', [
                'to'      => collect($toRecipients)->pluck('emailAddress.address')->toArray(),
                'from'    => $serviceMailbox,
                'subject' => $email->getSubject(),
            ]);
            $response = Http::withToken($accessToken)
                ->post($url, $payload);

            if (! $response->successful()) {
                Log::error('Failed to send email via Microsoft Graph', [
                    'status'   => $response->status(),
                    'response' => $response->body(),
                    'subject'  => $email->getSubject(),
                ]);

                throw new Exception('Failed to send email: '.$response->body());
            }

            Log::info('Email sent successfully via Microsoft Graph', [
                'subject' => $email->getSubject(),
                'to'      => collect($toRecipients)->pluck('emailAddress.address')->toArray(),
                'from'    => $serviceMailbox,
            ]);

            // Build default Envelope when not provided
            if ($envelope === null) {
                $senderEnvelope = new Address($serviceMailbox, $fromName);
                $allRecipients = array_merge($toRecipients, $ccRecipients, $bccRecipients);
                $recipientAddresses = [];
                foreach ($allRecipients as $recipient) {
                    $addr = $recipient['emailAddress']['address'] ?? null;
                    $name = $recipient['emailAddress']['name'] ?? null;
                    if ($addr) {
                        $recipientAddresses[] = new Address($addr, $name);
                    }
                }

                $envelope = new Envelope($senderEnvelope, $recipientAddresses);
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
     * Get access token using client credentials flow
     */
    protected function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $tenantId = config('mail.graph.tenant_id');
        $clientId = config('mail.graph.client_id');
        $clientSecret = config('mail.graph.client_secret');

        if (! $tenantId || ! $clientId || ! $clientSecret) {
            throw new Exception('Microsoft Graph credentials not configured');
        }

        try {
            $response = Http::asForm()->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'scope'         => 'https://graph.microsoft.com/.default',
                'grant_type'    => 'client_credentials',
            ]);

            if (! $response->successful()) {
                throw new Exception('Failed to get access token: '.$response->body());
            }

            $data = $response->json();
            $this->accessToken = $data['access_token'];

            return $this->accessToken;
        } catch (Exception $e) {
            Log::error('Failed to get Microsoft Graph access token', [
                'error' => $e->getMessage(),
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
     * Generate email address from user
     */
    protected function generateEmailFromUser($user): string
    {
        $firstName = strtolower($user->first_name);
        $lastName = strtolower($user->last_name);

        // Remove spaces and special characters
        $firstName = preg_replace('/[^a-z0-9]/', '', $firstName);
        $lastName = preg_replace('/[^a-z0-9]/', '', $lastName);

        $domain = config('mail.graph.sender_domain', 'crm.private-scan.nl');

        return "{$firstName}.{$lastName}@{$domain}";
    }

    /**
     * Get the string representation of the transport
     */
    public function __toString(): string
    {
        return 'microsoft-graph';
    }

    /**
     * Convert Symfony Address instances to Graph API recipient payload.
     *
     * @param  iterable<Address>  $addresses
     */
    protected function formatRecipients(iterable $addresses): array
    {
        $recipients = [];

        foreach ($addresses as $recipient) {
            if (! $recipient instanceof Address) {
                continue;
            }

            $address = $recipient->getAddress();

            if (! $address) {
                continue;
            }

            $recipients[] = [
                'emailAddress' => [
                    'address' => $address,
                    'name'    => $recipient->getName() ?: $address,
                ],
            ];
        }

        return $recipients;
    }

    /**
     * Extract attachment content as string for Graph API upload.
     */
    protected function getAttachmentContent($attachment): string
    {
        if (! method_exists($attachment, 'getBody')) {
            return '';
        }

        $body = $attachment->getBody();

        if (is_resource($body)) {
            return stream_get_contents($body) ?: '';
        }

        if (is_iterable($body)) {
            $content = '';

            foreach ($body as $chunk) {
                $content .= $chunk;
            }

            return $content;
        }

        if (is_string($body)) {
            return $body;
        }

        if ($body instanceof \Stringable) {
            return (string) $body;
        }

        return '';
    }

    /**
     * Build custom headers array for Graph internetMessageHeaders property.
     */
    protected function buildCustomHeaders(\Symfony\Component\Mime\Email $email): array
    {
        $headers = $email->getHeaders();

        $headerNames = ['Message-ID', 'In-Reply-To', 'References'];
        $customHeaders = [];

        foreach ($headerNames as $headerName) {
            if (! $headers->has($headerName)) {
                continue;
            }

            $header = $headers->get($headerName);
            if (! $header) {
                continue;
            }

            $value = method_exists($header, 'getBodyAsString')
                ? $header->getBodyAsString()
                : $header->getBody();

            if (is_array($value)) {
                $value = implode(' ', array_filter($value));
            }

            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            $customHeaders[] = [
                'name'  => $headerName,
                'value' => $value,
            ];
        }

        return $customHeaders;
    }
}
