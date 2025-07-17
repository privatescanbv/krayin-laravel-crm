<x-admin::layouts>
    <!-- Page Title -->
    <x-slot:title>
        Anamnesis bewerken - {{ $anamnesis->lead->title }}
    </x-slot>

    <!-- Edit Anamnesis Form -->
    <x-admin::form
        :action="route('admin.anamnesis.update', $anamnesis->id)"
        method="PUT"
    >
        <div class="flex flex-col gap-4">
            <div
                class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-x-2.5">
                        <a href="{{ route('admin.leads.view', $anamnesis->lead_id) }}" class="text-blue-600 hover:underline">
                            ← Terug naar Lead
                        </a>
                    </div>

                    <div class="text-xl font-bold dark:text-white">
                        Anamnesis bewerken - {{ $anamnesis->lead->title }}
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <!-- Save button for Editing Anamnesis -->
                    <div class="flex items-center gap-x-2.5">
                        <button
                            type="submit"
                            class="primary-button"
                        >
                            Opslaan
                        </button>
                    </div>
                </div>
            </div>

            <!-- Form Content -->
            <div class="flex gap-4 max-lg:flex-wrap">
                <!-- Left Panel -->
                <div class="flex flex-1 flex-col gap-4 max-lg:flex-auto">
                    <!-- Basic Information -->
                    <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="mb-4 text-lg font-semibold dark:text-white">Algemene informatie</h2>
                        
                        <div class="mb-4">
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    Naam
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="text"
                                    name="name"
                                    :value="$anamnesis->name"
                                    placeholder="Anamnesis naam"
                                />
                            </x-admin::form.control-group>
                        </div>

                        <div class="mb-4">
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    Beschrijving
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="textarea"
                                    name="description"
                                    :value="$anamnesis->description"
                                    placeholder="Beschrijving"
                                    rows="3"
                                />
                            </x-admin::form.control-group>
                        </div>

                        <div class="mb-4">
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    Kliniek opmerkingen
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="textarea"
                                    name="comment_clinic"
                                    :value="$anamnesis->comment_clinic"
                                    placeholder="Kliniek opmerkingen"
                                    rows="3"
                                />
                            </x-admin::form.control-group>
                        </div>
                    </div>

                    <!-- Physical Information -->
                    <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="mb-4 text-lg font-semibold dark:text-white">Fysieke informatie</h2>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        Lengte (cm)
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="number"
                                        name="lengte"
                                        :value="$anamnesis->lengte"
                                        placeholder="180"
                                    />
                                </x-admin::form.control-group>
                            </div>

                            <div>
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        Gewicht (kg)
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="number"
                                        name="gewicht"
                                        :value="$anamnesis->gewicht"
                                        placeholder="70"
                                    />
                                </x-admin::form.control-group>
                            </div>
                        </div>
                    </div>

                    <!-- Medical Conditions -->
                    <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="mb-4 text-lg font-semibold dark:text-white">Medische condities</h2>
                        
                        <div class="space-y-4">
                            <!-- Metalen -->
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.control
                                            type="checkbox"
                                            name="metalen"
                                            :value="1"
                                            :checked="$anamnesis->metalen"
                                        />
                                        <x-admin::form.control-group.label class="ml-2">
                                            Metalen
                                        </x-admin::form.control-group.label>
                                    </x-admin::form.control-group>
                                </div>
                                <div class="flex-1">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_metalen_c"
                                        :value="$anamnesis->opm_metalen_c"
                                        placeholder="Opmerkingen metalen"
                                    />
                                </div>
                            </div>

                            <!-- Medicijnen -->
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.control
                                            type="checkbox"
                                            name="medicijnen"
                                            :value="1"
                                            :checked="$anamnesis->medicijnen"
                                        />
                                        <x-admin::form.control-group.label class="ml-2">
                                            Medicijnen
                                        </x-admin::form.control-group.label>
                                    </x-admin::form.control-group>
                                </div>
                                <div class="flex-1">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_medicijnen_c"
                                        :value="$anamnesis->opm_medicijnen_c"
                                        placeholder="Opmerkingen medicijnen"
                                    />
                                </div>
                            </div>

                            <!-- Glaucoom -->
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.control
                                            type="checkbox"
                                            name="glaucoom"
                                            :value="1"
                                            :checked="$anamnesis->glaucoom"
                                        />
                                        <x-admin::form.control-group.label class="ml-2">
                                            Glaucoom
                                        </x-admin::form.control-group.label>
                                    </x-admin::form.control-group>
                                </div>
                                <div class="flex-1">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_glaucoom_c"
                                        :value="$anamnesis->opm_glaucoom_c"
                                        placeholder="Opmerkingen glaucoom"
                                    />
                                </div>
                            </div>

                            <!-- Claustrofobie -->
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.control
                                            type="checkbox"
                                            name="claustrofobie"
                                            :value="1"
                                            :checked="$anamnesis->claustrofobie"
                                        />
                                        <x-admin::form.control-group.label class="ml-2">
                                            Claustrofobie
                                        </x-admin::form.control-group.label>
                                    </x-admin::form.control-group>
                                </div>
                            </div>

                            <!-- Dormicum -->
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.control
                                            type="checkbox"
                                            name="dormicum"
                                            :value="1"
                                            :checked="$anamnesis->dormicum"
                                        />
                                        <x-admin::form.control-group.label class="ml-2">
                                            Dormicum
                                        </x-admin::form.control-group.label>
                                    </x-admin::form.control-group>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Panel -->
                <div class="flex flex-1 flex-col gap-4 max-lg:flex-auto">
                    <!-- Medical History -->
                    <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="mb-4 text-lg font-semibold dark:text-white">Medische geschiedenis</h2>
                        
                        <div class="space-y-4">
                            <!-- Hart operatie -->
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.control
                                            type="checkbox"
                                            name="hart_operatie_c"
                                            :value="1"
                                            :checked="$anamnesis->hart_operatie_c"
                                        />
                                        <x-admin::form.control-group.label class="ml-2">
                                            Hart operatie
                                        </x-admin::form.control-group.label>
                                    </x-admin::form.control-group>
                                </div>
                                <div class="flex-1">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_hart_operatie_c"
                                        :value="$anamnesis->opm_hart_operatie_c"
                                        placeholder="Opmerkingen hart operatie"
                                    />
                                </div>
                            </div>

                            <!-- Implantaat -->
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.control
                                            type="checkbox"
                                            name="implantaat_c"
                                            :value="1"
                                            :checked="$anamnesis->implantaat_c"
                                        />
                                        <x-admin::form.control-group.label class="ml-2">
                                            Implantaat
                                        </x-admin::form.control-group.label>
                                    </x-admin::form.control-group>
                                </div>
                                <div class="flex-1">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_implantaat_c"
                                        :value="$anamnesis->opm_implantaat_c"
                                        placeholder="Opmerkingen implantaat"
                                    />
                                </div>
                            </div>

                            <!-- Operaties -->
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.control
                                            type="checkbox"
                                            name="operaties_c"
                                            :value="1"
                                            :checked="$anamnesis->operaties_c"
                                        />
                                        <x-admin::form.control-group.label class="ml-2">
                                            Operaties
                                        </x-admin::form.control-group.label>
                                    </x-admin::form.control-group>
                                </div>
                                <div class="flex-1">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_operaties_c"
                                        :value="$anamnesis->opm_operaties_c"
                                        placeholder="Opmerkingen operaties"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hereditary Conditions -->
                    <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="mb-4 text-lg font-semibold dark:text-white">Erfelijke aandoeningen</h2>
                        
                        <div class="space-y-4">
                            <!-- Hart erfelijk -->
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.control
                                            type="checkbox"
                                            name="hart_erfelijk"
                                            :value="1"
                                            :checked="$anamnesis->hart_erfelijk"
                                        />
                                        <x-admin::form.control-group.label class="ml-2">
                                            Hart erfelijk
                                        </x-admin::form.control-group.label>
                                    </x-admin::form.control-group>
                                </div>
                                <div class="flex-1">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_erf_hart_c"
                                        :value="$anamnesis->opm_erf_hart_c"
                                        placeholder="Opmerkingen hart erfelijk"
                                    />
                                </div>
                            </div>

                            <!-- Vaat erfelijk -->
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.control
                                            type="checkbox"
                                            name="vaat_erfelijk"
                                            :value="1"
                                            :checked="$anamnesis->vaat_erfelijk"
                                        />
                                        <x-admin::form.control-group.label class="ml-2">
                                            Vaat erfelijk
                                        </x-admin::form.control-group.label>
                                    </x-admin::form.control-group>
                                </div>
                                <div class="flex-1">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_erf_vaat_c"
                                        :value="$anamnesis->opm_erf_vaat_c"
                                        placeholder="Opmerkingen vaat erfelijk"
                                    />
                                </div>
                            </div>

                            <!-- Tumoren erfelijk -->
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.control
                                            type="checkbox"
                                            name="tumoren_erfelijk"
                                            :value="1"
                                            :checked="$anamnesis->tumoren_erfelijk"
                                        />
                                        <x-admin::form.control-group.label class="ml-2">
                                            Tumoren erfelijk
                                        </x-admin::form.control-group.label>
                                    </x-admin::form.control-group>
                                </div>
                                <div class="flex-1">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_erf_tumor_c"
                                        :value="$anamnesis->opm_erf_tumor_c"
                                        placeholder="Opmerkingen tumoren erfelijk"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lifestyle -->
                    <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="mb-4 text-lg font-semibold dark:text-white">Levensstijl</h2>
                        
                        <div class="space-y-4">
                            <!-- Roken -->
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.control
                                            type="checkbox"
                                            name="smoking"
                                            :value="1"
                                            :checked="$anamnesis->smoking"
                                        />
                                        <x-admin::form.control-group.label class="ml-2">
                                            Roken
                                        </x-admin::form.control-group.label>
                                    </x-admin::form.control-group>
                                </div>
                                <div class="flex-1">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_roken_c"
                                        :value="$anamnesis->opm_roken_c"
                                        placeholder="Opmerkingen roken"
                                    />
                                </div>
                            </div>

                            <!-- Diabetes -->
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.control
                                            type="checkbox"
                                            name="diabetes"
                                            :value="1"
                                            :checked="$anamnesis->diabetes"
                                        />
                                        <x-admin::form.control-group.label class="ml-2">
                                            Diabetes
                                        </x-admin::form.control-group.label>
                                    </x-admin::form.control-group>
                                </div>
                                <div class="flex-1">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_diabetes_c"
                                        :value="$anamnesis->opm_diabetes_c"
                                        placeholder="Opmerkingen diabetes"
                                    />
                                </div>
                            </div>

                                                         <!-- Actief -->
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.control
                                            type="checkbox"
                                            name="actief"
                                            :value="1"
                                            :checked="$anamnesis->actief"
                                        />
                                        <x-admin::form.control-group.label class="ml-2">
                                            Actief
                                        </x-admin::form.control-group.label>
                                    </x-admin::form.control-group>
                                </div>
                            </div>

                            <!-- Spijsverteringsklachten -->
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.control
                                            type="checkbox"
                                            name="spijsverteringsklachten"
                                            :value="1"
                                            :checked="$anamnesis->spijsverteringsklachten"
                                        />
                                        <x-admin::form.control-group.label class="ml-2">
                                            Spijsverteringsklachten
                                        </x-admin::form.control-group.label>
                                    </x-admin::form.control-group>
                                </div>
                                <div class="flex-1">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_spijsvertering_c"
                                        :value="$anamnesis->opm_spijsvertering_c"
                                        placeholder="Opmerkingen spijsvertering"
                                    />
                                </div>
                            </div>

                            <!-- Allergie -->
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.control
                                            type="checkbox"
                                            name="allergie_c"
                                            :value="1"
                                            :checked="$anamnesis->allergie_c"
                                        />
                                        <x-admin::form.control-group.label class="ml-2">
                                            Allergie
                                        </x-admin::form.control-group.label>
                                    </x-admin::form.control-group>
                                </div>
                                <div class="flex-1">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_allergie_c"
                                        :value="$anamnesis->opm_allergie_c"
                                        placeholder="Opmerkingen allergie"
                                    />
                                </div>
                            </div>

                            <!-- Rugklachten -->
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.control
                                            type="checkbox"
                                            name="rugklachten"
                                            :value="1"
                                            :checked="$anamnesis->rugklachten"
                                        />
                                        <x-admin::form.control-group.label class="ml-2">
                                            Rugklachten
                                        </x-admin::form.control-group.label>
                                    </x-admin::form.control-group>
                                </div>
                                <div class="flex-1">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_rugklachten_c"
                                        :value="$anamnesis->opm_rugklachten_c"
                                        placeholder="Opmerkingen rugklachten"
                                    />
                                </div>
                            </div>

                            <!-- Hartproblemen -->
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.control
                                            type="checkbox"
                                            name="heart_problems"
                                            :value="1"
                                            :checked="$anamnesis->heart_problems"
                                        />
                                        <x-admin::form.control-group.label class="ml-2">
                                            Hartproblemen
                                        </x-admin::form.control-group.label>
                                    </x-admin::form.control-group>
                                </div>
                                <div class="flex-1">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_hartklachten_c"
                                        :value="$anamnesis->opm_hartklachten_c"
                                        placeholder="Opmerkingen hartklachten"
                                    />
                                </div>
                            </div>

                            <!-- Risico hartinfarct -->
                            <div class="flex items-start gap-4">
                                <div class="flex-1">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.label>
                                            Risico hartinfarct
                                        </x-admin::form.control-group.label>

                                        <x-admin::form.control-group.control
                                            type="text"
                                            name="risico_hartinfarct"
                                            :value="$anamnesis->risico_hartinfarct"
                                            placeholder="Risico hartinfarct"
                                        />
                                    </x-admin::form.control-group>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Final Notes -->
                    <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="mb-4 text-lg font-semibold dark:text-white">Opmerkingen en advies</h2>
                        
                        <div class="space-y-4">
                            <div>
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        Opmerking
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opmerking"
                                        :value="$anamnesis->opmerking"
                                        placeholder="Algemene opmerking"
                                    />
                                </x-admin::form.control-group>
                            </div>

                            <div>
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        Advies
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="textarea"
                                        name="opm_advies_c"
                                        :value="$anamnesis->opm_advies_c"
                                        placeholder="Advies voor patiënt"
                                        rows="3"
                                    />
                                </x-admin::form.control-group>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>