<x-admin::layouts>
    <x-slot:title>
        Edit Sales Lead
    </x-slot>

    <!-- Header -->
    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
        <div class="flex flex-col gap-2">
            <!-- Breadcrumb's -->
            <x-admin::breadcrumbs name="sales-leads.edit" :entity="$salesLead" />

            <div class="text-xl font-bold dark:text-white">
                Edit Sales Lead
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="mt-3.5">
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <form action="{{ route('admin.sales-leads.update', $salesLead->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="name"
                            id="name"
                            value="{{ old('name', $salesLead->name) }}"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            required
                        >
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Lead Selection -->
                    <div>
                        <label for="lead_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Lead
                        </label>
                        <select
                            name="lead_id"
                            id="lead_id"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        >
                            <option value="">Selecteer een lead</option>
                            @if(isset($leads))
                                @foreach($leads as $id => $name)
                                    <option value="{{ $id }}" {{ old('lead_id', $salesLead->lead_id) == $id ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                        @error('lead_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
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
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Contact Persons Section -->
                <div class="mt-6">
                    <div class="flex flex-col gap-4" id="contact-person">
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold dark:text-white">
                                Contactpersonen
                            </p>
                            <p class="text-gray-600 dark:text-gray-300">
                                Koppel een of meerdere contactpersonen aan deze sales lead
                            </p>
                        </div>

                        <!-- Multi Contact Matcher -->
                        @include('admin::leads.common.multi-contactmatcher', ['lead' => $salesLead, 'persons' => $salesLead->persons])
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="mt-6 flex justify-end">
                    <button
                        type="submit"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >
                        Update Sales Lead
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-admin::layouts>
 