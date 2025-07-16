{!! view_render_event('admin.leads.view.attributes.before', ['lead' => $lead]) !!}

<div class="flex w-full flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-800">
    <x-admin::accordion class="select-none !border-none">
        <x-slot:header class="!p-0">
            <div class="flex w-full items-center justify-between gap-4 font-semibold dark:text-white">
                <h4>@lang('admin::app.leads.view.attributes.title')</h4>

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

            <!-- Explicit fields: first_name, last_name, maiden_name -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">@lang('Voornaam')</label>
                    <div class="mt-1 text-gray-900 dark:text-gray-100">{{ $lead->first_name ?? '-' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">@lang('Achternaam')</label>
                    <div class="mt-1 text-gray-900 dark:text-gray-100">{{ $lead->last_name ?? '-' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">@lang('Aangetrouwde naam')</label>
                    <div class="mt-1 text-gray-900 dark:text-gray-100">{{ $lead->maiden_name ?? '-' }}</div>
                </div>
            </div>

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

            <div class="text-xs text-gray-500 mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-1">
                <div class="flex items-center gap-1">
                    <span class="inline-block w-24">@lang('Aangemaakt'):</span>
                    <span class="font-mono tabular-nums w-32">{{ $lead->created_at ? $lead->created_at->format('d-m-Y H:i') : '-' }}</span>
                    <span class="text-gray-400 truncate">{{ $lead->createdBy?->name ?? '' }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="inline-block w-24">@lang('Gewijzigd'):</span>
                    <span class="font-mono tabular-nums w-32">{{ $lead->updated_at ? $lead->updated_at->format('d-m-Y H:i') : '-' }}</span>
                    <span class="text-gray-400 truncate">{{ $lead->updatedBy?->name ? ' ' . $lead->updatedBy->name : '' }}</span>
                </div>
            </div>

            {!! view_render_event('admin.leads.view.attributes.form_controls.after', ['lead' => $lead]) !!}
        </x-slot>
    </x-admin::accordion>
</div>

{!! view_render_event('admin.leads.view.attributes.before', ['lead' => $lead]) !!}
