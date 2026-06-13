{{--
    Shared inline "Nieuwe organisatie aanmaken" form.
    Used by order-org-section and leads/common/organization-vue.
    Requires Vue data: newOrgName, newOrgPostal, newOrgHouseNumber, newOrgSuffix,
                       newOrgStreet, newOrgCity, newOrgCountry, isLookingUpAddress, showOrgForm, orgConfirmed.
    Requires Vue methods: cancelOrgForm(), confirmOrgForm(), editOrgForm(), lookupAddress().
--}}
<div v-if="showOrgForm" class="mt-2 bg-gray-50 border border-gray-200 rounded-lg p-4 dark:bg-gray-800 dark:border-gray-700">
    <p class="text-sm font-semibold text-gray-800 dark:text-white mb-3">Nieuwe organisatie aanmaken</p>
    <div class="grid grid-cols-1 gap-3">

        {{-- Name --}}
        <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Naam <span class="text-red-500">*</span></label>
            <input type="text" v-model="newOrgName" placeholder="Organisatienaam"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
        </div>

        {{-- Postcode + Huisnummer + Lookup --}}
        <div class="flex flex-wrap gap-3 items-end">
            <div class="flex flex-col gap-1 flex-1 min-w-[120px]">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Postcode <span class="text-red-500">*</span></label>
                <input type="text" v-model="newOrgPostal" placeholder="1234 AB"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
            </div>
            <div class="flex flex-col gap-1 flex-1 min-w-[100px]">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Huisnummer <span class="text-red-500">*</span></label>
                <input type="text" v-model="newOrgHouseNumber" placeholder="123"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
            </div>
            <div class="flex-shrink-0">
                <button type="button" @click="lookupAddress" :disabled="isLookingUpAddress"
                        class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60 disabled:cursor-not-allowed">
                    <span v-if="isLookingUpAddress">Zoeken...</span>
                    <span v-else>Adres opzoeken</span>
                </button>
            </div>
        </div>

        {{-- Toevoeging + Straat --}}
        <div class="grid grid-cols-2 gap-3">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Toevoeging</label>
                <input type="text" v-model="newOrgSuffix" placeholder="A"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Straat</label>
                <input type="text" v-model="newOrgStreet" placeholder="Straatnaam"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
            </div>
        </div>

        {{-- Stad + Land --}}
        <div class="grid grid-cols-2 gap-3">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Stad</label>
                <input type="text" v-model="newOrgCity" placeholder="Amsterdam"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Land</label>
                <input type="text" v-model="newOrgCountry" placeholder="Nederland"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
            </div>
        </div>

    </div>
    <div class="flex justify-end gap-2 mt-4">
        <button type="button" @click="cancelOrgForm"
                class="px-4 py-2 bg-gray-500 text-white text-sm font-medium rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500">
            Annuleren
        </button>
        <button type="button" @click="confirmOrgForm"
                class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
            Bevestigen
        </button>
    </div>
</div>
