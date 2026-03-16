@props([
    'anamnesis',
    'person' => null,
    'showCreatedDate' => false
])
<div class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
    <div>
@if ($anamnesis)
    @php
        $returnUrl = request()->fullUrlWithoutQuery(['return_url']).'#anamnese';
    @endphp
    <div class="p-3 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                <i class="icon-activity text-blue-600"></i>
                {{ $anamnesis->person->name }}
            </h3>

            <!-- Acties rechts -->
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.leads.sync-anamnesis-to-older-update', ['personId' => $anamnesis->person->id, 'return_url' => $returnUrl]) }}"
                   class="secondary-button flex items-center gap-1 border hover:border-neutral-text hover:text-neutral-text">
                    Synchroniseer met oudere Anamneses
                </a>

                <a
                    href="{{ route('admin.anamnesis.edit', ['id' => $anamnesis->id, 'return_url' => $returnUrl]) }}"
                    class="p-2 text-gray-500 rounded-lg hover:bg-gray-100 hover:text-blue-600 transition-colors"
                    title="Anamnese bewerken"
                >
                    <i class="icon-edit text-lg"></i>
                </a>
            </div>
        </div>

        <div class="space-y-4">
            {{-- Description --}}
            @if ($anamnesis->description)
                <div class="p-3 bg-gray-50 rounded-md border border-gray-100 dark:bg-gray-700/50 dark:border-gray-600">

                    <read-more
                        :text='@json($anamnesis->description ?? "")'
                        :lines="5"
                    />

{{--                    <p class="text-sm text-gray-700 dark:text-gray-300 italic">--}}
{{--                        "{{ Str::limit($anamnesis->description, 100) }}"--}}
{{--                    </p>--}}
                </div>
            @endif

            {{-- Vitals --}}
            @if ($anamnesis->height || $anamnesis->weight)
                <div class="flex gap-4">
                    @if ($anamnesis->height)
                        <div class="flex items-center gap-2 px-3 py-1.5 bg-blue-50 text-blue-700 rounded-md dark:bg-blue-900/30 dark:text-blue-300 border border-blue-100 dark:border-blue-800">
                            <span class="text-xs uppercase font-semibold text-blue-500 dark:text-blue-400">Lengte</span>
                            <span class="font-bold">{{ $anamnesis->height }} cm</span>
                        </div>
                    @endif
                    @if ($anamnesis->weight)
                        <div class="flex items-center gap-2 px-3 py-1.5 bg-blue-50 text-blue-700 rounded-md dark:bg-blue-900/30 dark:text-blue-300 border border-blue-100 dark:border-blue-800">
                            <span class="text-xs uppercase font-semibold text-blue-500 dark:text-blue-400">Gewicht</span>
                            <span class="font-bold">{{ $anamnesis->weight }} kg</span>
                        </div>
                    @endif
                </div>
            @endif
            <x-admin::gvl-form-link
                :gvlFormLink="$anamnesis->gvl_form_link"
                :attachUrl="route('admin.anamnesis.gvl-form.attach', $anamnesis->id)"
                :detachUrl="route('admin.anamnesis.gvl-form.detach', $anamnesis->id)"
                :statusUrl="route('admin.anamnesis.gvl-form.status', $anamnesis->id)"
                :entityId="$anamnesis->id"
                entityType="anamnesis"
                :personId="$anamnesis->person_id"
                :personHasPortalAccount="!empty(($person ?? $anamnesis->person)?->keycloak_user_id)"
                readonly="true"
            />

            {{-- Conditions --}}
            @php
                $conditions = collect([
                    'metals' => 'Metaal',
                    'medications' => 'Medicatie',
                    'glaucoma' => 'Glaucoom',
                    'claustrophobia' => 'Claustrofobisch',
                    'dormicum' => 'Rustgevend',
                    'heart_surgery' => 'Hartoperatie',
                    'implant' => 'Implantaat',
                    'surgeries' => 'Operaties',
                    'hereditary_heart' => 'Hartafwijking',
                    'hereditary_vascular' => 'Hart-/vaatziekten',
                    'hereditary_tumors' => 'Kanker familie',
                    'allergies' => 'Allergieën',
                    'back_problems' => 'Kan stil liggen',
                    'heart_problems' => 'Hartproblemen',
                    'smoking' => 'Rookt',
                    'diabetes' => 'Diabetes',
                    'digestive_problems' => 'Spijsverteringsklachten',
                    'active' => 'Lichamelijk actief'
                ])->filter(function($label, $field) use ($anamnesis) {
                    return $anamnesis->{$field} == 1;
                });
            @endphp

            <div>
                @if ($conditions->isNotEmpty())
                    <div class="flex flex-wrap gap-2">
                        @foreach ($conditions as $field => $label)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 border border-red-200 dark:border-red-800">
                                {{ $label }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400 text-sm">
                        <i class="icon-check-circle text-green-500"></i>
                        <span>Geen bijzonderheden gemeld</span>
                    </div>
                @endif
            </div>

            {{-- Footer / Meta --}}
            <div class="pt-3 mt-2 border-t border-gray-100 dark:border-gray-700 flex justify-end text-xs text-gray-400 dark:text-gray-500">
                <span class="flex items-center gap-1">
                    <i class="icon-clock"></i>
                    Laatst gewijzigd: {{ $anamnesis->updated_at ? $anamnesis->updated_at->format('d-m-Y H:i') : '-' }}
                </span>
            </div>
        </div>
    </div>
@endif
    </div>
</div>
