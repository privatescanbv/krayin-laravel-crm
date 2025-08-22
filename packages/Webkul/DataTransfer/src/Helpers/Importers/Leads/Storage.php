<?php

namespace Webkul\DataTransfer\Helpers\Importers\Leads;

use Webkul\Lead\Repositories\LeadRepository;

class Storage
{
    /**
     * Items contains identifier as key and product information as value.
     */
    protected array $items = [];

    /**
     * Columns which will be selected from database.
     */
    protected array $selectColumns = ['id'];

    /**
     * Create a new helper instance.
     *
     * @return void
     */
    public function __construct(protected LeadRepository $leadRepository) {}

    /**
     * Initialize storage.
     */
    public function init(): void
    {
        $this->items = [];

        $this->load();
    }

    /**
     * Load the leads.
     */
    public function load(array $ids = []): void
    {
        if (empty($ids)) {
            $leads = $this->leadRepository->all($this->selectColumns);
        } else {
            $leads = $this->leadRepository->findWhereIn('id', $ids, $this->selectColumns);
        }

        foreach ($leads as $lead) {
            $this->set($lead->id, [
                'id'    => $lead->id,
            ]);
        }
    }

    /**
     * Get Ids and Unique Id.
     */
    public function set(string $id, array $data): self
    {
        $this->items[$id] = $data;

        return $this;
    }

    /**
     * Check if unique id exists.
     */
    public function has(string $id): bool
    {
        return isset($this->items[$id]);
    }

    /**
     * Get unique id information.
     */
    public function get(string $id): ?array
    {
        if (! $this->has($id)) {
            return null;
        }

        return $this->items[$id];
    }

    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Is storage is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }
}
