<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Every order item belongs to a patient (the order form already requires it). Enforce it in the database:
 * legacy items without a person get one (see fillMissingPersons()), the column becomes NOT NULL and the foreign
 * key changes from SET NULL to RESTRICT, so a hard delete of a person can never leave person-less items behind.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Nothing is written when an item stays person-less: the check below throws and rolls the fill back.
        DB::transaction(function () {
            $this->fillMissingPersons();

            $remaining = $this->itemsWithoutPerson();

            if ($remaining->isNotEmpty()) {
                throw new RuntimeException('Order items without a person and without any person on their sale or lead; assign one by hand first: '
                    .$remaining->map(fn ($row) => "item {$row->id} (order {$row->order_id}, sale ".($row->sales_lead_id ?? '-').')')->implode(', '));
            }
        });

        Schema::table('order_items', fn (Blueprint $table) => $table->dropForeign(['person_id']));
        Schema::table('order_items', fn (Blueprint $table) => $table->unsignedInteger('person_id')->nullable(false)->change());
        Schema::table('order_items', fn (Blueprint $table) => $table->foreign('person_id')->references('id')->on('persons')->restrictOnDelete());
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropForeign(['person_id']));
        Schema::table('order_items', fn (Blueprint $table) => $table->unsignedInteger('person_id')->nullable()->change());
        Schema::table('order_items', fn (Blueprint $table) => $table->foreign('person_id')->references('id')->on('persons')->nullOnDelete());
    }

    /**
     * Person of the sale (the first one when there are several), else the first person of the sale's lead (lowest person id: `lead_persons` has no id column).
     * Soft-deleted persons come last (a live person wins). Items on a sale with several persons are
     * a guess and are logged.
     */
    private function fillMissingPersons(): void
    {
        $this->itemsWithoutPerson()->groupBy(fn ($row) => $row->sales_lead_id ?? 0)->each(function ($items, $salesLeadId) {
            if ($salesLeadId === 0) {
                return;
            }

            $salePersons = DB::table('saleslead_persons as sp')
                ->join('persons as p', 'p.id', '=', 'sp.person_id')
                ->where('sp.saleslead_id', $salesLeadId)
                ->orderByRaw('p.deleted_at is not null')
                ->orderBy('sp.id')
                ->pluck('sp.person_id');

            $personId = $salePersons->first() ?? $this->firstLeadPerson((int) $salesLeadId);

            if ($personId === null) {
                return;
            }

            DB::table('order_items')->whereIn('id', $items->pluck('id'))->update(['person_id' => $personId]);

            if ($salePersons->count() !== 1) {
                Log::warning('order_items.person_id guessed from the sale or lead', [
                    'order_item_ids'  => $items->pluck('id')->all(),
                    'sales_lead_id'   => (int) $salesLeadId,
                    'person_id'       => $personId,
                    'persons_on_sale' => $salePersons->count(),
                ]);
            }
        });
    }

    private function firstLeadPerson(int $salesLeadId): ?int
    {
        $leadId = DB::table('salesleads')->where('id', $salesLeadId)->value('lead_id');

        if ($leadId === null) {
            return null;
        }

        return DB::table('lead_persons as lp')
            ->join('persons as p', 'p.id', '=', 'lp.person_id')
            ->where('lp.lead_id', $leadId)
            ->orderByRaw('p.deleted_at is not null')
            ->orderBy('lp.person_id')
            ->value('lp.person_id');
    }

    /**
     * @return Collection<int, object{id: int, order_id: int, sales_lead_id: int|null}>
     */
    private function itemsWithoutPerson(): Collection
    {
        return DB::table('order_items as oi')
            ->leftJoin('orders as o', 'o.id', '=', 'oi.order_id')
            ->whereNull('oi.person_id')
            ->orderBy('oi.id')
            ->get(['oi.id', 'oi.order_id', 'o.sales_lead_id']);
    }
};
