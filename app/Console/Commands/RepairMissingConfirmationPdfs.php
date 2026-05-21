<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Storage\DocumentStorage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;
use Webkul\Activity\Models\File as ActivityFile;
use Webkul\Core\Traits\PDFHandler;

class RepairMissingConfirmationPdfs extends Command
{
    use PDFHandler;

    protected $signature = 'activities:repair-confirmation-pdfs {--dry-run} {--limit=100}';

    protected $description = 'Regenerate missing order confirmation PDFs for activity file records';

    public function handle(DocumentStorage $documentStorage): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $rows = [];

        $files = ActivityFile::query()
            ->with('activity')
            ->whereHas('activity', function (Builder $query) {
                $query->where('additional->document_type', 'order_confirmation');
            })
            ->limit($limit)
            ->get();

        foreach ($files as $file) {
            $rows[] = $this->repairFile($file, $documentStorage, $dryRun);
        }

        $this->table(['file_id', 'activity_id', 'order_id', 'status'], $rows);

        return collect($rows)->contains(fn (array $row) => $row['status'] === 'failed') ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{file_id: int|null, activity_id: int|null, order_id: int|null, status: string}
     */
    private function repairFile(ActivityFile $file, DocumentStorage $documentStorage, bool $dryRun): array
    {
        $activity = $file->activity;
        $orderId = $activity?->order_id;

        try {
            if ($file->resolveDisk() !== null) {
                return $this->row($file, $orderId, 'skipped');
            }

            if ($orderId === null) {
                return $this->row($file, null, 'skipped');
            }

            $order = Order::query()->find($orderId);

            if ($order === null || empty($order->confirmation_letter_content)) {
                return $this->row($file, $orderId, 'skipped');
            }

            if ($dryRun) {
                return $this->row($file, $orderId, 'dry-run');
            }

            $html = mb_convert_encoding($order->confirmation_letter_content, 'HTML-ENTITIES', 'UTF-8');
            $pdfContent = Pdf::loadHTML($this->adjustArabicAndPersianContent($html))
                ->setPaper('A4')
                ->output();

            $documentStorage->put($file->path, $pdfContent);

            return $this->row($file, $orderId, 'regenerated');
        } catch (Throwable $e) {
            $this->error("Failed to repair activity file {$file->id}: {$e->getMessage()}");

            return $this->row($file, $orderId, 'failed');
        }
    }

    /**
     * @return array{file_id: int|null, activity_id: int|null, order_id: int|null, status: string}
     */
    private function row(ActivityFile $file, ?int $orderId, string $status): array
    {
        return [
            'file_id'     => $file->id,
            'activity_id' => $file->activity_id,
            'order_id'    => $orderId,
            'status'      => $status,
        ];
    }
}
