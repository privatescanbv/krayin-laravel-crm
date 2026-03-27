<!-- Book modal -->
<x-admin::modal ref="bookModal">
    <x-slot:header>
        Inboeken
    </x-slot:header>
    <x-slot:content>
        <div class="space-y-6" style="pointer-events: auto; z-index: 1000; position: relative;">
            <!-- Orderitem: native select (zelfde reden als resource — v-field/vee-validate blokkeert wisselen) -->
            <div>
                <label
                    for="booking-modal-order_item_id"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1.5"
                >
                    Orderitem (Nog niet ingeplande items staan bovenaan)
                </label>
                <select
                    id="booking-modal-order_item_id"
                    v-model="form.order_item_id"
                    name="order_item_id"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:hover:border-gray-400"
                >
                    <option value="">Selecteer orderitem</option>
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
                    <optgroup v-if="notPlanableItems.length > 0"
                              label="━━━ Niet planbaar ━━━">
                        <option
                            v-for="item in notPlanableItems"
                            :key="item.id"
                            :value="item.id"
                            disabled
                        >
                            ⚠ @{{ item.product_name }} (Aantal: @{{ item.quantity }}) - Niet planbaar
                        </option>
                    </optgroup>
                </select>

                <p v-if="selectedOrderItem && selectedOrderItem.required_resource_type"
                   class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Vereist resource-type: @{{ selectedOrderItem.required_resource_type }}
                </p>
                <p v-else-if="selectedOrderItem"
                   class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Vereist resource-type: niet bepaald
                </p>

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

            <!-- Resource: native select (geen v-field/vee-validate) zodat dynamische opties betrouwbaar werken -->
            <div>
                <label
                    for="booking-modal-resource_id"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1.5"
                >
                    Resource
                </label>
                <select
                    id="booking-modal-resource_id"
                    v-model.number="form.resource_id"
                    name="resource_id"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:hover:border-gray-400"
                >
                    <option v-if="bookingResourceOptions.length === 0" disabled value="">
                        — Geen resources (laden of filter aanpassen) —
                    </option>
                    <option v-else disabled value="">Selecteer resource</option>
                    <option v-for="r in bookingResourceOptions" :key="r.id" :value="r.id">
                        @{{ r.name }} (@{{ r.clinic }})
                    </option>
                </select>
            </div>

            <!-- Outside availability warning -->
            <div v-if="isOutsideAvailability && isOutsideAvailabilityAllowedForResource"
                 class="mt-2 p-2 bg-yellow-50 border border-yellow-300 text-yellow-800 rounded text-sm">
                &#9888; Het geselecteerde tijdstip valt buiten de beschikbaarheid van de resource.
                Dit is toegestaan voor deze resource.
            </div>
            <div v-if="isOutsideAvailability && !isOutsideAvailabilityAllowedForResource"
                 class="mt-2 p-2 bg-yellow-50 border border-yellow-300 text-yellow-800 rounded text-sm">
                &#9888; Het geselecteerde tijdstip valt buiten de beschikbaarheid van de resource.
                Dit is NIET toegestaan voor deze resource.
            </div>

            <!-- From -->
            <x-adminc::components.field
                type="datetime-local"
                name="from"
                label="Van"
                v-model="form.from"
                @change="calculateEndTime"
            />

            <!-- To -->
            <div>
                <x-adminc::components.field
                    type="datetime-local"
                    name="to"
                    label="Tot"
                    v-model="form.to"
                />
                <span v-if="selectedOrderItem && selectedOrderItem.duration" class="text-xs text-gray-500 mt-1 block">
                    (@{{ selectedOrderItem.duration }} min)
                </span>
            </div>

            <!-- Replace existing -->
            <x-adminc::components.field
                type="checkbox"
                name="replace_existing"
                label="Vervang bestaande afspraak (verwijdert eerdere boekingen voor deze orderitem)"
                v-model="form.replace_existing"
            />

        </div>
    </x-slot:content>
    <x-slot:footer>
        <div class="flex justify-end gap-3">
            <button
                class="secondary-button"
                @click="$refs.bookModal.toggle()">Annuleren
            </button>
            <button
                class="primary-button"
                @click="submitBooking">Opslaan
            </button>
        </div>
    </x-slot:footer>
</x-admin::modal>
