<x-admin::layouts>
    <x-slot:title>
        Edit sales
    </x-slot>

    <!-- Header -->
    <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
        <div class="flex flex-col gap-2">
            <!-- Breadcrumb's -->
            <x-admin::breadcrumbs name="sales-leads.edit" :entity="$salesLead" />

            <div class="text-xl font-bold dark:text-white">
                Edit sales
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="mt-3.5">
        <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <form action="{{ route('admin.sales-leads.update', $salesLead->id) }}" method="POST">
                @csrf
                @method('PUT')

                @include('adminc.components.validation-errors')
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <!-- Name -->
                    <div>
                        <input
                            type="text"
                            name="name"
                            id="name"
                            value="{{ old('name', $salesLead->name) }}"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            required
                        >
                        @error('name')
                            <p class="mt-1 text-sm text-status-expired-text">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Description -->
                    <div class="md:col-span-2">
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Description
                        </label>
                        <textarea
                            name="description"
                            id="description"
                            rows="3"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        >{{ old('description', $salesLead->description) }}</textarea>
                        @error('description')
                            <p class="mt-1 text-sm text-status-expired-text">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Contact Person Selection -->
                <div class="mt-6">
                    <div class="flex flex-col gap-4" id="contact-person-selection">
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold dark:text-white">
                                Contactpersoon
                            </p>
                            <p class="text-gray-600 dark:text-gray-300">
                                Selecteer de contactpersoon voor deze sales
                            </p>
                        </div>
                        <div class="w-1/2 max-md:w-full">
                            @include('adminc.components.contact-person-selector')
                            <v-contact-person-selector
                                name="contact_person_id"
                                label="Contactpersoon"
                                placeholder="Selecteer contactpersoon..."
                                :current-value='@json($salesLead->contact_person_id)'
                                :current-label='@json($salesLead->contactPerson ? $salesLead->contactPerson->name : null)'
                                :can-add-new="true"
                            />
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Name <span class="text-red-500">*</span>
                        </label>
                        </div>
                    </div>
                </div>

                <!-- Contact Persons Section -->
                <div class="mt-6">
                    <div class="flex flex-col gap-4" id="contact-person">
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold dark:text-white">
                                Personen
                            </p>
                            <p class="text-gray-600 dark:text-gray-300">
                                Koppel een of meerdere personen aan deze sales
                            </p>
                        </div>

                        <x-adminc::components.multi-contactmatcher
                            :leads=" (object)['id' => $salesLead->id]"
                            :persons="$salesLead->persons"
                        />
                    </div>
                </div>

                <!-- Owner -->
                <div class="flex-1">
                    @php
                        $userOptions = app(Webkul\User\Repositories\UserRepository::class)
                        ->allActiveUsers();
                        $currentUserId = $salesLead->user_id;
                    @endphp
                    <x-adminc::components.field
                        type="select"
                        name="user_id"
                        value="{{ $currentUserId }}"
                        label="Toegewezen gebruiker"
                    >
                        <option value="">-- Kies gebruiker --</option>
                        @foreach ($userOptions as $user)
                            <option
                                value="{{ $user->id }}" {{ ($currentUserId == $user->id) ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </x-adminc::components.field>
                </div>

                <!-- Submit Button -->
                <div class="mt-6 flex justify-end">
                    <button
                        type="submit"
                        class="primary-button"
                    >
                        Update Sales
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-admin::layouts>
