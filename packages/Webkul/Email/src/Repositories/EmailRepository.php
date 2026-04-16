<?php

namespace Webkul\Email\Repositories;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Log;
use Webkul\Core\Eloquent\Repository;
use Webkul\Email\Contracts\Email;
use Webkul\Email\Models\Email as EmailModel;
use Webkul\Email\Models\Folder;
use Webkul\Email\Enums\EmailFolderEnum;

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

    public function createWith(array $data, array $linkEmailToEntities): Email {
        // TODO refactor later, keep this interface and remove create(data)
        return $this->create(array_merge($data, $linkEmailToEntities));
    }
    /**
     * Create.
     *
     * @return \Webkul\Email\Contracts\Email
     */
    public function create(array $data): Email
    {
        $uniqueId = time().'.'.bin2hex(random_bytes(8)).'@'.config('mail.domain');

        $referenceIds = [];

        $parent = null;
        if (isset($data['parent_id'])) {
            $parent = parent::findOrFail($data['parent_id']);

            $referenceIds = $parent->reference_ids ?? [];
        }

        // Normalize from field to array format if not already normalized
        // Respect null if explicitly set (for test compatibility)
        if (isset($data['from']) && $data['from'] === null) {
            $normalizedFrom = null;
        } elseif (isset($data['from']) && is_array($data['from'])) {
            // Already normalized array, use as is
            $normalizedFrom = $data['from'];
        } else {
            // Normalize string to array format
            $fromAddress = $data['from'] ?? config('mail.from.address');
            $fromName = config('mail.from.name', 'PrivateScan');
            $normalizedFrom = EmailModel::normalizeFromField($fromAddress, $fromName);
        } // (e.g., system emails that don't need to be linked to a specific entity)

        if(!is_null($parent)) {
            //use releation of parent
            if($parent->lead_id) {
                $data['lead_id'] = $parent->lead_id;
            }
            if($parent->sales_lead_id) {
                $data['sales_lead_id'] = $parent->sales_lead_id;
            }
            if($parent->person_id) {
                $data['person_id'] = $parent->person_id;
            }
            if($parent->clinic_id) {
                $data['clinic_id'] = $parent->clinic_id;
            }
            if($parent->order_id) {
                $data['order_id'] = $parent->order_id;
            }
        }

        $isDraft = $data['is_draft'] ?? false;

        $data = $this->sanitizeEmails(array_merge([
            'source'        => 'web',
            'from'          => $normalizedFrom,
            'user_type'     => 'admin',
            'is_read'       => $isDraft ? 0 : 1,
            'folder_id'     => $this->getFolderId($isDraft),
            'unique_id'     => $uniqueId,
            'message_id'    => $uniqueId,
            'reference_ids' => array_merge($referenceIds, [$uniqueId]),
        ], $data));

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
     * Move email to "Verwerkt" folder if it is currently in an inbox-type folder.
     */
    public function moveToProcessedIfInbox(int $emailId): void
    {
        $email = $this->find($emailId);

        if (! $email) {
            return;
        }

        $inboxFolderNames = [
            EmailFolderEnum::INBOX->getFolderName(),
            EmailFolderEnum::PRIVATESCAN_WEBFORM->getFolderName(),
            EmailFolderEnum::HERNIA_WEBFORM->getFolderName(),
            EmailFolderEnum::CLINICS->getFolderName(),
            EmailFolderEnum::NEWSLETTER->getFolderName(),
        ];

        $currentFolder = Folder::find($email->folder_id);

        if (! $currentFolder || ! in_array($currentFolder->name, $inboxFolderNames, true)) {
            return;
        }

        $processedFolder = Folder::where('name', EmailFolderEnum::PROCESSED->getFolderName())->first();

        if (! $processedFolder) {
            Log::warning('EmailRepository: "Verwerkt" folder not found, email not moved to processed.', ['email_id' => $emailId]);

            return;
        }

        parent::update(['folder_id' => $processedFolder->id], $emailId);
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
    protected function getFolderId($isDraft = false): ?int
    {
        $folderEnum = $isDraft ? EmailFolderEnum::DRAFT : EmailFolderEnum::SENT;
        $folder = Folder::where('name', $folderEnum->getFolderName())->first();
        return $folder ? $folder->id : null;
    }
}
