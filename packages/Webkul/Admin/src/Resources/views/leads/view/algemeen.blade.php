@php
    // General lead information view
@endphp

<div class="flex w-full flex-col gap-4 rounded-lg">
    <!-- Stages Navigation -->
    <!-- @ include ('admin::leads.view.stages') -->

    <div
        class="rounded-lg border border-neutral-border bg-neutral-muted p-6 text-sm text-gray-500 text-white dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
        notes:
        - Wat als persoon meerdere telefoonnummers of e-mailadressen heeft? zo laten en alleen defaults tonen?
    </div>

    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Algemene informatie Lead</h3>

            <div class="direction-row flex items-center gap-4">
                @if (bouncer()->hasPermission('leads.edit'))
                    <a href="{{ route('admin.leads.edit', $lead->id) }}"
                        class="secondary-button flex items-center gap-1 border hover:border-neutral-text hover:text-neutral-text"><span
                            class="icon-edit text-base"></span><span>Bewerk lead</span></></a>
                @endif

                @if (bouncer()->hasPermission('leads.delete'))
                    <v-lead-delete delete-url="{{ route('admin.leads.delete', $lead->id) }}"
                        redirect-url="{{ route('admin.leads.index') }}" lead-name="{{ $lead->name }}"></v-lead-delete>
                @endif
            </div>
        </div>
    </div>
    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between">
            <div class="direction-row flex items-center">
                 {{ $lead->description }}
            </div>
        </div>
    </div>
    <x-adminc::leads.compact-overview :lead="$lead" />

    <!-- Person Blocks Grid -->
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-2">
        @if ($lead->hasContactPerson())
            @include('adminc::persons.person', [
                'lead' => $lead,
                'person' => $lead->contactPerson,
                'isContactPerson' => true
            ])
        @endif

        <!-- Person Blocks - One for each person -->
        @foreach ($lead->persons as $person)
            @include('adminc::persons.person', ['lead' => $lead, 'person' => $person])
        @endforeach

        <!-- Insurance Block -->

        <div class="overflow-hidden rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900"><!-- Header -->
            <div
                class="flex items-center justify-between border-brand bg-activity-note-bg px-4 py-3 dark:bg-orange-900/20">
                <div class="flex items-center gap-3">
                    <h4 class="text-base font-semibold  text-activity-note-text dark:text-white">Zorgverzekering</h4>
                </div>
            </div><!-- Content -->
            <div class="space-y-4 p-4"><!-- Action Button and Status -->
                <div class="flex items-center justify-between gap-4">
                    -- status / notities --
                </div>
                <!-- Input Fields -->
                <div class="space-y-4">
                    <div class="relative mb-4 w-full"><input type="text" name="Naam verzekeraar"
                            class="peer placeholder:text-transparent" readonly="" placeholder="Naam verzekeraar"
                            value="Naam verzekeraar"><label
                            class="pointer-events-none absolute left-0 top-4 z-10 ml-2 max-w-[80%] -translate-y-6 overflow-auto text-ellipsis bg-gradient-to-t from-neutral-bg to-white px-1 text-xs duration-100 ease-linear peer-placeholder-shown:-translate-y-1 peer-placeholder-shown:bg-none peer-placeholder-shown:text-sm peer-placeholder-shown:text-gray-500">
                            Naam verzekeraar </label><!----></div>
                    <div class="relative mb-4 w-full"><input type="tel" name="Klantnummer"
                            class="peer placeholder:text-transparent" readonly="" placeholder="Klantnummer"
                            value="Klantnummer"><label
                            class="pointer-events-none absolute left-0 top-4 z-10 ml-2 max-w-[80%] -translate-y-6 overflow-auto text-ellipsis bg-gradient-to-t from-neutral-bg to-white px-1 text-xs duration-100 ease-linear peer-placeholder-shown:-translate-y-1 peer-placeholder-shown:bg-none peer-placeholder-shown:text-sm peer-placeholder-shown:text-gray-500">
                            Klantnummer </label><!----></div>
                    <div class="relative mb-4 w-full"><input type="email" name="Polisnummer"
                            class="peer placeholder:text-transparent" readonly="" placeholder="Polisnummer"
                            value="Polisnummer"><label
                            class="pointer-events-none absolute left-0 top-4 z-10 ml-2 max-w-[80%] -translate-y-6 overflow-auto text-ellipsis bg-gradient-to-t from-neutral-bg to-white px-1 text-xs duration-100 ease-linear peer-placeholder-shown:-translate-y-1 peer-placeholder-shown:bg-none peer-placeholder-shown:text-sm peer-placeholder-shown:text-gray-500">
                            E-Polisnummer </label><!----></div>
                    <div class="relative mb-4 w-full"><input type="email" name="Eigen Risico"
                            class="peer placeholder:text-transparent" readonly="" placeholder="Eigen Risico"
                            value="Eigen Risico"><label
                            class="pointer-events-none absolute left-0 top-4 z-10 ml-2 max-w-[80%] -translate-y-6 overflow-auto text-ellipsis bg-gradient-to-t from-neutral-bg to-white px-1 text-xs duration-100 ease-linear peer-placeholder-shown:-translate-y-1 peer-placeholder-shown:bg-none peer-placeholder-shown:text-sm peer-placeholder-shown:text-gray-500">
                            Eigen Risico </label><!----></div>
                </div>
                <div class="mb-4 rounded-lg border border-status-active-border bg-status-active-bg p-3 ">
                    <div class="flex items-center gap-2"><span
                            class="icon-success text-lg text-status-active-text"></span><span
                            class="text-sm font-medium text-status-active-text"> <strong>Verzekering geverifieerd</strong><br/>Laatste controle: -- date --</span></div>
                </div>

            </div>
        </div>
    </div>

</div>
