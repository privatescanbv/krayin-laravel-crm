<?php

namespace Tests\Unit\Services\Mail;

use App\Services\Mail\MicrosoftGraphMailTransport;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email as SymfonyEmail;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;

class MicrosoftGraphMailTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('mail.graph.client_id', 'test-client-id');
        config()->set('mail.graph.tenant_id', 'test-tenant-id');
        config()->set('mail.graph.client_secret', 'test-client-secret');
        config()->set('mail.graph.mailbox', 'crm@example.com');
        config()->set('mail.from.name', 'CRM Test');
    }

    public function test_it_sends_email_via_graph_api(): void
    {
        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response([
                'access_token' => 'test-access-token',
            ], 200),
            'https://graph.microsoft.com/v1.0/users/crm@example.com/sendMail' => Http::response(null, 202),
        ]);

        $symfonyEmail = (new SymfonyEmail())
            ->subject('Graph Test Message')
            ->from('john.doe@example.com', 'John Doe')
            ->to('recipient@example.com', 'Recipient')
            ->cc('copy@example.com', 'Copy')
            ->bcc('hidden@example.com', 'Hidden Copy')
            ->html('<p>Hello Graph!</p>')
            ->text('Hello Graph!')
            ->attach('Attachment body', 'note.txt', 'text/plain');

        $symfonyEmail->getHeaders()->addIdHeader('Message-ID', '<message-id@example.com>');
        $symfonyEmail->getHeaders()->addIdHeader('In-Reply-To', '<parent@example.com>');
        $symfonyEmail->getHeaders()->addTextHeader('References', '<parent@example.com>');

        $rawMessage = new RawMessage($symfonyEmail->toString());

        $transport = new MicrosoftGraphMailTransport();

        $sentMessage = $transport->send($rawMessage);

        $this->assertInstanceOf(SentMessage::class, $sentMessage);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/sendMail')) {
                return false;
            }

            $payload = $request->data();

            $this->assertEquals('Graph Test Message', $payload['message']['subject']);
            $this->assertEquals('HTML', $payload['message']['body']['contentType']);
            $this->assertEquals('<p>Hello Graph!</p>', $payload['message']['body']['content']);

            $this->assertEquals('crm@example.com', $payload['message']['from']['emailAddress']['address']);
            $this->assertEquals('John Doe', $payload['message']['from']['emailAddress']['name']);

            $this->assertEquals('recipient@example.com', $payload['message']['toRecipients'][0]['emailAddress']['address']);
            $this->assertEquals('copy@example.com', $payload['message']['ccRecipients'][0]['emailAddress']['address']);
            $this->assertEquals('hidden@example.com', $payload['message']['bccRecipients'][0]['emailAddress']['address']);

            $this->assertCount(1, $payload['message']['replyTo'] ?? []);
            $this->assertEquals('john.doe@example.com', $payload['message']['replyTo'][0]['emailAddress']['address']);

            $attachments = $payload['message']['attachments'];
            $this->assertCount(1, $attachments);
            $this->assertEquals('note.txt', $attachments[0]['name']);
            $this->assertEquals('text/plain', $attachments[0]['contentType']);
            $this->assertEquals(base64_encode('Attachment body'), $attachments[0]['contentBytes']);

            $headers = collect($payload['message']['internetMessageHeaders'])->pluck('value', 'name');
            $this->assertEquals('<message-id@example.com>', $headers->get('Message-ID'));
            $this->assertEquals('<parent@example.com>', $headers->get('In-Reply-To'));
            $this->assertStringContainsString('<parent@example.com>', $headers->get('References'));

            return true;
        });
    }

    public function test_it_omits_reply_to_when_sender_matches_mailbox(): void
    {
        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response([
                'access_token' => 'test-access-token',
            ], 200),
            'https://graph.microsoft.com/v1.0/users/crm@example.com/sendMail' => Http::response(null, 202),
        ]);

        $symfonyEmail = (new SymfonyEmail())
            ->subject('Graph Match Mailbox')
            ->from('crm@example.com', 'CRM Team')
            ->to('recipient@example.com')
            ->text('Hello!');

        $rawMessage = new RawMessage($symfonyEmail->toString());

        $transport = new MicrosoftGraphMailTransport();
        $transport->send($rawMessage);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/sendMail')) {
                return false;
            }

            $payload = $request->data();

            $this->assertArrayNotHasKey('replyTo', $payload['message']);

            return true;
        });
    }
}
