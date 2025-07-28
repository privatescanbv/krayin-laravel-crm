{!! view_render_event('admin.leads.view.compact_overview.before', ['lead' => $lead]) !!}

<div class="flex w-full flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-800">
    <x-admin::accordion class="select-none !border-none">
        <x-slot:header class="!p-0">
            <div class="flex w-full items-center justify-between gap-4 font-semibold dark:text-white">
                <h4>Over de lead</h4>

                @if (bouncer()->hasPermission('leads.edit'))
                    <a
                        href="{{ route('admin.leads.edit', $lead->id) }}"
                        class="icon-edit rounded-md p-1.5 text-2xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                    ></a>
                @endif
            </div>
        </x-slot>

        <x-slot:content class="mt-4 !px-0 !pb-0">
            {!! view_render_event('admin.leads.view.attributes.form_controls.before', ['lead' => $lead]) !!}

            <div class="flex flex-col gap-3 text-sm">
                <!-- Name Summary -->
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300">Naam:</span>
                    <div class="mt-1 text-gray-900 dark:text-gray-100">
                        @php
                            $nameParts = array_filter([
                                $lead->salutation,
                                $lead->initials,
                                $lead->first_name,
                                $lead->lastname_prefix,
                                $lead->last_name
                            ]);
                            
                            // Add married name in parentheses if it exists
                            if ($lead->married_name) {
                                $marriedNamePart = $lead->married_name_prefix 
                                    ? "({$lead->married_name_prefix} {$lead->married_name})"
                                    : "({$lead->married_name})";
                                $nameParts[] = $marriedNamePart;
                            }
                            
                            $fullName = implode(' ', $nameParts);
                        @endphp
                        {{ $fullName ?: ($lead->first_name . ' ' . $lead->last_name) }}
                    </div>
                </div>

                <!-- Lead Value -->
                @if($lead->lead_value)
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300">Lead waarde:</span>
                    <div class="mt-1 text-gray-900 dark:text-gray-100">
                        € {{ number_format($lead->lead_value, 2, ',', '.') }}
                    </div>
                </div>
                @endif

                <!-- Sales Owner -->
                @if($lead->user)
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300">Verkoop eigenaar:</span>
                    <div class="mt-1 text-gray-900 dark:text-gray-100">
                        {{ $lead->user->name }}
                    </div>
                </div>
                @endif

                <!-- Expected Close Date -->
                @if($lead->expected_close_date)
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300">Verwachte sluitingsdatum:</span>
                    <div class="mt-1 text-gray-900 dark:text-gray-100">
                        {{ $lead->expected_close_date->format('d-m-Y') }}
                    </div>
                </div>
                @endif

                <!-- Contact Person (if linked) -->
                @if($lead->person)
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300">Contact persoon:</span>
                    <div class="mt-1">
                        <a 
                            href="{{ route('admin.contacts.persons.view', $lead->person->id) }}" 
                            target="_blank"
                            class="text-brandColor hover:underline font-medium"
                        >
                            {{ $lead->person->name }}
                            <span class="icon-external-link text-xs ml-1"></span>
                        </a>
                    </div>
                </div>
                @endif

                <!-- Organization (if person has organization) -->
                @if($lead->person && $lead->person->organization)
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300">Organisatie:</span>
                    <div class="mt-1">
                        <a 
                            href="{{ route('admin.contacts.organizations.view', $lead->person->organization->id) }}" 
                            target="_blank"
                            class="text-brandColor hover:underline font-medium"
                        >
                            {{ $lead->person->organization->name }}
                            <span class="icon-external-link text-xs ml-1"></span>
                        </a>
                    </div>
                </div>
                @endif

                <!-- Address -->
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300">Adres:</span>
                    <div class="mt-1 text-gray-900 dark:text-gray-100">
                        @if($lead->address && $lead->address->full_address)
                            {{ $lead->address->full_address }}
                            <a 
                                href="https://maps.google.com/?q={{ urlencode($lead->address->full_address) }}" 
                                target="_blank" 
                                class="ml-2 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                title="Bekijk op Google Maps"
                            >
                                <span class="icon-location text-sm"></span>
                            </a>
                        @else
                            <span class="text-gray-500 dark:text-gray-400 italic">Geen adres</span>
                        @endif
                    </div>
                </div>

                <!-- Contact Information -->
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300">Contactgegevens:</span>
                    <div class="mt-1">
                        <!-- Email Addresses -->
                        @if($lead->emails && is_array($lead->emails) && count($lead->emails) > 0)
                            @php
                                $defaultEmail = collect($lead->emails)->firstWhere('is_default', true) 
                                               ?? collect($lead->emails)->first();
                                $otherEmails = collect($lead->emails)->reject(function($email) use ($defaultEmail) {
                                    return $email['value'] === $defaultEmail['value'];
                                });
                            @endphp
                            
                            <div class="text-gray-900 dark:text-gray-100">
                                {{ $defaultEmail['value'] }}
                            </div>
                            
                            @if($otherEmails->count() > 0)
                                <div class="text-xs text-gray-500 dark:text-gray-400 italic ml-2">
                                    @foreach($otherEmails as $email)
                                        {{ $email['value'] }}@if(!$loop->last), @endif
                                    @endforeach
                                </div>
                            @endif
                        @else
                            <div class="text-gray-500 dark:text-gray-400 italic">Geen e-mailadres</div>
                        @endif

                        <!-- Phone Numbers -->
                        @if($lead->phones && is_array($lead->phones) && count($lead->phones) > 0)
                            @php
                                $defaultPhone = collect($lead->phones)->firstWhere('is_default', true) 
                                               ?? collect($lead->phones)->first();
                                $otherPhones = collect($lead->phones)->reject(function($phone) use ($defaultPhone) {
                                    return $phone['value'] === $defaultPhone['value'];
                                });
                            @endphp
                            
                            <div class="text-gray-900 dark:text-gray-100 mt-1">
                                {{ $defaultPhone['value'] }}
                            </div>
                            
                            @if($otherPhones->count() > 0)
                                <div class="text-xs text-gray-500 dark:text-gray-400 italic ml-2">
                                    @foreach($otherPhones as $phone)
                                        {{ $phone['value'] }}@if(!$loop->last), @endif
                                    @endforeach
                                </div>
                            @endif
                        @else
                            <div class="text-gray-500 dark:text-gray-400 italic mt-1">Geen telefoonnummer</div>
                        @endif
                    </div>
                </div>

                <!-- Lead Specific Fields -->
                <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                    <h5 class="font-medium text-gray-700 dark:text-gray-300 mb-2">Lead informatie</h5>
                    
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <!-- Lead Source -->
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Bron:</span>
                            <div class="text-gray-900 dark:text-gray-100">
                                {{ $lead->source->name ?? 'Onbekend' }}
                            </div>
                        </div>

                        <!-- Lead Type -->
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Type:</span>
                            <div class="text-gray-900 dark:text-gray-100">
                                {{ $lead->type->name ?? 'Onbekend' }}
                            </div>
                        </div>

                        <!-- Lead Channel -->
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Kanaal:</span>
                            <div class="text-gray-900 dark:text-gray-100">
                                {{ $lead->channel->name ?? 'Onbekend' }}
                            </div>
                        </div>

                        <!-- Department -->
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Afdeling:</span>
                            <div class="text-gray-900 dark:text-gray-100">
                                {{ $lead->department->name ?? 'Onbekend' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom Attributes Section -->
            <div class="mt-6">
                <x-admin::form
                    v-slot="{ meta, errors, handleSubmit }"
                    as="div"
                    ref="modalForm"
                >
                    <form @submit="handleSubmit($event, () => {})">
                        {!! view_render_event('admin.leads.view.attributes.form_controls.attributes.view.before', ['lead' => $lead]) !!}

                        <x-admin::attributes.view
                            :custom-attributes="app('Webkul\\Attribute\\Repositories\\AttributeRepository')->findWhere([
                                'entity_type' => 'leads',
                                ['code', 'NOTIN', ['title', 'description', 'lead_pipeline_id', 'lead_pipeline_stage_id']]
                            ])"
                            :entity="$lead"
                            :url="route('admin.leads.attributes.update', $lead->id)"
                            :allow-edit="true"
                        />

                        {!! view_render_event('admin.leads.view.attributes.form_controls.attributes.view.after', ['lead' => $lead]) !!}
                    </form>
                </x-admin::form>
            </div>

            {!! view_render_event('admin.leads.view.attributes.form_controls.after', ['lead' => $lead]) !!}
        </x-slot>
    </x-admin::accordion>
</div>

{!! view_render_event('admin.leads.view.compact_overview.after', ['lead' => $lead]) !!}