<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Mockery as m;
use Tests\TestCase;
use Webkul\Email\InboundEmailProcessor\WebklexImapEmailProcessor;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;

class WebklexImapEmailProcessorTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_process_message_creates_email_when_none_exists(): void
    {
        $emailRepository = m::mock(EmailRepository::class);
        $attachmentRepository = m::mock(AttachmentRepository::class);

        $message = $this->makeFakeMessage();

        // No existing email found by message_id
        $emailRepository->shouldReceive('findOneByField')
            ->once()
            ->with('message_id', m::type('string'))
            ->andReturn(null);

        // No parent found via reply-to/in-reply-to/references
        $emailRepository->shouldReceive('findOneWhere')->zeroOrMoreTimes()->andReturn(null);

        // Expect create to be called and return a new email model
        $emailRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                // Minimal sanity checks to ensure required fields are present and no exceptions occur
                return isset($data['from'], $data['subject'], $data['message_id'])
                    && array_key_exists('folder_id', $data) // Can be null if folder not found
                    && array_key_exists('parent_id', $data) && $data['parent_id'] === null
                    && isset($data['reference_ids']) && is_array($data['reference_ids']) && count($data['reference_ids']) >= 1;
            }))
            ->andReturn((object) ['id' => 123]);

        // No attachments expected
        $attachmentRepository->shouldReceive('uploadAttachments')->never();

        $processor = new WebklexImapEmailProcessor($emailRepository, $attachmentRepository);

        // Should not throw (previously crashed on null id in log)
        $processor->processMessage($message);

        $this->assertTrue(true);
    }

    public function test_process_message_with_parent_email_inherits_and_logs_safely(): void
    {
        $emailRepository = m::mock(EmailRepository::class);
        $attachmentRepository = m::mock(AttachmentRepository::class);

        $message = $this->makeFakeMessage();

        $parentEmail = (object) [
            'id'            => 77,
            'folder_id'     => 1, // inbox folder
            'reference_ids' => ['<old@id>'],
            'activity_id'   => 9001,
            'lead_id'       => 2002,
            'person_id'     => 3003,
        ];

        // Not found by message_id
        $emailRepository->shouldReceive('findOneByField')
            ->once()
            ->andReturn(null);

        // Found parent via one of the fallback lookups
        $emailRepository->shouldReceive('findOneWhere')
            ->atLeast()->once()
            ->andReturn($parentEmail);

        // Update should be called to merge folder and references
        $emailRepository->shouldReceive('update')
            ->once()
            ->with(m::on(function ($data) {
                return array_key_exists('folder_id', $data) && isset($data['reference_ids']);
            }), $parentEmail->id)
            ->andReturn($parentEmail);

        // Create new email inheriting from parent
        $emailRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($parentEmail) {
                return ($data['parent_id'] === $parentEmail->id)
                    && ($data['activity_id'] === $parentEmail->activity_id)
                    && ($data['lead_id'] === $parentEmail->lead_id)
                    && ($data['person_id'] === $parentEmail->person_id)
                    && array_key_exists('folder_id', $data) // Can be null if folder not found
                    && isset($data['reference_ids']) && is_array($data['reference_ids']) && count($data['reference_ids']) >= 1;
            }))
            ->andReturn((object) ['id' => 555]);

        $attachmentRepository->shouldReceive('uploadAttachments')->never();

        $processor = new WebklexImapEmailProcessor($emailRepository, $attachmentRepository);

        // Should not throw and should inherit correctly
        $processor->processMessage($message);

        $this->assertTrue(true);
    }

    /**
     * Build a fake message object that mimics the subset of Webklex Message API we use.
     */
    private function makeFakeMessage(array $overrides = []): object
    {
        $messageId = $overrides['message_id'] ?? '<test-id@example.com>';

        $attributes = [
            'message_id' => new Collection([$messageId]),
            'from'       => new Collection([(object) ['mail' => 'sender@example.com', 'personal' => 'Sender']]),
            'subject'    => new Collection(['Test Subject']),
            'to'         => new Collection([(object) ['mail' => 'to@example.com']]),
        ];

        if (array_key_exists('in_reply_to', $overrides)) {
            $attributes['in_reply_to'] = new Collection([$overrides['in_reply_to']]);
        }

        if (array_key_exists('references', $overrides)) {
            $attributes['references'] = new Collection($overrides['references']);
        }

        $folder = new class
        {
            public $name = 'INBOX';
        };

        return new class($attributes, $folder)
        {
            public array $attributes;

            public object $folder;

            public array $bodies = [
                'html' => '<p>Hello</p>',
                'text' => 'Hello',
            ];

            public function __construct(array $attributes, object $folder)
            {
                $this->attributes = $attributes;
                $this->folder = $folder;
                $this->date = new class
                {
                    public function toDate()
                    {
                        return Carbon::now();
                    }
                };
            }

            public function getAttributes(): array
            {
                return $this->attributes;
            }

            public function getFolder(): object
            {
                return $this->folder;
            }

            public function flags(): object
            {
                return new class
                {
                    public function has(string $flag): bool
                    {
                        return false;
                    }
                };
            }

            public function hasAttachments(): bool
            {
                return false;
            }

            public function getAttachments(): array
            {
                return [];
            }
        };
    }
}
