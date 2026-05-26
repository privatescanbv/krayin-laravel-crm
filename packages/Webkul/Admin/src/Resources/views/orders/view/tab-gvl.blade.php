@props(['order', 'personsWithAnamnesis' => []])

<div class="flex w-full flex-col gap-4 rounded-lg">
    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">GVL Formulier(en)</h3>
        <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            GVL per persoon op deze order (zelfde volgorde als de orderregels). Koppel of ontkoppel formulierlinks per persoon.
        </div>
    </div>

    @if (!$order->salesLead)
        <div class="rounded-lg border bg-white p-6 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            Geen sales lead gekoppeld aan deze order.
        </div>
    @elseif(empty($personsWithAnamnesis))
        <div class="rounded-lg border bg-white p-6 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            Geen personen op de orderregels en geen personen gekoppeld aan de sales lead. Voeg orderregels met personen toe of koppel personen aan de sales lead.
        </div>
    @else
        <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="space-y-4">
                @foreach($personsWithAnamnesis as $personId => $data)
                    @php
                        $person = $data['person'];
                        $anamnesis = $data['anamnesis'] ?? null;
                        $leadId = $data['lead_id'];
                    @endphp
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                        <h4 class="mb-2 text-sm font-semibold text-gray-800 dark:text-white">
                            {{ $person->name }}
                        </h4>
                        @if($anamnesis)
                            <x-admin::gvl-form-link
                                :gvlFormLink="$anamnesis->gvl_form_link"
                                :gvlFormStatus="$anamnesis->gvl_form_status"
                                :attachUrl="route('admin.anamnesis.gvl-form.attach', $anamnesis->id)"
                                :detachUrl="route('admin.anamnesis.gvl-form.detach', $anamnesis->id)"
                                :entityId="$anamnesis->id"
                                entityType="anamnesis"
                                :personId="$person->id"
                                :personHasPortalAccount="!empty($person->keycloak_user_id)"
                            />
                        @else
                            <p class="mb-3 text-xs text-gray-600 dark:text-gray-400">
                                Nog geen anamnese voor deze persoon. Bij <span class="font-medium">Koppelen</span> wordt een anamnese aangemaakt en het GVL-formulier toegevoegd.
                            </p>
                            <x-admin::gvl-form-link
                                :gvlFormLink="null"
                                :attachUrl="route('admin.anamnesis.create-and-attach-gvl-form')"
                                :detachUrl="null"
                                :statusUrl="null"
                                :entityId="null"
                                entityType="person"
                                :personId="$person->id"
                                :leadId="$leadId"
                                :personHasPortalAccount="!empty($person->keycloak_user_id)"
                            />
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
