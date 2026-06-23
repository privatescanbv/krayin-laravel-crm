<?php

namespace App\Console\Commands;

use App\Enums\PaymentType;
use App\Models\Order;
use Illuminate\Console\Command;

class FindUnrefundedSurplusOrders extends Command
{
    protected $signature = 'orders:find-unrefunded-surplus';

    protected $description = 'Lijst van orders waarbij de klant meer betaalde dan het huidige totaal, zonder uitstaande terugbetaling (voor handmatige review)';

    public function handle(): int
    {
        $orders = Order::whereHas('orderItems', fn ($q) => $q->where('status', 'lost'))
            ->with('payments', 'orderItems')
            ->get();

        $rows = [];

        foreach ($orders as $order) {
            $netPaid = $order->netPaidAmount();
            $total = round((float) $order->total_price, 2);
            $surplus = round($netPaid - $total, 2);

            if ($surplus <= 0.01) {
                continue;
            }

            $hasOpenRefund = $order->payments
                ->where('type', PaymentType::REFUND)
                ->whereNull('paid_at')
                ->isNotEmpty();

            if ($hasOpenRefund) {
                continue;
            }

            $rows[] = [
                $order->id,
                $order->order_number ?? '—',
                number_format($total, 2, ',', '.'),
                number_format($netPaid, 2, ',', '.'),
                number_format($surplus, 2, ',', '.'),
            ];
        }

        if (empty($rows)) {
            $this->info('Geen orders gevonden met onverwerkt surplus.');

            return self::SUCCESS;
        }

        $this->table(
            ['Order ID', 'Ordernummer', 'Totaalprijs', 'Netto betaald', 'Surplus'],
            $rows
        );

        $this->warn(count($rows).' order(s) gevonden. Controleer handmatig of terugbetaling al buiten het systeem is verwerkt.');

        return self::SUCCESS;
    }
}
