@props(['organisation'])

    <!-- Person Block -->
<div class="rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900 overflow-hidden">
    <!-- Header -->
    <div
        class="bg-blue-100 dark:bg-blue-900/30  border-blue-500' px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <h4 class="text-base font-semibold text-gray-900 dark:text-white">Organisatie</h4>
        </div>
    </div>

    <!-- Content -->
    <div class="p-4 space-y-4">
        <!-- Action Button and Status -->
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <span>
                    {{ $organisation ? $organisation->name : 'Onbekend' }}
                </span>
            </div>
        </div>

        <!-- Input Fields -->
        <div class="space-y-4">
            <x-adminc::address.summarize_as_fields :address="$organisation->address"/>
        </div>

    </div>
</div>
