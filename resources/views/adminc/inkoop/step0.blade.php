<x-admin::layouts>
    <x-slot:title>Inkoop stap 0</x-slot>

    <x-admin::form :action="route('admin.inkoop.update-reference-date', $invoice->id)" method="POST">
        @method('PUT')

        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <div class="text-xl font-bold dark:text-gray-300">Referentiedatum bevestigen</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $invoice->name ?? $invoice->filename }}</div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <a href="{{ route('admin.inkoop.clinics.upload', $invoice->clinic_id) }}" class="secondary-button">Terug</a>
                    <button type="submit" class="primary-button">Verder</button>
                </div>
            </div>

            @include('adminc.components.validation-errors')

            <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-gray-800 dark:text-gray-200" for="name">Naam</label>
                        <input id="name" name="name" value="{{ old('name', $invoice->name) }}" class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300">
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-gray-800 dark:text-gray-200" for="reference_date">Referentiedatum</label>
                        <input id="reference_date" name="reference_date" type="date" value="{{ old('reference_date', optional($invoice->reference_date)->format('Y-m-d')) }}" class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300">
                    </div>
                </div>
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>
