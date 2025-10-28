<!-- Book modal -->
<x-admin::modal ref="bookModal">
    <x-slot:header>
        Inboeken
    </x-slot:header>
    <x-slot:content>
        <div class="space-y-6" style="pointer-events: auto; z-index: 1000; position: relative;">
            <!-- Order item selection -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Orderitem</label>
                <select
                    v-model.number="form.order_item_id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white cursor-pointer"
                    style="pointer-events: auto; z-index: 10; position: relative;"
                    @click.stop
                >
                    <option value="">Selecteer orderitem</option>
                    <option v-for="item in orderItems" :key="item.id" :value="item.id"
                            :disabled="!item.can_plan">
                        @{{ item.product_name }} (Aantal: @{{ item.quantity }}) @{{
                        !item.can_plan ? '- Niet planbaar' : '' }}
                    </option>
                </select>
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
                <input
                    type="datetime-local"
                    v-model="form.from"
                    @change="calculateEndTime"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white cursor-pointer"
                    style="pointer-events: auto; z-index: 10; position: relative;"
                    @click.stop
                />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Tot
                    <span v-if="selectedOrderItem && selectedOrderItem.duration" class="text-xs text-gray-500">
                        (@{{ selectedOrderItem.duration }} min)
                    </span>
                </label>
                <input
                    type="datetime-local"
                    v-model="form.to"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white cursor-pointer"
                    style="pointer-events: auto; z-index: 10; position: relative;"
                    @click.stop
                />
            </div>
            <div class="flex items-center gap-2">
                <input id="replace_existing" type="checkbox" v-model="form.replace_existing"
                       class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"/>
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
                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                @click="submitBooking">Opslaan
            </button>
        </div>
    </x-slot:footer>
</x-admin::modal>
