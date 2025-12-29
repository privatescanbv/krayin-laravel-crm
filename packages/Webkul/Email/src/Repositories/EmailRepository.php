<?php

namespace Webkul\Email\Repositories;

use Illuminate\Container\Container;
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

    /**
     * Create.
     *
     * @return \Webkul\Email\Contracts\Email
     */
    public function create(array $data)
    {
        $uniqueId = time().'@'.config('mail.domain');

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
        }

        // Prioritize entity linking: lead_id > sales_lead_id > person_id
        // When lead_id or sales_lead_id is set, also keep person_id so email appears in both places
        if (!empty($data['lead_id'])) {
            // Lead has highest priority, remove sales_lead_id but keep person_id if set
            // This allows the email to appear both in lead view and person view
            unset($data['sales_lead_id']);
            // person_id is kept if it was provided
        } elseif (!empty($data['sales_lead_id'])) {
            // Sales lead has second priority, but also keep person_id if set
            // This allows the email to appear both in sales lead view and person view
            // person_id is kept if it was provided
        } elseif (empty($data['person_id'])) {
            // No entity IDs provided - this is allowed for some email types
            // (e.g., system emails that don't need to be linked to a specific entity)
        }

        if(!is_null($parent)) {
            //use releation of parent
            if($parent->lead_id) {
                $data['lead_id'] = $parent->lead_id;
            }
            if($parent->sales_lead_id) {
                $data['sales_lead_id)'] = $parent->sales_lead_id;
            }
            if($parent->person_id) {
                $data['person_id'] = $parent->person_id;
            }
            if($parent->clinic_id) {
                $data['clinic_id'] = $parent->clinic_id;
            }
        }

        $data = $this->sanitizeEmails(array_merge([
            'source'        => 'web',
            'from'          => $normalizedFrom,
            'user_type'     => 'admin',
            'folder_id'     => $this->getFolderId($data['is_draft'] ?? false),
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
