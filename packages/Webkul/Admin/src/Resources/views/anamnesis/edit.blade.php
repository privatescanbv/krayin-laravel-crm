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
                        
                        <div class="space-y-6">
                            <!-- Metalen -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Metalen
                                    </x-admin::form.control-group.label>
                                    
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="metalen" value="1" 
                                                   {{ $anamnesis->metalen == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('metalen', this.checked)"
                                                   class="mr-2 {{ $errors->has('metalen') ? 'border-red-500' : '' }}">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="metalen" value="0" 
                                                   {{ $anamnesis->metalen == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('metalen', false)"
                                                   class="mr-2 {{ $errors->has('metalen') ? 'border-red-500' : '' }}">
                                            Nee
                                        </label>
                                    </div>
                                    
                                    @error('metalen')
                                        <p class="mt-1 text-xs italic text-red-600">{{ $message }}</p>
                                    @enderror
                                </x-admin::form.control-group>
                                
                                <div id="metalen_comment" class="mt-2" style="display: {{ $anamnesis->metalen == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_metalen_c"
                                        :value="$anamnesis->opm_metalen_c"
                                        placeholder="Toelichting metalen"
                                    />
                                </div>
                            </div>

                            <!-- Medicijnen -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Medicijnen
                                    </x-admin::form.control-group.label>
                                    
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="medicijnen" value="1" 
                                                   {{ $anamnesis->medicijnen == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('medicijnen', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="medicijnen" value="0" 
                                                   {{ $anamnesis->medicijnen == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('medicijnen', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                </x-admin::form.control-group>
                                
                                <div id="medicijnen_comment" class="mt-2" style="display: {{ $anamnesis->medicijnen == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_medicijnen_c"
                                        :value="$anamnesis->opm_medicijnen_c"
                                        placeholder="Toelichting medicijnen"
                                    />
                                </div>
                            </div>

                            <!-- Glaucoom -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Glaucoom
                                    </x-admin::form.control-group.label>
                                    
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="glaucoom" value="1" 
                                                   {{ $anamnesis->glaucoom == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('glaucoom', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="glaucoom" value="0" 
                                                   {{ $anamnesis->glaucoom == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('glaucoom', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                </x-admin::form.control-group>
                                
                                <div id="glaucoom_comment" class="mt-2" style="display: {{ $anamnesis->glaucoom == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_glaucoom_c"
                                        :value="$anamnesis->opm_glaucoom_c"
                                        placeholder="Toelichting glaucoom"
                                    />
                                </div>
                            </div>

                            <!-- Claustrofobie -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Claustrofobie
                                    </x-admin::form.control-group.label>
                                    
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="claustrofobie" value="1" 
                                                   {{ $anamnesis->claustrofobie == 1 ? 'checked' : '' }}
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="claustrofobie" value="0" 
                                                   {{ $anamnesis->claustrofobie == 0 ? 'checked' : '' }}
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                </x-admin::form.control-group>
                            </div>

                            <!-- Dormicum -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Dormicum
                                    </x-admin::form.control-group.label>
                                    
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
                                </x-admin::form.control-group>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Panel -->
                <div class="flex flex-1 flex-col gap-4 max-lg:flex-auto">
                    <!-- Medical History -->
                    <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="mb-4 text-lg font-semibold dark:text-white">Medische geschiedenis</h2>
                        
                        <div class="space-y-6">
                            <!-- Hart operatie -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Hart operatie
                                    </x-admin::form.control-group.label>
                                    
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="hart_operatie_c" value="1" 
                                                   {{ $anamnesis->hart_operatie_c == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('hart_operatie_c', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="hart_operatie_c" value="0" 
                                                   {{ $anamnesis->hart_operatie_c == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('hart_operatie_c', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                </x-admin::form.control-group>
                                
                                <div id="hart_operatie_c_comment" class="mt-2" style="display: {{ $anamnesis->hart_operatie_c == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_hart_operatie_c"
                                        :value="$anamnesis->opm_hart_operatie_c"
                                        placeholder="Toelichting hart operatie"
                                    />
                                </div>
                            </div>

                            <!-- Implantaat -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Implantaat
                                    </x-admin::form.control-group.label>
                                    
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="implantaat_c" value="1" 
                                                   {{ $anamnesis->implantaat_c == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('implantaat_c', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="implantaat_c" value="0" 
                                                   {{ $anamnesis->implantaat_c == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('implantaat_c', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                </x-admin::form.control-group>
                                
                                <div id="implantaat_c_comment" class="mt-2" style="display: {{ $anamnesis->implantaat_c == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_implantaat_c"
                                        :value="$anamnesis->opm_implantaat_c"
                                        placeholder="Toelichting implantaat"
                                    />
                                </div>
                            </div>

                            <!-- Operaties -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Operaties
                                    </x-admin::form.control-group.label>
                                    
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="operaties_c" value="1" 
                                                   {{ $anamnesis->operaties_c == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('operaties_c', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="operaties_c" value="0" 
                                                   {{ $anamnesis->operaties_c == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('operaties_c', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                </x-admin::form.control-group>
                                
                                <div id="operaties_c_comment" class="mt-2" style="display: {{ $anamnesis->operaties_c == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_operaties_c"
                                        :value="$anamnesis->opm_operaties_c"
                                        placeholder="Toelichting operaties"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hereditary Conditions -->
                    <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="mb-4 text-lg font-semibold dark:text-white">Erfelijke aandoeningen</h2>
                        
                        <div class="space-y-6">
                            <!-- Hart erfelijk -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Hart erfelijk
                                    </x-admin::form.control-group.label>
                                    
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="hart_erfelijk" value="1" 
                                                   {{ $anamnesis->hart_erfelijk == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('hart_erfelijk', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="hart_erfelijk" value="0" 
                                                   {{ $anamnesis->hart_erfelijk == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('hart_erfelijk', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                </x-admin::form.control-group>
                                
                                <div id="hart_erfelijk_comment" class="mt-2" style="display: {{ $anamnesis->hart_erfelijk == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_erf_hart_c"
                                        :value="$anamnesis->opm_erf_hart_c"
                                        placeholder="Toelichting hart erfelijk"
                                    />
                                </div>
                            </div>

                            <!-- Vaat erfelijk -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Vaat erfelijk
                                    </x-admin::form.control-group.label>
                                    
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="vaat_erfelijk" value="1" 
                                                   {{ $anamnesis->vaat_erfelijk == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('vaat_erfelijk', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="vaat_erfelijk" value="0" 
                                                   {{ $anamnesis->vaat_erfelijk == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('vaat_erfelijk', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                </x-admin::form.control-group>
                                
                                <div id="vaat_erfelijk_comment" class="mt-2" style="display: {{ $anamnesis->vaat_erfelijk == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_erf_vaat_c"
                                        :value="$anamnesis->opm_erf_vaat_c"
                                        placeholder="Toelichting vaat erfelijk"
                                    />
                                </div>
                            </div>

                            <!-- Tumoren erfelijk -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Tumoren erfelijk
                                    </x-admin::form.control-group.label>
                                    
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="tumoren_erfelijk" value="1" 
                                                   {{ $anamnesis->tumoren_erfelijk == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('tumoren_erfelijk', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="tumoren_erfelijk" value="0" 
                                                   {{ $anamnesis->tumoren_erfelijk == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('tumoren_erfelijk', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                </x-admin::form.control-group>
                                
                                <div id="tumoren_erfelijk_comment" class="mt-2" style="display: {{ $anamnesis->tumoren_erfelijk == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_erf_tumor_c"
                                        :value="$anamnesis->opm_erf_tumor_c"
                                        placeholder="Toelichting tumoren erfelijk"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                                        <!-- Lifestyle -->
                    <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="mb-4 text-lg font-semibold dark:text-white">Levensstijl</h2>
                        
                        <div class="space-y-6">
                            <!-- Roken -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Roken
                                    </x-admin::form.control-group.label>
                                    
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
                                </x-admin::form.control-group>
                                
                                <div id="smoking_comment" class="mt-2" style="display: {{ $anamnesis->smoking == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_roken_c"
                                        :value="$anamnesis->opm_roken_c"
                                        placeholder="Toelichting roken"
                                    />
                                </div>
                            </div>

                            <!-- Diabetes -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Diabetes
                                    </x-admin::form.control-group.label>
                                    
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
                                </x-admin::form.control-group>
                                
                                <div id="diabetes_comment" class="mt-2" style="display: {{ $anamnesis->diabetes == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_diabetes_c"
                                        :value="$anamnesis->opm_diabetes_c"
                                        placeholder="Toelichting diabetes"
                                    />
                                </div>
                            </div>

                            <!-- Actief -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Actief
                                    </x-admin::form.control-group.label>
                                    
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="actief" value="1" 
                                                   {{ $anamnesis->actief == 1 ? 'checked' : '' }}
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="actief" value="0" 
                                                   {{ $anamnesis->actief == 0 ? 'checked' : '' }}
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                </x-admin::form.control-group>
                            </div>

                            <!-- Spijsverteringsklachten -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Spijsverteringsklachten
                                    </x-admin::form.control-group.label>
                                    
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="spijsverteringsklachten" value="1" 
                                                   {{ $anamnesis->spijsverteringsklachten == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('spijsverteringsklachten', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="spijsverteringsklachten" value="0" 
                                                   {{ $anamnesis->spijsverteringsklachten == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('spijsverteringsklachten', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                </x-admin::form.control-group>
                                
                                <div id="spijsverteringsklachten_comment" class="mt-2" style="display: {{ $anamnesis->spijsverteringsklachten == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_spijsvertering_c"
                                        :value="$anamnesis->opm_spijsvertering_c"
                                        placeholder="Toelichting spijsvertering"
                                    />
                                </div>
                            </div>

                            <!-- Allergie -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Allergie
                                    </x-admin::form.control-group.label>
                                    
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="allergie_c" value="1" 
                                                   {{ $anamnesis->allergie_c == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('allergie_c', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="allergie_c" value="0" 
                                                   {{ $anamnesis->allergie_c == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('allergie_c', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                </x-admin::form.control-group>
                                
                                <div id="allergie_c_comment" class="mt-2" style="display: {{ $anamnesis->allergie_c == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_allergie_c"
                                        :value="$anamnesis->opm_allergie_c"
                                        placeholder="Toelichting allergie"
                                    />
                                </div>
                            </div>

                            <!-- Rugklachten -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Rugklachten
                                    </x-admin::form.control-group.label>
                                    
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="rugklachten" value="1" 
                                                   {{ $anamnesis->rugklachten == 1 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('rugklachten', this.checked)"
                                                   class="mr-2">
                                            Ja
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="rugklachten" value="0" 
                                                   {{ $anamnesis->rugklachten == 0 ? 'checked' : '' }}
                                                   onchange="toggleCommentField('rugklachten', false)"
                                                   class="mr-2">
                                            Nee
                                        </label>
                                    </div>
                                </x-admin::form.control-group>
                                
                                <div id="rugklachten_comment" class="mt-2" style="display: {{ $anamnesis->rugklachten == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_rugklachten_c"
                                        :value="$anamnesis->opm_rugklachten_c"
                                        placeholder="Toelichting rugklachten"
                                    />
                                </div>
                            </div>

                            <!-- Hartproblemen -->
                            <div class="space-y-2">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Hartproblemen
                                    </x-admin::form.control-group.label>
                                    
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
                                </x-admin::form.control-group>
                                
                                <div id="heart_problems_comment" class="mt-2" style="display: {{ $anamnesis->heart_problems == 1 ? 'block' : 'none' }}">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="opm_hartklachten_c"
                                        :value="$anamnesis->opm_hartklachten_c"
                                        placeholder="Toelichting hartklachten"
                                    />
                                </div>
                            </div>

                            <!-- Risico hartinfarct -->
                            <div class="space-y-2">
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

    <script>
        function toggleCommentField(fieldName, showField) {
            const commentDiv = document.getElementById(fieldName + '_comment');
            if (commentDiv) {
                commentDiv.style.display = showField ? 'block' : 'none';
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
</x-admin::layouts>