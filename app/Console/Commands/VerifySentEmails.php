<?php

namespace App\Console\Commands;

use App\Services\Mail\MailboxConfig;
use App\Services\Mail\MicrosoftGraphTokenService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Webkul\Email\Enums\EmailFolderEnum;
use Webkul\Email\Models\Email;

class VerifySentEmails extends Command
{
    protected $signature = 'emails:verify-sent
                            {--days=2 : Aantal dagen terug om te controleren}
                            {--mailbox= : Alleen controleren voor deze mailbox key}';

    protected $description = 'Controleer of uitgaande CRM emails aanwezig zijn in Microsoft Graph sentItems.';

    public function __construct(private readonly MicrosoftGraphTokenService $tokenService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $from = now()->subDays($days)->startOfDay();
        $filterKey = $this->option('mailbox');

        $sentFolderNames = [
            EmailFolderEnum::SENT_PRIVATESCAN->value,
            EmailFolderEnum::SENT_HERNIAPOLI->value,
        ];

        $dbEmailsQuery = Email::whereHas('folder', fn ($q) => $q->whereIn('name', $sentFolderNames))
            ->where('created_at', '>=', $from);

        if ($filterKey) {
            $dbEmailsQuery->where('mailbox_key', $filterKey);
        }

        $dbEmails = $dbEmailsQuery->get(['id', 'subject', 'message_id', 'reply_to', 'created_at', 'mailbox_key']);

        $graphIds = $this->fetchGraphSentIds($from, $filterKey);

        $rows = [];
        $confirmed = 0;
        $notFound = 0;

        foreach ($dbEmails as $email) {
            $normalizedId = trim($email->message_id, '<>');
            $inGraph = isset($graphIds[$normalizedId]);

            $inGraph ? $confirmed++ : $notFound++;

            $to = '';
            $replyTo = $email->reply_to;
            if (is_array($replyTo) && ! empty($replyTo)) {
                $first = reset($replyTo);
                $to = is_array($first) ? ($first['email'] ?? $first['address'] ?? '') : $first;
            }

            $rows[] = [
                $email->id,
                $email->mailbox_key ?? '-',
                mb_strimwidth($email->subject ?? '', 0, 50, '…'),
                $to,
                $email->created_at?->format('Y-m-d H:i'),
                $inGraph ? 'ja' : 'NEE',
            ];
        }

        $this->table(['ID', 'Mailbox', 'Onderwerp', 'Aan', 'Verzonden', 'In Graph'], $rows);

        $total = count($rows);
        $this->info("Totaal: {$total} | Bevestigd: {$confirmed} | Niet gevonden: {$notFound}");

        if ($notFound > 0) {
            $this->warn("{$notFound} email(s) niet aangetroffen in Graph sentItems.");
        }

        Log::info('emails:verify-sent completed', [
            'days'      => $days,
            'total'     => $total,
            'confirmed' => $confirmed,
            'not_found' => $notFound,
        ]);

        return Command::SUCCESS;
    }

    private function fetchGraphSentIds(Carbon $from, ?string $filterKey = null): array
    {
        $ids = [];

        foreach (MailboxConfig::all() as $key => $mailboxConfig) {
            if ($filterKey && $filterKey !== $key) {
                continue;
            }

            $address = $mailboxConfig['address'] ?? null;

            if (empty($address)) {
                continue;
            }

            try {
                $token = $this->tokenService->getAccessToken($key);
            } catch (\Exception $e) {
                $this->error("Graph token ophalen mislukt voor [{$key}]: {$e->getMessage()}");

                continue;
            }

            $url = "https://graph.microsoft.com/v1.0/users/{$address}/mailFolders('SentItems')/messages";

            $params = [
                '$filter' => "sentDateTime ge {$from->toIso8601String()}",
                '$select' => 'internetMessageId',
                '$top'    => 500,
            ];

            do {
                $response = Http::withToken($token)->get($url, $params);

                if (! $response->successful()) {
                    $this->error("Graph API fout voor [{$key}]: ".$response->status().' '.$response->body());
                    break;
                }

                $data = $response->json();

                foreach ($data['value'] ?? [] as $message) {
                    $id = trim($message['internetMessageId'] ?? '', '<>');
                    if ($id !== '') {
                        $ids[$id] = true;
                    }
                }

                $url = $data['@odata.nextLink'] ?? null;
                $params = [];
            } while ($url !== null);
        }

        return $ids;
    }
}
