@props(['clinic'])


<div class="flex w-full flex-col gap-4 rounded-lg">

    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Algemene informatie Kliniek</h3>

            <div class="direction-row flex items-center gap-4">
                @if (bouncer()->hasPermission('clinics.edit'))
                    <a href="{{ route('admin.clinics.edit', $clinic->id) }}"
                        class="secondary-button flex items-center gap-1 border hover:border-neutral-text hover:text-neutral-text"><span
                            class="icon-edit text-base"></span><span>Bewerk kliniek</span></></a>
                @endif

                @if (bouncer()->hasPermission('clinics.delete'))
                    <v-lead-delete delete-url="{{ route('admin.clinics.delete', $clinic->id) }}"
                        redirect-url="{{ route('admin.clinics.index') }}" />
                @endif
            </div>
        </div>
    </div>

    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between">
            <div class="direction-row flex items-center break-all">

                <read-more
                    :text='@json($clinic->description ?? "")'
                    :lines="5"
                />
            </div>
        </div>
    </div>
    <x-adminc::clinics.partials.overview :clinic="$clinic" />

</div>
