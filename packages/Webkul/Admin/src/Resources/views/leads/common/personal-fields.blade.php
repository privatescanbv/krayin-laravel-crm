{!! view_render_event('admin.leads.create.personal_fields.form_controls.before') !!}

<div class="flex flex-col gap-4">
    <!-- Salutation -->

    <div class="flex gap-4">
        <x-admin::form.control-group class="w-40">
            <x-admin::form.control-group.label>
                Aanhef
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="select"
                name="salutation"
                :value="$entity->salutation ?? ''"
                :label="trans('Aanhef')"
            >
                <option value="">Selecteer aanhef</option>
                <option value="Dhr." {{ ($entity->salutation ?? '') == 'Dhr.' ? 'selected' : '' }}>Dhr.</option>
                <option value="Mevr." {{ ($entity->salutation ?? '') == 'Mevr.' ? 'selected' : '' }}>Mevr.</option>
            </x-admin::form.control-group.control>

            <x-admin::form.control-group.error control-name="salutation"/>
        </x-admin::form.control-group>
    </div>

    <!-- Initials and First Name Row -->
    <div class="flex gap-4">
        <!-- Initials -->
        <x-admin::form.control-group class="w-20">
            <x-admin::form.control-group.label>
                Initialen
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="text"
                name="initials"
                :value="$entity->initials ?? ''"
                :label="trans('Initialen')"
                placeholder="J.A."
            />

            <x-admin::form.control-group.error control-name="initials"/>
        </x-admin::form.control-group>

        <!-- First Name -->
        <x-admin::form.control-group class="flex-1">
            <x-admin::form.control-group.label>
                Voornaam
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="text"
                name="first_name"
                :value="$entity->first_name ?? ''"
                :label="trans('Voornaam')"
                placeholder="Voornaam"
            />

            <x-admin::form.control-group.error control-name="first_name"/>
        </x-admin::form.control-group>
    </div>

    <!-- Last Name Row -->
    <div class="flex gap-4">
        <!-- Last Name Prefix -->
        <x-admin::form.control-group class="w-25">
            <x-admin::form.control-group.label>
                Tussenvoegsel
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="text"
                name="lastname_prefix"
                :value="$entity->lastname_prefix ?? ''"
                :label="trans('Tussenvoegsel')"
                placeholder="van, de, den, etc."
                class="w-24"
            />

            <x-admin::form.control-group.error control-name="lastname_prefix"/>
        </x-admin::form.control-group>

        <!-- Last Name -->
        <x-admin::form.control-group class="flex-1">
            <x-admin::form.control-group.label>
                Achternaam bij geboorte
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="text"
                name="last_name"
                :value="$entity->last_name ?? ''"
                :label="trans('Achternaam')"
                placeholder="Achternaam"
            />

            <x-admin::form.control-group.error control-name="last_name"/>
        </x-admin::form.control-group>
    </div>


    <!-- Married Name Row -->
    <div class="flex gap-4">
        <!-- Married Name Prefix -->
        <x-admin::form.control-group class="w-25">
            <x-admin::form.control-group.label>
                Tussenvoegsel
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="text"
                name="married_name_prefix"
                :value="$entity->married_name_prefix ?? ''"
                :label="trans('Married name prefix')"
                placeholder="van, de, den, etc."
                class="w-24"
            />

            <x-admin::form.control-group.error control-name="married_name_prefix"/>
        </x-admin::form.control-group>

        <!-- Married Name -->
        <x-admin::form.control-group class="flex-1">
            <x-admin::form.control-group.label>
                Aangetrouwde naam
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="text"
                name="married_name"
                :value="$entity->married_name ?? ''"
                :label="trans('Married name')"
            />

            <x-admin::form.control-group.error control-name="married_name"/>
        </x-admin::form.control-group>
    </div>


    <!-- Date of Birth -->
    <x-admin::form.control-group>
        <x-admin::form.control-group.label>
            Geboortedatum
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="date"
            name="date_of_birth"
            :value="$entity && $entity->date_of_birth ? $entity->date_of_birth->format('d-m-Y') : ''"
            :label="trans('Geboortedatum')"
        />

        <x-admin::form.control-group.error control-name="date_of_birth"/>
    </x-admin::form.control-group>

    <!-- Gender -->
    <x-admin::form.control-group>
        <x-admin::form.control-group.label>
            Geslacht
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="select"
            name="gender"
            :value="$entity->gender ?? ''"
            :label="trans('Geslacht')"
        >
            <option value="">Selecteer geslacht</option>
            <option value="Man" {{ ($entity->gender ?? '') == 'Man' ? 'selected' : '' }}>Man</option>
            <option value="Vrouw" {{ ($entity->gender ?? '') == 'Vrouw' ? 'selected' : '' }}>Vrouw</option>
            <option value="Anders" {{ ($entity->gender ?? '') == 'Anders' ? 'selected' : '' }}>Anders</option>
        </x-admin::form.control-group.control>

        <x-admin::form.control-group.error control-name="gender"/>
    </x-admin::form.control-group>
</div>

{!! view_render_event('admin.leads.create.personal_fields.form_controls.after') !!}
