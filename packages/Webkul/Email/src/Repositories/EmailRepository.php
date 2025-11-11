<?php

namespace Webkul\Email\Repositories;

use Illuminate\Container\Container;
use Illuminate\Support\Arr;
use Webkul\Core\Eloquent\Repository;
use Webkul\Email\Contracts\Email;
use Webkul\Email\Models\Folder;
use Webkul\Email\Enums\EmailFolderEnum;
use Webkul\Email\Models\Email as EmailModel;

class EmailRepository extends Repository
{
    public function __construct(
        protected AttachmentRepository $attachmentRepository,
        Container $container
    ) {
        parent::__construct($container);
    }

    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return Email::class;
    }

    /**
     * Create.
     *
     * @return \Webkul\Email\Contracts\Email
     */
    public function create(array $data)
    {
        $uniqueId = time().'@'.config('mail.domain');

        $referenceIds = [];

        if (isset($data['parent_id'])) {
            $parent = parent::findOrFail($data['parent_id']);

            $referenceIds = $parent->reference_ids ?? [];
        }

        $user = auth()->guard('user')->user();

        $data = $this->sanitizeEmails(array_merge([
            'source'        => 'web',
            'user_type'     => 'admin',
            'folder_id'     => $this->getFolderId($data['is_draft'] ?? false),
            'unique_id'     => $uniqueId,
            'message_id'    => $uniqueId,
            'reference_ids' => array_merge($referenceIds, [$uniqueId]),
        ], $data));

        $data = $this->normalizeFromField($data, $user);

        $email = parent::create($data);

        $this->attachmentRepository->uploadAttachments($email, $data);

        return $email;
    }

    /**
     * Update.
     *
     * @param  int  $id
     * @param  string  $attribute
     * @return \Webkul\Email\Contracts\Email
     */
    public function update(array $data, $id, $attribute = 'id')
    {
        // Add user signature to email content if not a draft and reply content exists
        if ((!isset($data['is_draft']) || !$data['is_draft']) && isset($data['reply'])) {
            $user = auth()->guard('user')->user();
            if ($user && $user->signature) {
                // Only add signature if it's not already present
                if (strpos($data['reply'], $user->signature) === false) {
                    $data['reply'] = $data['reply'] . "\n\n" . $user->signature;
                }
            }
        }

        return parent::update($this->sanitizeEmails($data), $id);
    }

    /**
     * Sanitize emails.
     *
     * @return array
     */
    public function sanitizeEmails(array $data)
    {
        if (isset($data['reply_to'])) {
            $data['reply_to'] = array_values(array_filter($data['reply_to']));
        }

        if (isset($data['cc'])) {
            $data['cc'] = array_values(array_filter($data['cc']));
        }

        if (isset($data['bcc'])) {
            $data['bcc'] = array_values(array_filter($data['bcc']));
        }

        return $data;
    }

    /**
     * Get folder ID by name
     *
     * @param bool $isDraft
     * @return int|null
     */
    protected function getFolderId($isDraft = false)
    {
        $folderEnum = $isDraft ? EmailFolderEnum::DRAFT : EmailFolderEnum::SENT;
        $folder = Folder::where('name', $folderEnum->getFolderName())->first();
        return $folder ? $folder->id : null;
    }

    /**
     * Ensure the outbound email has a normalized from field using the configured mailbox.
     */
    protected function normalizeFromField(array $data, $user): array
    {
        $currentFrom = $data['from'] ?? null;
        $defaultMailbox = config('mail.graph.mailbox') ?: config('mail.from.address');
        $defaultName = Arr::get($data, 'name') ?: ($user?->name ?? config('mail.from.name'));

        if (is_array($currentFrom) && isset($currentFrom['email'])) {
            $normalized = [
                'email' => trim($currentFrom['email']),
                'name'  => Arr::get($currentFrom, 'name', $defaultName) ?: '',
            ];
        } elseif (is_string($currentFrom) && $currentFrom !== '') {
            $normalized = EmailModel::normalizeFromField($currentFrom, $defaultName);
        } elseif ($currentFrom instanceof \Stringable) {
            $normalized = EmailModel::normalizeFromField((string) $currentFrom, $defaultName);
        } else {
            $fromAddress = $defaultMailbox;

            if (! $fromAddress) {
                return $data;
            }

            $normalized = EmailModel::normalizeFromField($fromAddress, $defaultName);
        }

        $data['from'] = $normalized;

        if (empty($data['name'])) {
            $data['name'] = $normalized['name'] ?: $defaultName;
        }

        return $data;
    }
}
