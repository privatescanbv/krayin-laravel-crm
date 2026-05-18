<?php

namespace Webkul\Email\Repositories;

use App\Support\SafeStorageFilename;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Webklex\PHPIMAP\Attachment as ImapAttachment;
use Webkul\Core\Eloquent\Repository;
use Webkul\Email\Contracts\Attachment;
use Webkul\Email\Contracts\Email;

class AttachmentRepository extends Repository
{
    /**
     * @return array{0: string, 1: string}
     */
    private static function stemAndExtensionFromBasename(string $basename): array
    {
        if (! preg_match('/^(.+)\.([^.]{1,40})$/u', $basename, $matches)) {
            return [$basename, ''];
        }

        return [$matches[1], '.'.$matches[2]];
    }

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
        $content = null;
        $size = null;

        if ($attachment instanceof UploadedFile) {
            $filename = $attachment->getClientOriginalName();
            $mimeType = $attachment->getClientMimeType() ?: $attachment->getMimeType();
            $content = @file_get_contents($attachment->getRealPath());
            $size = $attachment->getSize();
        } elseif ($attachment instanceof ImapAttachment) {
            $filename = $attachment->getName();
            $mimeType = $attachment->getMimeType();
            $content = $attachment->getContent();
            // Size might not be available directly; compute from content as fallback
            $size = method_exists($attachment, 'getSize') ? $attachment->getSize() : (is_string($content) ? strlen($content) : null);
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

        $originalName = $filename ?: 'attachment';
        $directory = 'emails/'.$email->id;
        $safeBasename = SafeStorageFilename::forPathSegment($originalName);

        $path = $directory.'/'.$safeBasename;
        $counter = 0;
        [$stem, $extension] = self::stemAndExtensionFromBasename($safeBasename);

        while (Storage::exists($path)) {
            $counter++;
            $path = $directory.'/'.$stem.'_'.$counter.$extension;
        }

        if ($content !== null) {
            Storage::put($path, $content);
        } else {
            // As a last resort, create an empty file to avoid failures
            Storage::put($path, '');
        }

        $attributes = [
            'path'         => $path,
            'name'         => $originalName,
            'content_type' => $mimeType,
            'size'         => $size ?: Storage::size($path),
            'email_id'     => $email->id,
        ];

        return $attributes;
    }
}
