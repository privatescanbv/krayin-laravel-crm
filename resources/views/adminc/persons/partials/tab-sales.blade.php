@php
    /** @var \Webkul\Contact\Models\Person $person */
@endphp

<div class="flex w-full flex-col gap-4">
    <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-3 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
        <div class="flex flex-col gap-1">
            <div class="text-xl font-bold text-gray-900 dark:text-gray-100">
                Sales
            </div>

            <div class="text-sm text-gray-500 dark:text-gray-400">
                Actieve sales staan bovenaan. Afgesloten sales (gewonnen/verloren) staan onder "Afgesloten".
            </div>
        </div>
    </div>

    <div class="rounded-lg border bg-white p-2 dark:border-gray-800 dark:bg-gray-900">
        <div class="px-2 pb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
            Actief
        </div>

        <x-admin::datagrid
            :src="route('admin.sales-leads.get', ['view_type' => 'table', 'person_id' => $person->id, 'status_bucket' => 'active'])"
            ref="salesActiveGrid"
        />
    </div>

    <details class="rounded-lg border bg-white p-2 dark:border-gray-800 dark:bg-gray-900">
        <summary class="cursor-pointer select-none px-2 pb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
            Afgesloten
        </summary>

        <div class="opacity-70">
            <x-admin::datagrid
                :src="route('admin.sales-leads.get', ['view_type' => 'table', 'person_id' => $person->id, 'status_bucket' => 'closed'])"
                ref="salesClosedGrid"
            />
        </div>
    </details>
</div>
