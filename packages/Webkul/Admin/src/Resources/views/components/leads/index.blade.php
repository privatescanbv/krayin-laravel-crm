@props(['person'])

<div class="flex w-full flex-col gap-4">
    <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-3 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
        <div class="flex flex-col gap-1">
            <div class="text-xl font-bold text-gray-900 dark:text-gray-100">
                Leads
            </div>

            <div class="text-sm text-gray-500 dark:text-gray-400">
                Openstaande leads staan bovenaan. Afgeronde (gewonnen / verloren) leads staan onder “Afgerond”.
            </div>
        </div>
    </div>

    <div class="rounded-lg border bg-white p-2 dark:border-gray-800 dark:bg-gray-900">
        <div class="px-2 pb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
            Open / lopend
        </div>

        <x-admin::datagrid
            :src="route('admin.contacts.persons.leads.index', ['id' => $person->id, 'status_bucket' => 'open'])"
            ref="personLeadsOpenGrid"
        />
    </div>

    <details class="rounded-lg border bg-white p-2 dark:border-gray-800 dark:bg-gray-900">
        <summary class="cursor-pointer select-none px-2 pb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
            Afgerond (gewonnen / verloren)
        </summary>

        <div class="opacity-70">
            <x-admin::datagrid
                :src="route('admin.contacts.persons.leads.index', ['id' => $person->id, 'status_bucket' => 'completed'])"
                ref="personLeadsCompletedGrid"
            />
        </div>
    </details>
</div>
