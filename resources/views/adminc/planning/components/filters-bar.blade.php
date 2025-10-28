<!-- Filters and View Controls -->
<div class="filters-bar rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/60 p-3 md:p-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <!-- Left: Filters -->
        <div class="flex flex-wrap items-start gap-3">
            <div class="w-full md:w-56">
                <v-multiselect-filter
                    v-model="filters.resource_type_ids"
                    :options="resourceTypeOptions"
                    label="Resource type"
                    placeholder="Alle types"
                ></v-multiselect-filter>
            </div>
            <div class="w-full md:w-56">
                <v-multiselect-filter
                    v-model="filters.clinic_ids"
                    :options="clinicOptions"
                    label="Kliniek"
                    placeholder="Alle klinieken"
                ></v-multiselect-filter>
            </div>
            <div class="w-full md:w-56">
                <v-multiselect-filter
                    v-model="filters.resource_ids"
                    :options="filteredResourceOptions"
                    label="Resource"
                    placeholder="Alle resources"
                ></v-multiselect-filter>
            </div>
            <div v-if="orderItemOptions && orderItemOptions.length > 0" class="w-full md:w-56">
                <v-multiselect-filter
                    v-model="filters.order_item_ids"
                    :options="orderItemOptions"
                    label="Orderitem"
                    placeholder="Alle orderitems"
                ></v-multiselect-filter>
            </div>
            <div class="w-full md:w-56 flex items-end">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        v-model="filters.show_available_only"
                        class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                    />
                    <span class="text-sm text-gray-700 dark:text-gray-300">Toon alleen beschikbaar</span>
                </label>
            </div>
        </div>

        <!-- Right: View controls -->
        <div class="flex flex-col gap-3">
            <!-- View toggle -->
            <div class="flex items-center justify-end gap-3">
                <div class="flex border border-gray-300 dark:border-gray-700 rounded-md overflow-hidden">
                    <button
                        @click="setViewType('week')"
                        :class="['px-3 py-1 text-sm', viewType === 'week' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800']"
                    >
                        Week
                    </button>
                    <button
                        @click="setViewType('month')"
                        :class="['px-3 py-1 text-sm', viewType === 'month' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800']"
                    >
                        Maand
                    </button>
                </div>
            </div>

            <!-- Calendar controls -->
            <div class="flex items-center justify-end gap-2">
                <button class="secondary-button" @click="prevPeriod">Vorige</button>
                <div class="text-sm font-medium text-gray-800 dark:text-gray-200">@{{ periodLabel }}</div>
                <button class="secondary-button" @click="nextPeriod">Volgende</button>
                <button class="primary-button" @click="loadAvailability">Zoeken</button>
            </div>
        </div>
    </div>
</div>
