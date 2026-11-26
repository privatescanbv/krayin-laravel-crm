<?php

namespace App\Services\Repair;

use App\Enums\Departments;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStage;
use App\Enums\PurchasePriceType;
use App\Models\Address;
use App\Models\Department;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\ResourceOrderItem;
use App\Repositories\AddressRepository;
use App\Repositories\SalesLeadRepository;
use App\Services\OrderCheckService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;

/**
 * One-off repair: SugarCRM orders that were skipped because they had no usable lead during the import.
 *
 * The orders are read from a gzipped JSON file that holds patient data and is therefore placed on the
 * server by hand instead of being committed. Everything is matched on stable keys (external ids,
 * product, department and pipeline stage names), never on ids from the export database.
 *
 * Their tasks and mails come from a second file, restored by {@see OrderHistoryRestorer}.
 */
class MissingOrderRestorer
{
    public const DATA_FILE = 'app/herstel/ontbrekende-orders.json.gz';

    public const MANIFEST_FILE = 'app/herstel/ontbrekende-orders-ingevoegd.json';

    public const HISTORY_FILE = 'app/herstel/ontbrekende-orders-activiteiten.jsonl.gz';

    public const HISTORY_MANIFEST_FILE = 'app/herstel/ontbrekende-orders-activiteiten-ingevoegd.json';

    public const ORDER_NUMBERS = ['202500393', '202501067', '202600044', '202600687'];

    /** @var array<string, list<int>> */
    private array $inserted = [];

    public function __construct(
        private readonly SalesLeadRepository $salesLeadRepository,
        private readonly AddressRepository $addressRepository,
        private readonly OrderCheckService $orderCheckService,
        private readonly OrderHistoryRestorer $historyRestorer,
    ) {}

    /**
     * @return array{orders: int, leads: int, persons: int}|null null when there is nothing to repair
     */
    public function restore(): ?array
    {
        // Fresh and test databases migrate too: leave the files on disk untouched there.
        if (! DB::table('orders')->whereNotNull('external_id')->exists()) {
            return null;
        }

        if (DB::table('orders')->whereIn('order_number', self::ORDER_NUMBERS)->count() === count(self::ORDER_NUMBERS)) {
            return null;
        }

        foreach ([self::DATA_FILE, self::HISTORY_FILE] as $file) {
            if (! File::exists(storage_path($file))) {
                throw new RuntimeException(
                    'Er ontbreken SugarCRM-orders, maar het databestand ontbreekt: '.storage_path($file)
                    .'. Zet het bestand neer en draai de migratie opnieuw.'
                );
            }
        }

        $data = json_decode(gzdecode(File::get(storage_path(self::DATA_FILE))) ?: '', true, flags: JSON_THROW_ON_ERROR);

        $this->inserted = array_fill_keys(['order_ids', 'saleslead_ids', 'lead_ids', 'person_ids', 'organization_ids', 'address_ids'], []);
        $webhooksEnabled = config('webhook.enabled');
        Config::set('webhook.enabled', false);

        try {
            DB::transaction(function () use ($data) {
                foreach ($data['orders'] as $record) {
                    $this->restoreOrder($record);
                }

                $this->historyRestorer->restore(self::HISTORY_FILE, self::HISTORY_MANIFEST_FILE);
            });
        } finally {
            Config::set('webhook.enabled', $webhooksEnabled);
        }

        $this->rememberInserted();

        Log::info('Missing SugarCRM orders restored', ['actor' => 'migration', ...$this->inserted]);

        return [
            'orders'  => count($this->inserted['order_ids']),
            'leads'   => count($this->inserted['lead_ids']),
            'persons' => count($this->inserted['person_ids']),
        ];
    }

