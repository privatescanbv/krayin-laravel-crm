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
        @include('adminc.components.validation-errors')
        <div class="flex flex-col gap-4">
            <div
                class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-x-2.5">
                        <a href="{{ route('admin.leads.view', $anamnesis->lead_id) }}" class="text-activity-note-text hover:underline">
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

            <!-- Validation Errors -->
            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                                Er zijn validatiefouten opgetreden
                            </h3>
                            <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                <ul class="list-disc pl-5 space-y-1">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Form Content -->
            <div class="flex gap-4 max-lg:flex-wrap">
                <!-- Left Panel -->
                <div class="flex flex-1 flex-col gap-4 max-lg:flex-auto">
                    <!-- Basic Information -->
                    <div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="mb-4 text-lg font-semibold dark:text-white">Algemene informatie</h2>

                        <div class="mb-4">
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.control
                                    type="text"
                                    name="name"
                                    :value="$anamnesis->name"
                                    placeholder="Anamnesis naam"
                                />
                                <x-admin::form.control-group.label>
                                    Naam
                                </x-admin::form.control-group.label>

                            </x-admin::form.control-group>
                        </div>

                        <div class="mb-4">
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.control
                                    type="textarea"
                                    name="description"
                                    :value="$anamnesis->description"
                                    placeholder="Beschrijving"
                                    rows="3"
                                />
                                <x-admin::form.control-group.label>
                                    Beschrijving
                                </x-admin::form.control-group.label>

                            </x-admin::form.control-group>
                        </div>

                        <div class="mb-4">

                            <x-admin::gvl-form-link
                                :gvlFormLink="$anamnesis->gvl_form_link"
                                :attachUrl="route('admin.anamnesis.gvl-form.attach', $anamnesis->id)"
                                :detachUrl="route('admin.anamnesis.gvl-form.detach', $anamnesis->id)"
                                :statusUrl="route('admin.anamnesis.gvl-form.status', $anamnesis->id)"
                                :entityId="$anamnesis->id"
                                entityType="anamnesis"
                            />
                        </div>

                        <div class="mb-4">
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.control
                                    type="textarea"
                                    name="comment_clinic"
                                    :value="$anamnesis->comment_clinic"
                                    placeholder="Kliniek opmerkingen"
                                    rows="3"
                                />
                                <x-admin::form.control-group.label>
                                    Kliniek opmerkingen
                                </x-admin::form.control-group.label>

                            </x-admin::form.control-group>
                        </div>
                    </div>

                    <!-- Physical Information -->
                    <div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="mb-4 text-lg font-semibold dark:text-white">Fysieke informatie</h2>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.control
                                        type="number"
                                        name="height"
                                        :value="$anamnesis->height"
                                        placeholder="180"
                                        onchange="updateBMI()"
                                    />
                                    <x-admin::form.control-group.label>
                                        Lengte (cm)
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>
                            </div>

                            <div>
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.control
                                        type="number"
                                        name="weight"
                                        :value="$anamnesis->weight"
                                        placeholder="70"
                                        onchange="updateBMI()"
                                    />
                                    <x-admin::form.control-group.label>
                                        Gewicht (kg)
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>
                            </div>
                        </div>

                        <!-- BMI Calculator -->
                        <div id="bmi-display">
                            <x-admin::health.bmi-calculator
                                :height="$anamnesis->height"
                                :weight="$anamnesis->weight"
                                :show-label="false"
                            />
                        </div>
                    </div>

                    <!-- Contra-indicaties -->
                    <div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="mb-4 text-lg font-semibold dark:text-white">Contra-indicaties</h2>

                        <div class="space-y-6">
                            <!-- Metalen -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="metals" value="1"
                                                   {{ $anamnesis->metals == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('metals', this.checked)"
                                                   class="mr-2 {{ $errors->has('metals') ? 'border-error' : '' }}">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="metals" value="0"
                                                   {{ $anamnesis->metals == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('metals', false)"
                                                   class="mr-2 {{ $errors->has('metals') ? 'border-error' : '' }}">
                                            Nee
                                        </label>
                                    </div>

                                    @error('metals')
                                        <p class="mt-1 text-xs italic text-status-expired-text">{{ $message }}</p>
                                    @enderror
                                    <x-admin::form.control-group.label class="required">
                                        Heeft u metaal in uw lichaam?
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>

                                <div id="metals_comment" class="mt-2" style="display: {{ $anamnesis->metals == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="metals_notes"
                                        :value="$anamnesis->metals_notes"
                                        placeholder="Toelichting metalen"
                                    />
                                </div>
                            </div>

                            <!-- Medicijnen -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="medications" value="1"
                                                   {{ $anamnesis->medications == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('medications', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="medications" value="0"
                                                   {{ $anamnesis->medications == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('medications', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                    <x-admin::form.control-group.label class="required">
                                        Gebruikt u Metformine?
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>

                                <div id="medications_comment" class="mt-2" style="display: {{ $anamnesis->medications == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="medications_notes"
                                        :value="$anamnesis->medications_notes"
                                        placeholder="Toelichting medicijnen"
                                    />
                                </div>
                            </div>

                            <!-- Glaucoom -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="glaucoma" value="1"
                                                   {{ $anamnesis->glaucoma == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('glaucoma', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="glaucoma" value="0"
                                                   {{ $anamnesis->glaucoma == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('glaucoma', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                    <x-admin::form.control-group.label class="required">
                                        Heeft u glaucoom?
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>

                                <div id="glaucoma_comment" class="mt-2" style="display: {{ $anamnesis->glaucoma == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="glaucoma_notes"
                                        :value="$anamnesis->glaucoma_notes"
                                        placeholder="Toelichting glaucoom"
                                    />
                                </div>
                            </div>

                            <!-- Claustrofobie -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="claustrophobia" value="1"
                                                   {{ $anamnesis->claustrophobia == 1 ? 'checked' : '' }}
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="claustrophobia" value="0"
                                                   {{ $anamnesis->claustrophobia == 0 ? 'checked' : '' }}
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                    <x-admin::form.control-group.label class="required">
                                        Bent u claustrofobisch?
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>
                            </div>

                            <!-- Dormicum -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="dormicum" value="1"
                                                   {{ $anamnesis->dormicum == 1 ? 'checked' : '' }}
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="dormicum" value="0"
                                                   {{ $anamnesis->dormicum == 0 ? 'checked' : '' }}
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                    <x-admin::form.control-group.label class="required">
                                        Wenst u een rustgevend middel?
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>
                            </div>

                            <!-- Hart operatie -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="heart_surgery" value="1"
                                                   {{ $anamnesis->heart_surgery == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('heart_surgery', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="heart_surgery" value="0"
                                                   {{ $anamnesis->heart_surgery == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('heart_surgery', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                    <x-admin::form.control-group.label class="required">
                                        Heeft u ooit een hartkatheterisatie gehad?
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>

                                <div id="heart_surgery_comment" class="mt-2" style="display: {{ $anamnesis->heart_surgery == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="heart_surgery_notes"
                                        :value="$anamnesis->heart_surgery_notes"
                                        placeholder="Toelichting hart operatie"
                                    />
                                </div>
                            </div>

                            <!-- Implantaat -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="implant" value="1"
                                                   {{ $anamnesis->implant == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('implant', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="implant" value="0"
                                                   {{ $anamnesis->implant == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('implant', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                    <x-admin::form.control-group.label class="required">
                                        Draagt u een implantaat?
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>

                                <div id="implant_comment" class="mt-2" style="display: {{ $anamnesis->implant == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="implant_notes"
                                        :value="$anamnesis->implant_notes"
                                        placeholder="Toelichting implantaat"
                                    />
                                </div>
                            </div>

                            <!-- Operaties -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="surgeries" value="1"
                                                   {{ $anamnesis->surgeries == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('surgeries', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="surgeries" value="0"
                                                   {{ $anamnesis->surgeries == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('surgeries', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                    <x-admin::form.control-group.label class="required">
                                        Heeft u in het verleden operaties gehad?
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>

                                <div id="surgeries_comment" class="mt-2" style="display: {{ $anamnesis->surgeries == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="surgeries_notes"
                                        :value="$anamnesis->surgeries_notes"
                                        placeholder="Toelichting operaties"
                                    />
                                </div>
                            </div>

                            <!-- Hartproblemen -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="heart_problems" value="1"
                                                   {{ $anamnesis->heart_problems == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('heart_problems', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="heart_problems" value="0"
                                                   {{ $anamnesis->heart_problems == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('heart_problems', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                    <x-admin::form.control-group.label class="required">
                                        Heeft u hartproblemen?
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>

                                <div id="heart_problems_comment" class="mt-2" style="display: {{ $anamnesis->heart_problems == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="heart_problems_notes"
                                        :value="$anamnesis->heart_problems_notes"
                                        placeholder="Toelichting hartklachten"
                                    />
                                </div>
                            </div>

                            <!-- Rugklachten -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="back_problems" value="1"
                                                   {{ $anamnesis->back_problems == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('back_problems', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="back_problems" value="0"
                                                   {{ $anamnesis->back_problems == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('back_problems', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                    <x-admin::form.control-group.label class="required">
                                        Kunt u langere tijd stil liggen op uw rug?
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>

                                <div id="back_problems_comment" class="mt-2" style="display: {{ $anamnesis->back_problems == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="back_problems_notes"
                                        :value="$anamnesis->back_problems_notes"
                                        placeholder="Toelichting rugklachten"
                                    />
                                </div>
                            </div>

                            <!-- Allergie -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="allergies" value="1"
                                                   {{ $anamnesis->allergies == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('allergies', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="allergies" value="0"
                                                   {{ $anamnesis->allergies == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('allergies', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                    <x-admin::form.control-group.label class="required">
                                        Heeft u allergieën?
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>

                                <div id="allergies_comment" class="mt-2" style="display: {{ $anamnesis->allergies == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="allergies_notes"
                                        :value="$anamnesis->allergies_notes"
                                        placeholder="Toelichting allergie"
                                    />
                                </div>
                            </div>

                            <!-- Spijsverteringsklachten -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="digestive_problems" value="1"
                                                   {{ $anamnesis->digestive_problems == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('digestive_problems', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="digestive_problems" value="0"
                                                   {{ $anamnesis->digestive_problems == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('digestive_problems', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                    <x-admin::form.control-group.label class="required">
                                        Heeft u spijsverteringsklachten
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>

                                <div id="digestive_problems_comment" class="mt-2" style="display: {{ $anamnesis->digestive_problems == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="digestive_problems_notes"
                                        :value="$anamnesis->digestive_problems_notes"
                                        placeholder="Toelichting spijsvertering"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Panel -->
                <div class="flex flex-1 flex-col gap-4 max-lg:flex-auto">
                    <!-- Gezondheid & Erfelijkheden -->
                    <div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="mb-4 text-lg font-semibold dark:text-white">Gezondheid & Erfelijkheden</h2>

                        <div class="space-y-6">
                            <!-- Hart erfelijk -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="hereditary_heart" value="1"
                                                   {{ $anamnesis->hereditary_heart == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('hereditary_heart', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="hereditary_heart" value="0"
                                                   {{ $anamnesis->hereditary_heart == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('hereditary_heart', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                    <x-admin::form.control-group.label class="required">
                                        Hartafwijking?
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>

                                <div id="hereditary_heart_comment" class="mt-2" style="display: {{ $anamnesis->hereditary_heart == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="hereditary_heart_notes"
                                        :value="$anamnesis->hereditary_heart_notes"
                                        placeholder="Toelichting hart erfelijk"
                                    />
                                </div>
                            </div>

                            <!-- Vaat erfelijk -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="hereditary_vascular" value="1"
                                                   {{ $anamnesis->hereditary_vascular == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('hereditary_vascular', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="hereditary_vascular" value="0"
                                                   {{ $anamnesis->hereditary_vascular == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('hereditary_vascular', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                    <x-admin::form.control-group.label class="required">
                                        Komt / kwam er in naaste familie hart- en/of vaatziekten voor?
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>

                                <div id="hereditary_vascular_comment" class="mt-2" style="display: {{ $anamnesis->hereditary_vascular == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="hereditary_vascular_notes"
                                        :value="$anamnesis->hereditary_vascular_notes"
                                        placeholder="Toelichting vaat erfelijk"
                                    />
                                </div>
                            </div>

                            <!-- Tumoren erfelijk -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="hereditary_tumors" value="1"
                                                   {{ $anamnesis->hereditary_tumors == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('hereditary_tumors', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="hereditary_tumors" value="0"
                                                   {{ $anamnesis->hereditary_tumors == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('hereditary_tumors', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                    <x-admin::form.control-group.label class="required">
                                        Komt / kwam er in de naaste familie kanker voor?
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>

                                <div id="hereditary_tumors_comment" class="mt-2" style="display: {{ $anamnesis->hereditary_tumors == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="hereditary_tumors_notes"
                                        :value="$anamnesis->hereditary_tumors_notes"
                                        placeholder="Toelichting tumoren erfelijk"
                                    />
                                </div>
                            </div>

                            <!-- Roken -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="smoking" value="1"
                                                   {{ $anamnesis->smoking == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('smoking', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="smoking" value="0"
                                                   {{ $anamnesis->smoking == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('smoking', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                    <x-admin::form.control-group.label class="required">
                                        Rookt u?
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>

                                <div id="smoking_comment" class="mt-2" style="display: {{ $anamnesis->smoking == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="smoking_notes"
                                        :value="$anamnesis->smoking_notes"
                                        placeholder="Toelichting roken"
                                    />
                                </div>
                            </div>

                            <!-- Diabetes -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="diabetes" value="1"
                                                   {{ $anamnesis->diabetes == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('diabetes', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="diabetes" value="0"
                                                   {{ $anamnesis->diabetes == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('diabetes', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                    <x-admin::form.control-group.label class="required">
                                        Heeft u diabetes?
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>

                                <div id="diabetes_comment" class="mt-2" style="display: {{ $anamnesis->diabetes == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="diabetes_notes"
                                        :value="$anamnesis->diabetes_notes"
                                        placeholder="Toelichting diabetes"
                                    />
                                </div>
                            </div>

                            <!-- Actief -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="active" value="1"
                                                   {{ $anamnesis->active == 1 ? 'checked' : '' }}
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="active" value="0"
                                                   {{ $anamnesis->active == 0 ? 'checked' : '' }}
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                    <x-admin::form.control-group.label class="required">
                                        Beweegt u regelmatig?
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>
                            </div>

                            <!-- Risico hartinfarct -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="heart_attack_risk"
                                        :value="$anamnesis->heart_attack_risk"
                                        placeholder="Risico hartinfarct"
                                    />
                                    <x-admin::form.control-group.label>
                                        Risico hartinfarct
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>
                            </div>
                        </div>
                    </div>



                    <!-- Final Notes -->
                    <div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="mb-4 text-lg font-semibold dark:text-white">Opmerkingen en advies</h2>

                        <div class="space-y-4">
                            <div>
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="remarks"
                                        :value="$anamnesis->remarks"
                                        placeholder="Algemene opmerking"
                                    />
                                    <x-admin::form.control-group.label>
                                        Opmerking
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>
                            </div>

                            <div>
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.control
                                        type="textarea"
                                        name="advice_notes"
                                        :value="$anamnesis->advice_notes"
                                        placeholder="Advies voor patiënt"
                                        rows="3"
                                    />
                                    <x-admin::form.control-group.label>
                                        Advies
                                    </x-admin::form.control-group.label>

                                </x-admin::form.control-group>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-admin::form>

    @pushOnce('scripts')
    <script>
        function toggleCommentField(fieldName, showField) {
            const commentDiv = document.getElementById(fieldName + '_comment');
            if (commentDiv) {
                commentDiv.style.display = showField ? 'block' : 'none';
            }
        }

        function updateBMI() {
            const heightInput = document.querySelector('input[name="height"]');
            const weightInput = document.querySelector('input[name="weight"]');
            const bmiDisplay = document.getElementById('bmi-display');

            const height = parseFloat(heightInput.value);
            const weight = parseFloat(weightInput.value);

            if (height && weight) {
                // Calculate BMI
                const heightInMeters = height / 100;
                const bmi = (weight / (heightInMeters * heightInMeters)).toFixed(1);

                // Determine category and colors
                let category, bgColor, textColor, barColor;
                if (bmi < 18.5) {
                    category = 'Ondergewicht';
                    bgColor = 'bg-activity-note-bg';
                    textColor = 'text-blue-700';
                    barColor = 'bg-brand-herniapoli-main';
                } else if (bmi < 25) {
                    category = 'Normaal gewicht';
                    bgColor = 'bg-status-active-bg';
                    textColor = 'text-green-700';
                    barColor = 'bg-succes';
                } else if (bmi < 30) {
                    category = 'Overgewicht';
                    bgColor = 'bg-status-on_hold-bg';
                    textColor = 'text-yellow-700';
                    barColor = 'bg-status-on_hold-text';
                } else {
                    category = 'Obesitas';
                    bgColor = 'bg-red-50';
                    textColor = 'text-red-700';
                    barColor = 'bg-red-500';
                }

                // Calculate position (BMI scale from 15 to 40)
                const position = Math.min(Math.max((bmi - 15) / 25 * 100, 0), 100);

                // Update the BMI display
                bmiDisplay.innerHTML = `
                    <div class="mt-4 p-3 ${bgColor} rounded-lg border bg-white dark:border-gray-600 dark:bg-opacity-20">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-bold ${textColor} dark:text-white">${bmi} - ${category}</span>
                        </div>

                        <!-- BMI Visual Bar -->
                        <div class="relative">
                            <div class="w-full h-6 bg-gray-200 rounded-full overflow-hidden dark:bg-gray-700">
                                <!-- BMI scale background -->
                                <div class="h-full flex">
                                    <div class="bg-blue-300 flex-1 dark:text-activity-note-text"></div>
                                    <div class="bg-green-300 flex-1 dark:bg-green-600"></div>
                                    <div class="bg-yellow-300 flex-1 dark:bg-yellow-600"></div>
                                    <div class="bg-red-300 flex-1 dark:bg-red-600"></div>
                                </div>
                            </div>

                            <!-- BMI indicator -->
                            <div class="absolute top-0 h-6 w-1 ${barColor} rounded-full transform -translate-x-1/2"
                                 style="left: ${position}%;">
                            </div>
                        </div>

                        <!-- BMI scale labels -->
                        <div class="flex justify-between text-xs text-gray-500 mt-1 dark:text-gray-400">
                            <span>15</span>
                            <span>18.5</span>
                            <span>25</span>
                            <span>30</span>
                            <span>40</span>
                        </div>
                    </div>
                `;
            } else {
                bmiDisplay.innerHTML = '';
            }
        }

        // Initialize comment fields visibility on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Get all radio buttons with value="1" (Ja options)
            const yesRadios = document.querySelectorAll('input[type="radio"][value="1"]');

            yesRadios.forEach(function(radio) {
                if (radio.checked) {
                    toggleCommentField(radio.name, true);
                }
            });
        });
    </script>
    @endPushOnce
</x-admin::layouts>
