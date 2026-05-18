<?php

namespace App\Console\Commands;

use App\Services\Mail\MicrosoftGraphTokenService;
use App\Support\SafeStorageFilename;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Fix email_attachments records that have an empty path.
 *
 * Root cause: GraphMailService::processAttachments() used to call
 * attachmentRepository->create() without storing file content or setting path.
 * This command re-fetches the attachment bytes from Microsoft Graph and
 * stores them, then updates the path in the database.
 */
class FixEmailAttachmentPaths extends Command
{
    protected $signature = 'mail:fix-attachment-paths
                            {--dry-run : List affected records without making changes}
                            {--limit=100 : Maximum number of attachments to process}';

    protected $description = 'Re-fetch and store email attachments that have an empty path field';

    private string $baseUrl = 'https://graph.microsoft.com/v1.0';

    private string $mailbox = '';

    public function __construct(private readonly MicrosoftGraphTokenService $tokenService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $mailbox = config('mail.graph.mailbox', '');

        if (empty($mailbox)) {
            $this->error('mail.graph.mailbox is not configured.');

            return self::FAILURE;
        }

        $this->mailbox = $mailbox;

        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $broken = DB::table('email_attachments as ea')
            ->join('emails as e', 'e.id', '=', 'ea.email_id')
            ->whereNull('ea.path')
            ->orWhere('ea.path', '')
            ->select('ea.id', 'ea.email_id', 'ea.name', 'ea.path', 'e.unique_id')
            ->limit($limit)
            ->get();

        if ($broken->isEmpty()) {
            $this->info('No attachments with empty path found.');

            return self::SUCCESS;
        }

        $this->info("Found {$broken->count()} attachment(s) with empty path.");

        if ($dryRun) {
            $this->table(
                ['attachment_id', 'email_id', 'name', 'email unique_id'],
                $broken->map(fn ($r) => [$r->id, $r->email_id, $r->name, $r->unique_id])->toArray()
            );

            return self::SUCCESS;
        }

        $token = $this->tokenService->getAccessToken();
        $fixed = 0;
        $skipped = 0;

        foreach ($broken as $attachment) {
            $this->line("Processing attachment {$attachment->id} ({$attachment->name}) for email {$attachment->email_id}...");

            $graphMessageId = $this->findGraphMessageId($attachment->unique_id, $token);

            if (! $graphMessageId) {
                $this->warn("  ✗ Graph message not found for unique_id: {$attachment->unique_id}");
                $skipped++;

                continue;
            }

            $graphAttachment = $this->fetchMatchingAttachment($graphMessageId, $attachment->name, $token);

            if (! $graphAttachment) {
                $this->warn("  ✗ Attachment '{$attachment->name}' not found in Graph message {$graphMessageId}");
                $skipped++;

                continue;
            }

            $path = $this->storeAttachment((int) $attachment->email_id, $graphAttachment);

            DB::table('email_attachments')
                ->where('id', $attachment->id)
                ->update([
                    'path'       => $path,
                    'size'       => strlen(base64_decode($graphAttachment['contentBytes'] ?? '')),
                    'updated_at' => now(),
                ]);

            $this->line("  ✓ Stored at {$path}");
            $fixed++;
        }

        $this->info("Done. Fixed: {$fixed}, skipped: {$skipped}.");

        if ($skipped > 0) {
            $this->warn('Run again to retry skipped items, or check Graph API access for those emails.');
        }

        return self::SUCCESS;
    }

    /**
     * Find the Graph internal message ID by searching for the SMTP internetMessageId.
     */
    private function findGraphMessageId(string $internetMessageId, string $token): ?string
    {
        // internetMessageId in Graph filter requires exact match including angle brackets
        $filter = "internetMessageId eq '".addslashes($internetMessageId)."'";
        $url = "{$this->baseUrl}/users/{$this->mailbox}/messages?\$filter=".urlencode($filter).'&$select=id,hasAttachments';

        try {
            $response = Http::withToken($token)->timeout(10)->get($url);

            if ($response->successful()) {
                $messages = $response->json('value') ?? [];

                return ! empty($messages) ? ($messages[0]['id'] ?? null) : null;
            }

            Log::warning('FixEmailAttachmentPaths: failed to search Graph message', [
                'status'             => $response->status(),
                'internet_messageid' => $internetMessageId,
            ]);
        } catch (\Exception $e) {
            Log::error('FixEmailAttachmentPaths: exception searching Graph', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Fetch attachments for a Graph message and return the one matching $name.
     */
    private function fetchMatchingAttachment(string $graphMessageId, string $name, string $token): ?array
    {
        $url = "{$this->baseUrl}/users/{$this->mailbox}/messages/{$graphMessageId}/attachments";

        try {
            $response = Http::withToken($token)->timeout(30)->get($url);

            if ($response->successful()) {
                foreach ($response->json('value') ?? [] as $att) {
                    if (($att['name'] ?? '') === $name) {
                        return $att;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('FixEmailAttachmentPaths: exception fetching attachments', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Store the file content to Storage and return the path.
     */
    private function storeAttachment(int $emailId, array $graphAttachment): string
    {
        $name = $graphAttachment['name'] ?? 'attachment';
        $content = base64_decode($graphAttachment['contentBytes'] ?? '');
        $safeBasename = SafeStorageFilename::forPathSegment($name);
        $directory = 'emails/'.$emailId;
        $path = $directory.'/'.$safeBasename;
        $counter = 0;

        while (Storage::exists($path)) {
            $counter++;
            [$stem, $ext] = $this->stemAndExtension($safeBasename);
            $path = $directory.'/'.$stem.'_'.$counter.$ext;
        }

        Storage::put($path, $content);

        return $path;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function stemAndExtension(string $basename): array
    {
        if (! preg_match('/^(.+)\.([^.]{1,40})$/u', $basename, $matches)) {
            return [$basename, ''];
        }

        return [$matches[1], '.'.$matches[2]];
    }
}
