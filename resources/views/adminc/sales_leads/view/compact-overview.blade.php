{!! view_render_event('admin.leads.view.compact_overview.before', ['lead' => $salesLead]) !!}

<div class="flex w-full flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-800">
    <x-admin::accordion class="select-none !border-none">
        <x-slot:header class="!p-0">
            <div class="flex w-full items-center justify-between gap-4 font-semibold dark:text-white">
                <h4>Gegevens</h4>

                @if (bouncer()->hasPermission('leads.edit'))
                    <a
                        href="{{ route('admin.sales-leads.edit', $salesLead->id) }}"
                        class="icon-edit rounded-md p-1.5 text-2xl transition-all hover:bg-neutral-bg dark:hover:bg-gray-950"
                    ></a>
                @endif
            </div>
        </x-slot>

        <x-slot:content class="mt-4 !px-0 !pb-0">
            {!! view_render_event('admin.leads.view.attributes.form_controls.before', ['lead' => $salesLead]) !!}

            <div class="flex flex-col text-sm">

                <!-- Description -->
                <div class="mb-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Omschrijving</div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ $salesLead->description ?? '-' }}
                    </div>
                </div>

                <!-- Sales Owner -->
                @if ($salesLead->user)
                <div class="mb-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Toegewezen aan</div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ $salesLead->user->name }}
                    </div>
                </div>
                @endif

                <!-- Lost Reason -->
                @if (!empty($salesLead->lost_reason))
                <div class="mb-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Verliesreden</div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ $salesLead->lostReasonLabel }}
                    </div>
                </div>
                @endif

                 <!-- Lead Organization (for billing) -->
                 @if ($salesLead->organization)
                 <div class="mb-4">
                     <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Organisatie (facturatie)</div>
                     <div>
                         <a
                             href="{{ route('admin.contacts.organizations.view', $salesLead->organization->id) }}"
                             target="_blank"
                             class="text-sm font-medium text-brandColor hover:underline"
                         >
                             {{ $salesLead->organization->name }}
                             <span class="icon-external-link text-xs ml-1"></span>
                         </a>
                     </div>
                 </div>
                 @endif

                <!-- Address -->
                <div class="mb-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Adres</div>
                    <div class="text-sm text-gray-900 dark:text-gray-100">
                        @if ($salesLead->address && $salesLead->address->full_address)
                            {{ $salesLead->address->full_address }}
                            <a
                                href="https://maps.google.com/?q={{ urlencode($salesLead->address->full_address) }}"
                                target="_blank"
                                class="ml-2 text-activity-note-text hover:text-activity-task-text dark:text-blue-400 dark:hover:text-blue-300"
                                title="Bekijk op Google Maps"
                            >

                            </a>
                        @else
                            <span class="text-gray-500 dark:text-gray-400 italic">-</span>
                        @endif
                    </div>
                </div>

                <!-- Lead Specific Fields -->
                <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-3">Lead informatie</div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- MRI Status -->
                        <div class="mb-3">
                            <div class="text-xs text-gray-400 dark:text-gray-500 mb-1">MRI status</div>
                            <div class="text-sm text-gray-900 dark:text-gray-100">
                                {{ $salesLead->mriStatusLabel ?? 'Onbekend' }}
                            </div>
                        </div>

                        <!-- Diagnoseformulier aanwezig -->
                        <div class="mb-3">
                            <div class="text-xs text-gray-400 dark:text-gray-500 mb-1">Diagnoseformulier</div>
                            <div class="text-sm text-gray-900 dark:text-gray-100">
                                @if ($salesLead->has_diagnosis_form)
                                    <span class="inline-flex items-center gap-1 text-green-700">
                                        <span class="icon-attachment text-xs"></span>
                                        Aanwezig
                                    </span>
                                @else
                                    <span class="text-gray-500">Niet aanwezig</span>
                                @endif
                            </div>
                        </div>

                        <!-- Lead Source -->
                        <div class="mb-3">
                            <div class="text-xs text-gray-400 dark:text-gray-500 mb-1">Bron</div>
                            <div class="text-sm text-gray-900 dark:text-gray-100">
                                {{ $salesLead->source->name ?? 'Onbekend' }}
                            </div>
                        </div>

                        <!-- Lead Type -->
                        <div class="mb-3">
                            <div class="text-xs text-gray-400 dark:text-gray-500 mb-1">Type</div>
                            <div class="text-sm text-gray-900 dark:text-gray-100">
                                {{ $salesLead->type->name ?? 'Onbekend' }}
                            </div>
                        </div>

                        <!-- Lead Channel -->
                        <div class="mb-3">
                            <div class="text-xs text-gray-400 dark:text-gray-500 mb-1">Kanaal</div>
                            <div class="text-sm text-gray-900 dark:text-gray-100">
                                {{ $salesLead->channel->name ?? 'Onbekend' }}
                            </div>
                        </div>

                        <!-- Department -->
                        <div class="mb-3">
                            <div class="text-xs text-gray-400 dark:text-gray-500 mb-1">Afdeling</div>
                            <div class="text-sm text-gray-900 dark:text-gray-100">
                                {{ $salesLead->department->name ?? 'Onbekend' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Suite CRM link -->
            @if (!empty($salesLead->sugar_link))
                <div class="mb-4 pt-[10px]">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Sugar Link</div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        <a href="{{ $salesLead->sugar_link }}" target="_blank">{{ $salesLead->external_id }}</a>
                    </div>
                </div>
            @endif

            {!! view_render_event('admin.leads.view.attributes.form_controls.after', ['lead' => $salesLead]) !!}
        </x-slot>
    </x-admin::accordion>
</div>

{!! view_render_event('admin.leads.view.compact_overview.after', ['lead' => $salesLead]) !!}
