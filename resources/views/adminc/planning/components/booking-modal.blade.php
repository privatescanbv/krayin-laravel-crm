<!-- Book modal -->
<x-admin::modal ref="bookModal">
    <x-slot:header>
        Inboeken
    </x-slot:header>
    <x-slot:content>
        <div class="space-y-6" style="pointer-events: auto; z-index: 1000; position: relative;">
            <!-- Orderitem -->
            <div>
                <x-adminc::components.field
                    type="select"
                    name="order_item_id"
                    label="Orderitem (Nog niet ingeplande items staan bovenaan)"
                    v-model="form.order_item_id"
                    class="w-full"
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
                </x-adminc::components.field>

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

            <!-- Resource -->
            <x-adminc::components.field
                type="select"
                name="resource_id"
                label="Resource"
                v-model.number="form.resource_id"
                class="w-full"
            >
                <option v-for="r in resources" :key="r.id" :value="r.id">@{{ r.name }} (@{{
                    r.clinic }})
                </option>
            </x-adminc::components.field>

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