    public function revert(): void
    {
        $path = storage_path(self::MANIFEST_FILE);

        if (! File::exists($path)) {
            return;
        }

        $manifest = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($manifest) {
            $this->historyRestorer->revert(self::HISTORY_MANIFEST_FILE);

            $itemIds = DB::table('order_items')->whereIn('order_id', $manifest['order_ids'])->pluck('id');

            DB::table('resource_orderitem')->whereIn('orderitem_id', $itemIds)->delete();
            DB::table('purchase_prices')
                ->where('priceable_type', (new OrderItem)->getMorphClass())
                ->whereIn('priceable_id', $itemIds)
                ->delete();
            DB::table('order_items')->whereIn('id', $itemIds)->delete();
            DB::table('order_payments')->whereIn('order_id', $manifest['order_ids'])->delete();
            DB::table('order_checks')->whereIn('order_id', $manifest['order_ids'])->delete();
            DB::table('orders')->whereIn('id', $manifest['order_ids'])->delete();

            DB::table('anamnesis')->whereIn('sales_id', $manifest['saleslead_ids'])->delete();
            DB::table('saleslead_persons')->whereIn('saleslead_id', $manifest['saleslead_ids'])->delete();
            DB::table('salesleads')->whereIn('id', $manifest['saleslead_ids'])->delete();

            DB::table('anamnesis')->whereIn('lead_id', $manifest['lead_ids'])->delete();
            DB::table('lead_persons')->whereIn('lead_id', $manifest['lead_ids'])->delete();
            DB::table('leads')->whereIn('id', $manifest['lead_ids'])->delete();

            DB::table('persons')->whereIn('id', $manifest['person_ids'])->delete();
            DB::table('organizations')->whereIn('id', $manifest['organization_ids'])->delete();
            DB::table('addresses')->whereIn('id', $manifest['address_ids'])->delete();
        });

