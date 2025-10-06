<?php

namespace Tests\Unit;

use App\Models\EmailLog;
use App\Services\Mail\AbstractEmailProcessor;
use App\Services\Mail\GraphMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;
use Webkul\Email\Models\Email;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;

class AbstractEmailProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected AbstractEmailProcessor $processor;

    protected EmailRepository $emailRepository;

    protected AttachmentRepository $attachmentRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->emailRepository = $this->createMock(EmailRepository::class);
        $this->attachmentRepository = $this->createMock(AttachmentRepository::class);

        // Use GraphMailService as a concrete implementation for testing
        $this->processor = new GraphMailService(
            $this->emailRepository,
            $this->attachmentRepository
        );
    }

    public function test_implements_inbound_email_processor_contract()
    {
        $this->assertInstanceOf(\Webkul\Email\InboundEmailProcessor\Contracts\InboundEmailProcessor::class, $this->processor);
    }

    public function test_process_message_handles_null_input()
    {
        $this->emailRepository
            ->expects($this->never())
            ->method('findOneByField');

        $this->emailRepository
            ->expects($this->never())
            ->method('create');

        $this->processor->processMessage(null);
    }

    public function test_process_message_handles_invalid_input()
    {
        $this->emailRepository
            ->expects($this->never())
            ->method('findOneByField');

        $this->emailRepository
            ->expects($this->never())
            ->method('create');

        $this->processor->processMessage('invalid input');
    }

    public function test_log_sync_start_creates_email_log()
    {
        $reflection = new ReflectionClass($this->processor);
        $method = $reflection->getMethod('logSyncStart');
        $method->setAccessible(true);

        $method->invoke($this->processor);

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

        $reflection = new ReflectionClass($this->processor);
        $property = $reflection->getProperty('currentLog');
        $property->setAccessible(true);
        $property->setValue($this->processor, $emailLog);

        $method = $reflection->getMethod('logSyncComplete');
        $method->setAccessible(true);

        $method->invoke($this->processor, 5, 1);

        $emailLog->refresh();
        $this->assertEquals(5, $emailLog->processed_count);
        $this->assertEquals(1, $emailLog->error_count);
        $this->assertNotNull($emailLog->completed_at);
    }

    public function test_log_sync_error_updates_email_log()
    {
        $emailLog = EmailLog::create([
            'sync_type'  => 'graph',
            'started_at' => now(),
        ]);

        $reflection = new ReflectionClass($this->processor);
        $property = $reflection->getProperty('currentLog');
        $property->setAccessible(true);
        $property->setValue($this->processor, $emailLog);

        $method = $reflection->getMethod('logSyncError');
        $method->setAccessible(true);

        $method->invoke($this->processor, 'Test error message');

        $emailLog->refresh();
        $this->assertEquals('Test error message', $emailLog->error_message);
        $this->assertNotNull($emailLog->completed_at);
    }

    public function test_process_message_skips_duplicate_by_message_id()
    {
        $messageId = '<test-message-id@example.com>';
        $existingEmail = new Email(['id' => 1, 'message_id' => $messageId]);

        $this->emailRepository
            ->expects($this->once())
            ->method('findOneByField')
            ->with('message_id', $messageId)
            ->willReturn($existingEmail);

        $this->emailRepository
            ->expects($this->never())
            ->method('create');

        // Create a mock message
        $message = [
            'id'                => 'test-id',
            'internetMessageId' => $messageId,
            'from'              => ['emailAddress' => ['address' => 'test@example.com', 'name' => 'Test User']],
            'subject'           => 'Test Subject',
            'toRecipients'      => [],
            'ccRecipients'      => [],
            'bccRecipients'     => [],
            'replyTo'           => [],
            'isRead'            => false,
            'hasAttachments'    => false,
            'body'              => ['content' => 'Test body', 'contentType' => 'text'],
            'receivedDateTime'  => now()->toISOString(),
        ];

        $this->processor->processMessage($message);
    }

    public function test_process_message_skips_duplicate_by_unique_id()
    {
        $messageId = '<test-message-id@example.com>';
        $existingEmail = new Email(['id' => 1, 'unique_id' => $messageId]);

        // First call returns null (no existing email by message_id)
        // Second call returns existing email by unique_id (which is the same as message_id)
        $this->emailRepository
            ->expects($this->exactly(2))
            ->method('findOneByField')
            ->willReturnMap([
                ['message_id', $messageId, null],
                ['unique_id', $messageId, $existingEmail],
            ]);

        $this->emailRepository
            ->expects($this->never())
            ->method('create');

        // Create a mock message
        $message = [
            'id'                => 'test-id',
            'internetMessageId' => $messageId,
            'from'              => ['emailAddress' => ['address' => 'test@example.com', 'name' => 'Test User']],
            'subject'           => 'Test Subject',
            'toRecipients'      => [],
            'ccRecipients'      => [],
            'bccRecipients'     => [],
            'replyTo'           => [],
            'isRead'            => false,
            'hasAttachments'    => false,
            'body'              => ['content' => 'Test body', 'contentType' => 'text'],
            'receivedDateTime'  => now()->toISOString(),
        ];

        $this->processor->processMessage($message);
    }

    public function test_process_message_creates_email_successfully()
    {
        $messageId = '<test-message-id@example.com>';
        $createdEmail = new Email(['id' => 1, 'message_id' => $messageId, 'unique_id' => $messageId]);

        // No existing email found
        $this->emailRepository
            ->expects($this->exactly(2))
            ->method('findOneByField')
            ->willReturn(null);

        // Create succeeds
        $this->emailRepository
            ->expects($this->once())
            ->method('create')
            ->willReturn($createdEmail);

        // Create a mock message
        $message = [
            'id'                => 'test-id',
            'internetMessageId' => $messageId,
            'from'              => ['emailAddress' => ['address' => 'test@example.com', 'name' => 'Test User']],
            'subject'           => 'Test Subject',
            'toRecipients'      => [],
            'ccRecipients'      => [],
            'bccRecipients'     => [],
            'replyTo'           => [],
            'isRead'            => false,
            'hasAttachments'    => false,
            'body'              => ['content' => 'Test body', 'contentType' => 'text'],
            'receivedDateTime'  => now()->toISOString(),
        ];

        // Should not throw an exception
        $this->processor->processMessage($message);
    }
}
