<x-admin::layouts>
    <x-slot:title>Inkoop factuur uploaden</x-slot>

    <x-admin::form :action="route('admin.inkoop.clinics.upload.store', $clinic->id)" method="POST" enctype="multipart/form-data">
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.clinics.view" :entity="$clinic" />

                    <div class="text-xl font-bold dark:text-gray-300">
                        Inkoop factuur uploaden
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <a href="{{ route('admin.clinics.view', $clinic->id) }}" class="secondary-button">Terug</a>
                    <button type="submit" class="primary-button">Uploaden</button>
                </div>
            </div>

            @include('adminc.components.validation-errors')

            <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-gray-800 dark:text-gray-200" for="parser">Parser</label>
                        <select id="parser" name="parser" class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300" required>
                            @foreach ($parsers as $parser)
                                <option value="{{ $parser->value }}" @selected(old('parser') === $parser->value)>
                                    {{ $parser->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-gray-800 dark:text-gray-200" for="file">PDF factuur</label>
                        <input id="file" name="file" type="file" accept="application/pdf" class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300" required>
                    </div>
                </div>
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>
