{!! view_render_event('admin.leads.organization.before') !!}

<!-- Lead Organization Section -->
<x-admin::form.control-group>
    <x-admin::form.control-group.label>
        @lang('admin::app.leads.common.organization.title')
    </x-admin::form.control-group.label>

    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
        Koppel een organisatie voor facturatie doeleinden (optioneel)
    </p>

    <x-admin::lookup
        src="{{ route('admin.contacts.organizations.search') }}"
        name="organization_id"
        label="Naam"
        value="{{ json_encode($organization) }}"
        placeholder="Zoek organisatie..."
        :can-add-new="true"
    />

    <x-admin::form.control-group.error control-name="organization_id" />
</x-admin::form.control-group>

{!! view_render_event('admin.leads.organization.after') !!}