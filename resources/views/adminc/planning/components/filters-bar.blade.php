<!-- Filters and View Controls -->
<div class="filters-bar rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/60 p-3 md:p-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <!-- Left: Filters -->
        <div class="flex flex-col h-full">
            <!-- Filters -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

            <div class="w-full md:flex-1 md:min-w-[14rem]">
                    <v-multiselect-filter
                        v-model="filters.resource_type_ids"
                        :options="resourceTypeOptions"
                        label="Resource type"
                        placeholder="Alle types"
                    />
                </div>

                <div class="w-full md:flex-1 md:min-w-[14rem]">
                    <v-multiselect-filter
                        v-model="filters.clinic_ids"
                        :options="clinicOptions"
                        label="Kliniek"
                        placeholder="Alle klinieken"
                    />
                </div>

                <div class="w-full md:flex-1 md:min-w-[14rem]">
                    <v-multiselect-filter
                        v-model="filters.resource_ids"
                        :options="filteredResourceOptions"
                        label="Resource"
                        placeholder="Alle resources"
                    />
                </div>

                <div
                    v-if="orderItemOptions && orderItemOptions.length > 0"
                    class="w-full md:flex-1 md:min-w-[14rem]"
                >
                    <v-multiselect-filter
                        v-model="filters.order_item_ids"
                        :options="orderItemOptions"
                        label="Orderitem"
                        placeholder="Alle orderitems"
                    />
                </div>
            </div>

            <!-- Checkbox onderin -->
            <div class="mt-auto pt-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        v-model="filters.show_available_only"
                        class="h-4 w-4 text-activity-note-text border-gray-300 rounded focus:ring-blue-500"
                    />
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                Toon alleen beschikbaar
            </span>
                </label>
            </div>
        </div>


        <!-- Right: View controls -->
        <div class="flex flex-col items-end gap-3 h-full">
            <!-- Rij 1: Toggle -->
            <div class="inline-flex rounded-md border border-neutral-border overflow-hidden">
                <button
                    @click="setViewType('week')"
                    :class="[
                            viewType === 'week' ? 'primary-button' : 'secondary-button',
                            'rounded-none border-0 px-3 py-1 text-sm'
                        ]"
                >
                    Week
                </button>

                <button
                    @click="setViewType('month')"
                    :class="[
                        viewType === 'month' ? 'primary-button' : 'secondary-button',
                        'rounded-none border-0 px-3 py-1 text-sm'
                    ]"
                >
                    Maand
                </button>
            </div>

            <!-- Rij 2: Kalender navigatie -->
            <div class="flex items-center gap-3">
                <button class="secondary-button" @click="prevPeriod">Vorige</button>
                <div class="text-sm font-medium text-gray-800 dark:text-gray-200">@{{ periodLabel }}</div>
                <button class="secondary-button" @click="nextPeriod">Volgende</button>
            </div>

            <!-- Rij 3: Zoeken onderaan -->
            <div class="mt-auto">
                <button class="primary-button" @click="loadAvailability">Zoeken</button>
            </div>
        </div>
    </div>
</div>
