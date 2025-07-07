{!! view_render_event('admin.contacts.persons.view.attributes.before', ['person' => $person]) !!}

<div class="flex w-full flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-800">
    <x-admin::accordion class="select-none !border-none">
        <x-slot:header class="!p-0">
            <h4 class="font-semibold dark:text-white">
                @lang('admin::app.contacts.persons.view.about-person')
            </h4>
        </x-slot>

        <x-slot:content class="mt-4 !px-0 !pb-0">
            {!! view_render_event('admin.contacts.persons.view.attributes.form_controls.before', ['person' => $person]) !!}

            <!-- Explicit fields: first_name, last_name, maiden_name -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">@lang('Voornaam')</label>
                    <div class="mt-1 text-gray-900 dark:text-gray-100">{{ $person->first_name ?? '-' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">@lang('Achternaam')</label>
                    <div class="mt-1 text-gray-900 dark:text-gray-100">{{ $person->last_name ?? '-' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">@lang('Meisjesnaam')</label>
                    <div class="mt-1 text-gray-900 dark:text-gray-100">{{ $person->maiden_name ?? '-' }}</div>
                </div>
            </div>

            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="modalForm"
            >
                <form @submit="handleSubmit($event, () => {})">
                    {!! view_render_event('admin.contacts.persons.view.attributes.form_controls.attributes_view.before', ['person' => $person]) !!}

                    <x-admin::attributes.view
                        :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                            'entity_type' => 'persons',
                            ['code', 'NOTIN', ['name', 'jon_title']]
                        ])"
                        :entity="$person"
                        :url="route('admin.contacts.persons.update', $person->id)"
                        :allow-edit="true"
                    />

                    {!! view_render_event('admin.contacts.persons.view.attributes.form_controls.attributes_view.after', ['person' => $person]) !!}
                </form>
            </x-admin::form>

            {!! view_render_event('admin.contacts.persons.view.attributes.form_controls.after', ['person' => $person]) !!}
        </x-slot>
    </x-admin::accordion>
</div>

{!! view_render_event('admin.contacts.persons.view.attributes.before', ['person' => $person]) !!}
