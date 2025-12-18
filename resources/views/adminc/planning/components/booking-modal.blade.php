<!-- Book modal -->
<x-admin::modal ref="bookModal">
    <x-slot:header>
        Inboeken
    </x-slot:header>
    <x-slot:content>
        <div class="space-y-6" style="pointer-events: auto; z-index: 1000; position: relative;">
            <!-- Order item selection -->
            <div class="relative">
                <input
                    type="datetime-local"
                    v-model="form.from"
                    @change="calculateEndTime"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white cursor-pointer"
                    style="pointer-events: auto; z-index: 10; position: relative;"
                    @click.stop
                />
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Orderitem
                    <span class="text-xs text-gray-500 font-normal ml-2">
                        (Nog niet ingeplande items staan bovenaan)
                    </span>
                </label>
                <select
                    v-model.number="form.order_item_id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white cursor-pointer"
                    style="pointer-events: auto; z-index: 10; position: relative;"
                    @click.stop
                >
                    <option value="">Selecteer orderitem</option>
                    <!-- Unplanned items first -->
                    <optgroup v-if="unplannedItems.length > 0"
                              label="━━━ Nog niet ingepland (voorrang) ━━━">
                        <option
                            v-for="item in unplannedItems"
                            :key="item.id"
                            :value="item.id"
                        >
                            ✓ @{{ item.product_name }} (Aantal: @{{ item.quantity }})
                        </option>
                    </optgroup>
                    <!-- Planned items -->
                    <optgroup v-if="plannedItems.length > 0"
                              label="━━━ Al ingepland (aanpassen) ━━━">
                        <option
                            v-for="item in plannedItems"
                            :key="item.id"
                            :value="item.id"
                        >
                            📅 @{{ item.product_name }} (Aantal: @{{ item.quantity }}) - @{{ item.bookings.length }}x ingepland
                        </option>
                    </optgroup>
                    <!-- Not planable items -->
                    <optgroup v-if="notPlanableItems.length > 0"
                              label="━━━ Niet planbaar ━━━">
                        <option
                            v-for="item in notPlanableItems"
                            :key="item.id"
                            :value="item.id"
                            :disabled="true"
                        >
                            ⚠ @{{ item.product_name }} (Aantal: @{{ item.quantity }}) - Niet planbaar
                        </option>
                    </optgroup>
                </select>
                <!-- Show booking details for selected item -->
                <div v-if="selectedOrderItem && selectedOrderItem.bookings && selectedOrderItem.bookings.length > 0"
                     class="mt-3 p-3 bg-activity-note-bg dark:bg-blue-900/20 border border-activity-note-border dark:border-blue-800 rounded-md">
                    <div class="text-sm font-medium text-activity-task-text dark:text-blue-200 mb-2">
                        Huidige planning voor dit orderitem:
                    </div>
                    <div v-for="(booking, index) in selectedOrderItem.bookings" :key="booking.id"
                         class="text-xs text-blue-700 dark:text-blue-300 mb-1">
                        <span class="font-medium">@{{ booking.resource_name }}</span> -
                        @{{ formatBookingDate(booking.from) }} tot @{{ formatBookingTime(booking.to) }}
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Resource</label>
                <select
                    v-model.number="form.resource_id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white cursor-pointer"
                    style="pointer-events: auto; z-index: 10; position: relative;"
                    @click.stop
                >
                    <option v-for="r in resources" :key="r.id" :value="r.id">@{{ r.name }} (@{{
                        r.clinic }})
                    </option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Van</label>
            </div>
            <div class="relative">
                <input
                    type="datetime-local"
                    v-model="form.to"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white cursor-pointer"
                    style="pointer-events: auto; z-index: 10; position: relative;"
                    @click.stop
                />
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Tot
                    <span v-if="selectedOrderItem && selectedOrderItem.duration" class="text-xs text-gray-500">
                        (@{{ selectedOrderItem.duration }} min)
                    </span>
                </label>
            </div>
            <div class="flex items-center gap-2">
                <input id="replace_existing" type="checkbox" v-model="form.replace_existing"
                       class="h-4 w-4 text-activity-note-text border-gray-300 rounded focus:ring-blue-500"/>
                <label for="replace_existing" class="text-sm text-gray-700 dark:text-gray-300">Vervang
                    bestaande afspraak (verwijdert eerdere boekingen voor deze
                    orderitem)</label>
            </div>
        </div>
    </x-slot:content>
    <x-slot:footer>
        <div class="flex justify-end gap-3">
            <button
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                @click="$refs.bookModal.toggle()">Annuleren
            </button>
            <button
                class="px-4 py-2 text-sm font-medium text-white text-activity-note-text border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                @click="submitBooking">Opslaan
            </button>
        </div>
    </x-slot:footer>
</x-admin::modal>
