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
     * Store a Microsoft Graph attachment to disk and create the DB record.
     * Graph delivers file content as base64 in 'contentBytes'.
     */
    public function createFromGraphData(Email $email, array $graphAttachment): void
    {
        $name    = $graphAttachment['name'] ?? 'attachment';
        $content = base64_decode($graphAttachment['contentBytes'] ?? '');

        $safeBasename = SafeStorageFilename::forPathSegment($name);
        $directory    = 'emails/'.$email->id;
        $path         = $directory.'/'.$safeBasename;
        $counter      = 0;
        [$stem, $ext] = self::stemAndExtensionFromBasename($safeBasename);

        while (Storage::exists($path)) {
            $counter++;
            $path = $directory.'/'.$stem.'_'.$counter.$ext;
        }

        Storage::put($path, $content);

        $this->create([
            'email_id'     => $email->id,
            'name'         => $name,
            'content_type' => $graphAttachment['contentType'] ?? 'application/octet-stream',
            'size'         => strlen($content) ?: ($graphAttachment['size'] ?? 0),
            'path'         => $path,
        ]);
    }

    /**
     * Copy attachments from a source email to a target email (e.g. forward).
     */
    public function copyAttachmentsToEmail(Email $targetEmail, Email $sourceEmail): void
    {
        $sourceEmail->loadMissing('attachments');

        foreach ($sourceEmail->attachments as $attachment) {
            if (empty($attachment->path) || ! Storage::exists($attachment->path)) {
                continue;
            }

            $originalName = $attachment->name ?: basename($attachment->path);
            $directory = 'emails/'.$targetEmail->id;
            $safeBasename = SafeStorageFilename::forPathSegment($originalName);

            $path = $directory.'/'.$safeBasename;
            $counter = 0;
            [$stem, $extension] = self::stemAndExtensionFromBasename($safeBasename);

            while (Storage::exists($path)) {
                $counter++;
                $path = $directory.'/'.$stem.'_'.$counter.$extension;
            }

            Storage::put($path, Storage::get($attachment->path));

            $this->create([
                'email_id'     => $targetEmail->id,
                'name'         => $originalName,
                'content_type' => $attachment->content_type,
                'size'         => $attachment->size ?: Storage::size($path),
                'path'         => $path,
            ]);
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
