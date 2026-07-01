<?php

namespace Tests\Unit;

use App\Models\EmailLog;
use App\Services\Mail\AbstractEmailProcessor;
use App\Services\Mail\EmailEntityLinker;
use App\Services\Mail\GraphMailService;
use App\Services\Mail\MicrosoftGraphTokenService;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use Tests\TestCase;
use Webkul\Email\InboundEmailProcessor\Contracts\InboundEmailProcessor;
use Webkul\Email\Models\Email;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;

class GraphMailServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GraphMailService $service;

    protected EmailRepository $emailRepository;

    protected AttachmentRepository $attachmentRepository;

    protected MicrosoftGraphTokenService $tokenService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.mailboxes' => [
            'privatescan' => [
                'address'     => 'test@example.com',
                'folder_name' => 'Inbox Privatescan',
                'graph'       => [
                    'tenant_id'     => 'test-tenant-id',
                    'client_id'     => 'test-client-id',
                    'client_secret' => 'test-client-secret',
                ],
            ],
        ]]);

        $this->emailRepository = $this->createMock(EmailRepository::class);
        $this->attachmentRepository = $this->createMock(AttachmentRepository::class);
        $this->tokenService = new MicrosoftGraphTokenService;

        $this->service = new GraphMailService(
            $this->emailRepository,
            $this->attachmentRepository,
            new EmailEntityLinker,
            $this->tokenService,
        );

        $this->service->configureMailbox('test@example.com', 'privatescan');
    }

    public function test_implements_inbound_email_processor_contract()
    {
        $this->assertInstanceOf(InboundEmailProcessor::class, $this->service);
    }

    public function test_extends_abstract_email_processor()
    {
        $this->assertInstanceOf(AbstractEmailProcessor::class, $this->service);
    }

    public function test_get_access_token_success()
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'test-token',
                'token_type'   => 'Bearer',
                'expires_in'   => 3600,
            ], 200),
        ]);

        $token = $this->tokenService->getAccessToken('privatescan');

        $this->assertEquals('test-token', $token);
    }

    public function test_get_access_token_failure()
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'error'             => 'invalid_client',
                'error_description' => 'Invalid client credentials',
            ], 401),
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to get access token');

        $this->tokenService->getAccessToken();
    }

    public function test_fetch_messages_success()
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'test-token',
                'token_type'   => 'Bearer',
                'expires_in'   => 3600,
            ], 200),
            'graph.microsoft.com/*' => Http::response([
                'value' => [
                    [
                        'id'                => 'msg-1',
                        'internetMessageId' => '<msg1@example.com>',
                        'subject'           => 'Test Subject',
                        'from'              => [
                            'emailAddress' => [
                                'address' => 'sender@example.com',
                                'name'    => 'Test Sender',
                            ],
                        ],
                        'toRecipients' => [
                            [
                                'emailAddress' => [
                                    'address' => 'recipient@example.com',
                                    'name'    => 'Test Recipient',
                                ],
                            ],
                        ],
                        'receivedDateTime' => '2025-01-28T10:00:00Z',
                        'isRead'           => false,
                        'hasAttachments'   => false,
                        'body'             => [
                            'contentType' => 'html',
                            'content'     => '<p>Test message body</p>',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('fetchMessages');
        $method->setAccessible(true);

        $messages = $method->invoke($this->service);

        $this->assertCount(1, $messages);
        $this->assertEquals('msg-1', $messages[0]['id']);
        $this->assertEquals('Test Subject', $messages[0]['subject']);
    }

    public function test_process_message_creates_email()
    {
        $message = [
            'id'                => 'msg-1',
            'internetMessageId' => '<msg1@example.com>',
            'subject'           => 'Test Subject',
            'from'              => [
                'emailAddress' => [
                    'address' => 'sender@example.com',
                    'name'    => 'Test Sender',
                ],
            ],
            'toRecipients' => [
                [
                    'emailAddress' => [
                        'address' => 'recipient@example.com',
                        'name'    => 'Test Recipient',
                    ],
                ],
            ],
            'receivedDateTime' => '2025-01-28T10:00:00Z',
            'isRead'           => false,
            'hasAttachments'   => false,
            'body'             => [
                'contentType' => 'html',
                'content'     => '<p>Test message body</p>',
            ],
        ];

        $this->emailRepository
            ->expects($this->exactly(2))
            ->method('findOneByField')
            ->willReturnMap([
                ['message_id', '<msg1@example.com>', null],
                ['unique_id', '<msg1@example.com>', null],
            ]);

        $this->emailRepository
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($data) {
                return $data['subject'] === 'Test Subject' &&
                       is_array($data['from']) &&
                       $data['from']['email'] === 'sender@example.com' &&
                       $data['from']['name'] === 'Test Sender' &&
                       $data['message_id'] === '<msg1@example.com>';
            }))
            ->willReturn(new Email);

        $this->service->processMessage($message);
    }

    public function test_process_message_skips_existing_email()
    {
        $message = [
            'id'                => 'msg-1',
            'internetMessageId' => '<msg1@example.com>',
            'subject'           => 'Test Subject',
        ];

        $existingEmail = new Email;
        $existingEmail->id = 1;

        $this->emailRepository
            ->expects($this->once())
            ->method('findOneByField')
            ->with('message_id', '<msg1@example.com>')
            ->willReturn($existingEmail);

        $this->emailRepository
            ->expects($this->never())
            ->method('create');

        $this->service->processMessage($message);
    }

    public function test_process_message_skips_existing_email_by_unique_id()
    {
        $message = [
            'id'                => 'msg-1',
            'internetMessageId' => '<msg1@example.com>',
            'subject'           => 'Test Subject',
        ];

        $existingEmail = new Email;
        $existingEmail->id = 1;

        $this->emailRepository
            ->expects($this->exactly(2))
            ->method('findOneByField')
            ->willReturnMap([
                ['message_id', '<msg1@example.com>', null],
                ['unique_id', '<msg1@example.com>', $existingEmail],
            ]);

        $this->emailRepository
            ->expects($this->never())
            ->method('create');

        $this->service->processMessage($message);
    }

    public function test_process_message_handles_null_input()
    {
        $this->emailRepository
            ->expects($this->never())
            ->method('findOneByField');

        $this->emailRepository
            ->expects($this->never())
            ->method('create');

        $this->service->processMessage(null);
    }

    public function test_process_message_handles_invalid_input()
    {
        $this->emailRepository
            ->expects($this->never())
            ->method('findOneByField');

        $this->emailRepository
            ->expects($this->never())
            ->method('create');

        $this->service->processMessage('invalid input');
    }

    public function test_extract_message_body_html()
    {
        $message = [
            'body' => [
                'contentType' => 'html',
                'content'     => '<p>Test HTML content</p>',
            ],
        ];

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('extractMessageBody');
        $method->setAccessible(true);

        $body = $method->invoke($this->service, $message);

        $this->assertEquals('<p>Test HTML content</p>', $body);
    }

    public function test_extract_message_body_text()
    {
        $message = [
            'body' => [
                'contentType' => 'text',
                'content'     => 'Test text content',
            ],
        ];

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('extractMessageBody');
        $method->setAccessible(true);

        $body = $method->invoke($this->service, $message);

        $this->assertEquals('Test text content', $body);
    }

    public function test_extract_email_addresses()
    {
        $recipients = [
            [
                'emailAddress' => [
                    'address' => 'test1@example.com',
                    'name'    => 'Test 1',
                ],
            ],
            [
                'emailAddress' => [
                    'address' => 'test2@example.com',
                    'name'    => 'Test 2',
                ],
            ],
        ];

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('extractEmailAddresses');
        $method->setAccessible(true);

        $addresses = $method->invoke($this->service, $recipients);

        $this->assertEquals(['test1@example.com', 'test2@example.com'], $addresses);
    }

    public function test_parse_date_time()
    {
        $dateTime = '2025-01-28T10:00:00Z';

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('parseDateTime');
        $method->setAccessible(true);

        $carbon = $method->invoke($this->service, $dateTime);

        $this->assertInstanceOf(Carbon::class, $carbon);

        // Test that the timezone conversion works correctly
        // The Z indicates UTC, so we expect it to be converted to the app timezone (Europe/Amsterdam = UTC+1)
        $expectedTime = Carbon::parse($dateTime)->setTimezone('Europe/Amsterdam');
        $this->assertEquals($expectedTime->format('Y-m-d H:i:s'), $carbon->format('Y-m-d H:i:s'));
        $this->assertEquals('Europe/Amsterdam', $carbon->getTimezone()->getName());
    }

    public function test_log_sync_start_creates_email_log()
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('logSyncStart');
        $method->setAccessible(true);

        $method->invoke($this->service);

        $this->assertDatabaseHas('email_logs', [
            'sync_type'       => 'graph',
            'processed_count' => 0,
            'error_count'     => 0,
        ]);
    }

    public function test_process_attachments_calls_create_from_graph_data(): void
    {
        $email = new Email;
        $email->id = 99;

        $message = ['id' => 'msg-attach'];

        Http::fake([
            '*/oauth2/v2.0/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in'   => 3600,
            ]),
            '*/messages/msg-attach/attachments' => Http::response([
                'value' => [[
                    'name'         => 'test.pdf',
                    'contentType'  => 'application/pdf',
                    'contentBytes' => base64_encode('fake'),
                    'size'         => 4,
                ]],
            ]),
        ]);

        $this->attachmentRepository
            ->expects($this->once())
            ->method('createFromGraphData')
            ->with(
                $this->equalTo($email),
                $this->callback(fn ($a) => $a['name'] === 'test.pdf')
            );

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('processAttachments');
        $method->setAccessible(true);
        $method->invoke($this->service, $email, $message);
    }

    // -------------------------------------------------------------------------
    // Threading: reference_ids & In-Reply-To
    // -------------------------------------------------------------------------

    public function test_extract_email_data_stores_both_message_id_and_conversation_id_in_reference_ids()
    {
        $message = [
            'id'                => 'graph-msg-1',
            'internetMessageId' => '<msg1@mail.example.com>',
            'conversationId'    => 'AAQkADYwConversation123',
            'subject'           => 'Test',
            'from'              => ['emailAddress' => ['address' => 'a@b.com', 'name' => 'A']],
            'toRecipients'      => [],
            'ccRecipients'      => [],
            'bccRecipients'     => [],
            'replyTo'           => [],
            'isRead'            => false,
            'hasAttachments'    => false,
            'body'              => ['contentType' => 'text', 'content' => 'hello'],
            'receivedDateTime'  => now()->toISOString(),
        ];

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('extractEmailData');
        $method->setAccessible(true);

        $data = $method->invoke($this->service, $message, 'Inbox Privatescan', null);

        $this->assertContains('<msg1@mail.example.com>', $data['reference_ids']);
        $this->assertContains('AAQkADYwConversation123', $data['reference_ids']);
        $this->assertCount(2, $data['reference_ids']);
    }

    public function test_extract_email_data_reference_ids_without_conversation_id()
    {
        $message = [
            'id'                => 'graph-msg-1',
            'internetMessageId' => '<msg1@mail.example.com>',
            'subject'           => 'Test',
            'from'              => ['emailAddress' => ['address' => 'a@b.com', 'name' => 'A']],
            'toRecipients'      => [],
            'ccRecipients'      => [],
            'bccRecipients'     => [],
            'replyTo'           => [],
            'isRead'            => false,
            'hasAttachments'    => false,
            'body'              => ['contentType' => 'text', 'content' => 'hello'],
            'receivedDateTime'  => now()->toISOString(),
        ];

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('extractEmailData');
        $method->setAccessible(true);

        $data = $method->invoke($this->service, $message, 'Inbox Privatescan', null);

        $this->assertSame(['<msg1@mail.example.com>'], $data['reference_ids']);
    }

    public function test_get_in_reply_to_id_returns_in_reply_to_header_value()
    {
        $message = [
            'internetMessageHeaders' => [
                ['name' => 'MIME-Version', 'value' => '1.0'],
                ['name' => 'In-Reply-To', 'value' => '<original@mail.example.com>'],
                ['name' => 'References', 'value' => '<original@mail.example.com>'],
            ],
        ];

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('getInReplyToId');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $message);

        $this->assertSame('<original@mail.example.com>', $result);
    }

    public function test_get_in_reply_to_id_is_case_insensitive_on_header_name()
    {
        $message = [
            'internetMessageHeaders' => [
                ['name' => 'in-reply-to', 'value' => '<original@mail.example.com>'],
            ],
        ];

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('getInReplyToId');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $message);

        $this->assertSame('<original@mail.example.com>', $result);
    }

    public function test_get_in_reply_to_id_returns_null_when_no_in_reply_to_header()
    {
        $message = [
            'internetMessageHeaders' => [
                ['name' => 'MIME-Version', 'value' => '1.0'],
                ['name' => 'References', 'value' => '<original@mail.example.com>'],
            ],
        ];

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('getInReplyToId');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $message);

        $this->assertNull($result);
    }

    public function test_get_in_reply_to_id_returns_null_when_no_headers()
    {
        $message = [];

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('getInReplyToId');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $message);

        $this->assertNull($result);
    }

    public function test_find_parent_email_finds_by_conversation_id()
    {
        $conversationId = 'AAQkADYwConversationXYZ';
        $parentEmail = new Email;
        $parentEmail->id = 10;
        $parentEmail->reference_ids = ['<original@mail.example.com>', $conversationId];

        $this->emailRepository
            ->expects($this->once())
            ->method('findOneWhere')
            ->with([['reference_ids', 'like', '%'.$conversationId.'%']])
            ->willReturn($parentEmail);

        $message = [
            'id'                => 'reply-graph-id',
            'internetMessageId' => '<reply@mail.example.com>',
            'conversationId'    => $conversationId,
            'toRecipients'      => [],
        ];

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('findParentEmail');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $message);

        $this->assertSame($parentEmail, $result);
    }

    public function test_find_parent_email_falls_back_to_in_reply_to_when_no_conversation_id_match()
    {
        $parentMessageId = '<original@mail.example.com>';
        $parentEmail = new Email;
        $parentEmail->id = 10;
        $parentEmail->message_id = $parentMessageId;
        $parentEmail->reference_ids = [$parentMessageId];

        $this->emailRepository
            ->expects($this->once())
            ->method('findOneWhere')
            ->willReturn(null);

        $this->emailRepository
            ->expects($this->once())
            ->method('findOneByField')
            ->with('message_id', $parentMessageId)
            ->willReturn($parentEmail);

        $message = [
            'id'                     => 'reply-graph-id',
            'internetMessageId'      => '<reply@mail.example.com>',
            'conversationId'         => 'AAQkConversation',
            'internetMessageHeaders' => [
                ['name' => 'In-Reply-To', 'value' => $parentMessageId],
            ],
            'toRecipients' => [],
        ];

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('findParentEmail');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $message);

        $this->assertSame($parentEmail, $result);
    }

    public function test_find_parent_email_returns_null_when_no_match()
    {
        $this->emailRepository
            ->expects($this->once())
            ->method('findOneWhere')
            ->with([['reference_ids', 'like', '%AAQkConversation%']])
            ->willReturn(null);

        $this->emailRepository
            ->expects($this->once())
            ->method('findOneByField')
            ->with('message_id', '<original@mail.example.com>')
            ->willReturn(null);

        $message = [
            'id'                     => 'reply-graph-id',
            'internetMessageId'      => '<reply@mail.example.com>',
            'conversationId'         => 'AAQkConversation',
            'internetMessageHeaders' => [
                ['name' => 'In-Reply-To', 'value' => '<original@mail.example.com>'],
            ],
            'toRecipients' => [],
        ];

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('findParentEmail');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $message);

        $this->assertNull($result);
    }

    public function test_process_message_propagates_conversation_id_to_parent_reference_ids()
    {
        $conversationId = 'AAQkADYwConversation999';
        $parentMessageId = '<original@mail.example.com>';
        $replyMessageId = '<reply@mail.example.com>';

        $parentEmail = new Email;
        $parentEmail->id = 5;
        $parentEmail->reference_ids = [$parentMessageId];

        $createdEmail = new Email;
        $createdEmail->id = 6;

        // Called 3×: message_id dedup, unique_id dedup, then In-Reply-To lookup
        $this->emailRepository
            ->expects($this->exactly(3))
            ->method('findOneByField')
            ->willReturnMap([
                ['message_id', $replyMessageId, null],
                ['unique_id', $replyMessageId, null],
                ['message_id', $parentMessageId, $parentEmail],
            ]);

        $this->emailRepository
            ->expects($this->once())
            ->method('findOneWhere')
            ->with([['reference_ids', 'like', '%'.$conversationId.'%']])
            ->willReturn(null);

        $this->emailRepository
            ->expects($this->once())
            ->method('update')
            ->with(
                $this->callback(function ($data) use ($conversationId, $replyMessageId) {
                    return in_array($conversationId, $data['reference_ids'])
                        && in_array($replyMessageId, $data['reference_ids']);
                }),
                5
            )
            ->willReturn($parentEmail);

        $this->emailRepository
            ->expects($this->once())
            ->method('create')
            ->willReturn($createdEmail);

        $message = [
            'id'                     => 'graph-reply',
            'internetMessageId'      => $replyMessageId,
            'conversationId'         => $conversationId,
            'internetMessageHeaders' => [
                ['name' => 'In-Reply-To', 'value' => $parentMessageId],
            ],
            'from'             => ['emailAddress' => ['address' => 'a@b.com', 'name' => 'A']],
            'subject'          => 'Re: Test',
            'toRecipients'     => [],
            'ccRecipients'     => [],
            'bccRecipients'    => [],
            'replyTo'          => [],
            'isRead'           => false,
            'hasAttachments'   => false,
            'body'             => ['contentType' => 'text', 'content' => 'reply'],
            'receivedDateTime' => now()->toISOString(),
        ];

        $this->service->processMessage($message);
    }

    public function test_log_sync_complete_updates_email_log()
    {
        $emailLog = EmailLog::create([
            'sync_type'  => 'graph',
            'started_at' => now(),
        ]);

        $reflection = new ReflectionClass($this->service);
        $property = $reflection->getProperty('currentLog');
        $property->setAccessible(true);
        $property->setValue($this->service, $emailLog);

        $method = $reflection->getMethod('logSyncComplete');
        $method->setAccessible(true);

        $method->invoke($this->service, 5, 1);

        $emailLog->refresh();
        $this->assertEquals(5, $emailLog->processed_count);
        $this->assertEquals(1, $emailLog->error_count);
        $this->assertNotNull($emailLog->completed_at);
    }
}
