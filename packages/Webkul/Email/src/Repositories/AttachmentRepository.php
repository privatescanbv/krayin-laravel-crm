<?php

namespace Webkul\Email\Repositories;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Webkul\Core\Eloquent\Repository;
use Webkul\Email\Contracts\Attachment;
use Webkul\Email\Contracts\Email;
use Webklex\PHPIMAP\Attachment as ImapAttachment;

class AttachmentRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return Attachment::class;
    }

    /**
     * Upload attachments.
     */
    public function uploadAttachments(Email $email, array $data): void
    {
        if (
            empty($data['attachments'])
            || empty($data['source'])
        ) {
            return;
        }

        foreach ($data['attachments'] as $attachment) {
            $attributes = $this->prepareData($email, $attachment);

            if (
                ! empty($attachment->contentId)
                && $data['source'] === 'email'
            ) {
                $attributes['content_id'] = $attachment->contentId;
            }

            $this->create($attributes);
        }
    }

    /**
     * Get the path for the attachment.
     */
    private function prepareData(Email $email, $attachment): array
    {
        $filename = null;
        $mimeType = null;
        $content  = null;
        $size     = null;

        if ($attachment instanceof UploadedFile) {
            $filename = $attachment->getClientOriginalName();
            $mimeType = $attachment->getClientMimeType() ?: $attachment->getMimeType();
            $content  = @file_get_contents($attachment->getRealPath());
            $size     = $attachment->getSize();
        } elseif ($attachment instanceof ImapAttachment) {
            $filename = $attachment->getName();
            $mimeType = $attachment->getMimeType();
            $content  = $attachment->getContent();
            // Size might not be available directly; compute from content as fallback
            $size     = method_exists($attachment, 'getSize') ? $attachment->getSize() : (is_string($content) ? strlen($content) : null);
        } else {
            // Unsupported type; attempt to best-effort handle if it exposes similar API
            if (is_object($attachment)) {
                $filename = method_exists($attachment, 'getClientOriginalName') ? $attachment->getClientOriginalName() : (method_exists($attachment, 'getName') ? $attachment->getName() : 'attachment');
                $mimeType = method_exists($attachment, 'getClientMimeType') ? $attachment->getClientMimeType() : (method_exists($attachment, 'getMimeType') ? $attachment->getMimeType() : null);
                if (method_exists($attachment, 'getContent')) {
                    $content = $attachment->getContent();
                } elseif (method_exists($attachment, 'getRealPath')) {
                    $content = @file_get_contents($attachment->getRealPath());
                }
                $size = method_exists($attachment, 'getSize') ? $attachment->getSize() : (is_string($content) ? strlen($content) : null);
            }
        }

        $filename = $filename ?: 'attachment';
        $path = 'emails/'.$email->id.'/'.$filename;

        if ($content !== null) {
            Storage::put($path, $content);
        } else {
            // As a last resort, create an empty file to avoid failures
            Storage::put($path, '');
        }

        $attributes = [
            'path'         => $path,
            'name'         => $filename,
            'content_type' => $mimeType,
            'size'         => $size ?: Storage::size($path),
            'email_id'     => $email->id,
        ];

        return $attributes;
    }
}