        File::delete($path);
    }

    /**
     * Adds to the manifest of earlier runs, so a rerun never loses what revert() has to remove.
     */
    private function rememberInserted(): void
    {
        $path = storage_path(self::MANIFEST_FILE);
        $manifest = File::exists($path)
            ? array_merge_recursive(json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR), $this->inserted)
            : $this->inserted;

        File::put($path, json_encode($manifest));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function restoreOrder(array $record): void
    {
        $exists = DB::table('orders')
            ->where('external_id', $record['external_id'])
            ->orWhere('order_number', $record['order_number'])
            ->exists();

        if ($exists) {
            return;
        }

        $personIds = [];

        foreach ($record['persons'] as $person) {
            $personIds[$person['external_id']] = $this->findOrCreatePerson($person);
        }

        $lead = $this->findOrCreateLead($record['lead'], $personIds);

        // Same as the original import: historical orders must not fire observers.
        Model::withoutEvents(fn () => $this->createOrder($record, $lead, $personIds));
    }

    /**
     * @param  array{external_id: string, row: array<string, mixed>}  $data
     */
    private function findOrCreatePerson(array $data): int
    {
        $person = Person::withTrashed()->where('external_id', $data['external_id'])->first();

        if (! $person && ! empty($data['row']['date_of_birth'])) {
            $person = Person::query()
                ->whereDate('date_of_birth', $data['row']['date_of_birth'])
                ->where('last_name', $data['row']['last_name'])
                ->orderBy('id')
                ->first();
        }

        if ($person) {
            return $person->id;
        }

        $person = $this->saveQuietly(new Person, ['external_id' => $data['external_id'], ...$data['row']]);

        return $this->inserted['person_ids'][] = $person->id;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $personIds  keyed by SugarCRM contact id
     */
    private function findOrCreateLead(array $data, array $personIds): Lead
    {
        $lead = Lead::withTrashed()->where('external_id', $data['external_id'])->first();

        if ($lead) {
            return $lead;
        }

        $department = Department::where('name', $data['department'])->firstOrFail();
        $pipelineId = $data['department'] === Departments::HERNIA->value
            ? PipelineDefaultKeys::PIPELINE_HERNIA_ID->value
            : PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value;

        $lead = $this->saveQuietly(new Lead, [
            'external_id'            => $data['external_id'],
            ...$data['row'],
            'department_id'          => $department->id,
            'lead_pipeline_id'       => $pipelineId,
            'lead_pipeline_stage_id' => Stage::where('lead_pipeline_id', $pipelineId)->where('code', 'like', '%won%')->firstOrFail()->id,
            'user_id'                => $this->userId($data['user_external_id']),
        ]);

        $this->inserted['lead_ids'][] = $lead->id;

        if ($data['address']) {
            $address = Address::create($data['address']);
            DB::table('leads')->where('id', $lead->id)->update(['address_id' => $address->id]);
            $this->inserted['address_ids'][] = $address->id;
        }

        // With events: linking a person creates the anamnesis and derives the customer type.
        $lead->attachPersons(array_values(array_intersect_key($personIds, array_flip($data['person_external_ids']))));

        return $lead->refresh();
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, int>  $personIds
     */
    private function createOrder(array $record, Lead $lead, array $personIds): void
    {
        $organizationId = $record['organization'] ? $this->organizationId($record['organization']) : null;

        $sales = $record['sales'];
        $salesLead = $this->salesLeadRepository->createFromLeadForOrderImport($lead, [
            'name'              => $sales['name'],
            'pipeline_stage_id' => PipelineStage::from($sales['pipeline_stage'])->id(),
            'closed_at'         => $sales['closed_at'],
        ], ['created_at' => $sales['created_at'], 'updated_at' => $sales['updated_at']]);

        $this->inserted['saleslead_ids'][] = $salesLead->id;

        $extraPersonIds = array_diff(array_values($personIds), $salesLead->persons()->pluck('persons.id')->all());

        if ($extraPersonIds) {
            $salesLead->attachPersons(array_values($extraPersonIds));
        }

        $data = $record['order'];
        $order = $this->saveQuietly(new Order, [
            'external_id'  => $record['external_id'],
            'order_number' => $record['order_number'],
            ...$data['row'],
            'pipeline_stage_id'          => PipelineStage::from($data['pipeline_stage'])->id(),
            'sales_lead_id'              => $salesLead->id,
            'organization_id'            => $organizationId,
            'is_business'                => $organizationId !== null,
            'user_id'                    => $this->userId($data['user_external_id']),
            'clinic_coordinator_user_id' => $this->userId($data['coordinator_external_id']),
        ]);

        $this->inserted['order_ids'][] = $order->id;

        foreach ($record['items'] as $item) {
            $this->createItem($order, $item, $personIds);
        }

        foreach ($record['payments'] as $payment) {
            OrderPayment::create(['order_id' => $order->id, ...$payment]);
        }

        $this->orderCheckService->updatePartnerProductChecks($order);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $personIds
     */
    private function createItem(Order $order, array $data, array $personIds): void
    {
        $item = OrderItem::create([
            'order_id'    => $order->id,
            'external_id' => $data['external_id'],
            'person_id'   => $personIds[$data['person_external_id']],
            'product_id'  => $this->productId($data),
            'quantity'    => 1,
            ...$data['row'],
        ]);

        $item->invoicePurchasePrice()->updateOrCreate(
            ['type' => PurchasePriceType::INVOICE],
            [...$data['invoice'], 'type' => PurchasePriceType::INVOICE, 'force_received' => true]
        );
        $item->purchasePrice()->updateOrCreate(
            ['type' => PurchasePriceType::MAIN],
            [...$data['main'], 'type' => PurchasePriceType::MAIN]
        );

        if ($booking = $data['booking']) {
            $resourceId = DB::table('resources')->where('external_id', $booking['resource_external_id'])->value('id')
                ?? throw new RuntimeException('Resource ontbreekt: '.$booking['resource_external_id']);

            ResourceOrderItem::create([
                'resource_id'  => $resourceId,
                'orderitem_id' => $item->id,
                'from'         => $booking['from'],
                'to'           => $booking['to'],
            ]);
        }
    }

    /**
     * @param  array{partner_product_external_id: string|null, product_name: string}  $data
     */
    private function productId(array $data): int
    {
        $productId = $data['partner_product_external_id']
            ? DB::table('partner_products')->where('external_id', $data['partner_product_external_id'])->value('product_id')
            : null;

        if ($productId) {
            return $productId;
        }

        $matches = DB::table('products')->where('name', $data['product_name'])->pluck('id');

        if ($matches->count() !== 1) {
            throw new RuntimeException("Product niet eenduidig gevonden: {$data['product_name']} ({$matches->count()} treffers).");
        }

        return $matches->first();
    }

    /**
     * @param  array{name: string, address: array<string, mixed>|null}  $data
     */
    private function organizationId(array $data): int
    {
        $organization = Organization::firstOrCreate(['name' => $data['name']]);

        if ($organization->wasRecentlyCreated) {
            $this->inserted['organization_ids'][] = $organization->id;
        }

        if ($data['address'] && ! $organization->address_id) {
            $address = $this->addressRepository->upsertForEntity($organization, $data['address']);

            if ($address) {
                $this->inserted['address_ids'][] = $address->id;
            }
        }

        return $organization->id;
    }

    private function userId(?string $externalId): ?int
    {
        return $externalId ? DB::table('users')->where('external_id', $externalId)->value('id') : null;
    }

    /**
     * Keeps the historical created_at and updated_at from the data file.
     *
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    private function saveQuietly(Model $model, array $attributes): Model
    {
        $model->forceFill($attributes);
        $model->timestamps = false;
        $model->saveQuietly();
        $model->timestamps = true;

        return $model;
    }
}
