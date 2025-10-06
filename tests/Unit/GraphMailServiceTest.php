<?php

namespace Tests\Unit;

use App\Models\EmailLog;
use App\Services\Mail\AbstractEmailProcessor;
use App\Services\Mail\GraphMailService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use Tests\TestCase;
use Webkul\Email\Models\Email;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;

class GraphMailServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GraphMailService $service;

    protected EmailRepository $emailRepository;

    protected AttachmentRepository $attachmentRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->emailRepository = $this->createMock(EmailRepository::class);
        $this->attachmentRepository = $this->createMock(AttachmentRepository::class);

        $this->service = new GraphMailService(
            $this->emailRepository,
            $this->attachmentRepository
        );
    }

    public function test_implements_inbound_email_processor_contract()
    {
        $this->assertInstanceOf(\Webkul\Email\InboundEmailProcessor\Contracts\InboundEmailProcessor::class, $this->service);
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

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('getAccessToken');
        $method->setAccessible(true);

        $token = $method->invoke($this->service);

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

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('getAccessToken');
        $method->setAccessible(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to get access token');

        $method->invoke($this->service);
    }

    public function test_fetch_unread_messages_success()
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

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('fetchUnreadMessages');
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
            ->expects($this->once())
            ->method('findOneByField')
            ->with('message_id', '<msg1@example.com>')
            ->willReturn(null);

        $this->emailRepository
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($data) {
                return $data['subject'] === 'Test Subject' &&
                       $data['from'] === 'sender@example.com' &&
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

        $reflection = new \ReflectionClass($this->service);
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

        $reflection = new \ReflectionClass($this->service);
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

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractEmailAddresses');
        $method->setAccessible(true);

        $addresses = $method->invoke($this->service, $recipients);

        $this->assertEquals(['test1@example.com', 'test2@example.com'], $addresses);
    }

    public function test_parse_date_time()
    {
        $dateTime = '2025-01-28T10:00:00Z';

        $reflection = new \ReflectionClass($this->service);
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
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('logSyncStart');
        $method->setAccessible(true);

        $method->invoke($this->service);

        $this->assertDatabaseHas('email_logs', [
            'sync_type'       => 'graph',
            'processed_count' => 0,
            'error_count'     => 0,
        ]);
    }

    public function test_log_sync_complete_updates_email_log()
    {
        $emailLog = EmailLog::create([
            'sync_type'  => 'graph',
            'started_at' => now(),
        ]);

        $reflection = new \ReflectionClass($this->service);
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
