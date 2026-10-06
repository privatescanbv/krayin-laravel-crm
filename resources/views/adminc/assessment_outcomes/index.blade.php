<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.assessment_outcomes.index.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs name="settings.assessment_outcomes" />

                <div class="text-xl font-bold dark:text-gray-300">
                    @lang('admin::app.settings.assessment_outcomes.index.title')
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                @if (bouncer()->hasPermission('settings.lead.assessment_outcomes.create'))
                    <a href="{{ route('admin.settings.assessment_outcomes.create') }}" class="primary-button">
                        @lang('admin::app.settings.assessment_outcomes.index.create-btn')
                    </a>
                @endif
            </div>
        </div>

        <v-assessment-outcomes></v-assessment-outcomes>
    </div>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-assessment-outcomes-template">
            <div class="box-shadow rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
                <p class="px-4 pt-4 text-sm text-gray-600 dark:text-gray-400" v-if="canEdit">
                    @lang('admin::app.settings.assessment_outcomes.index.reorder-hint')
                </p>

                <table class="w-full text-left text-sm dark:text-gray-300">
                    <thead class="border-b text-gray-600 dark:border-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="w-10 p-4"></th>
                            <th class="p-4">@lang('admin::app.settings.assessment_outcomes.index.datagrid.label')</th>
                            <th class="p-4">@lang('admin::app.settings.assessment_outcomes.index.datagrid.code')</th>
                            <th class="p-4">@lang('admin::app.settings.assessment_outcomes.index.datagrid.is_surgery_advice')</th>
                            <th class="p-4 text-right">@lang('admin::app.settings.assessment_outcomes.index.actions')</th>
                        </tr>
                    </thead>

                    <draggable
                        tag="tbody"
                        ghost-class="draggable-ghost"
                        handle=".icon-move"
                        item-key="id"
                        v-bind="{ animation: 200 }"
                        :list="outcomes"
                        :disabled="! canEdit"
                        @end="saveOrder"
                    >
                        <template #item="{ element }">
                            <tr class="border-b hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-950">
                                <td class="p-4">
                                    <i v-if="canEdit" class="icon-move cursor-grab text-2xl"></i>
                                </td>
                                <td class="p-4 font-medium">@{{ element.label }}</td>
                                <td class="p-4"><code>@{{ element.code }}</code></td>
                                <td class="p-4">@{{ element.is_surgery_advice ? 'Ja' : 'Nee' }}</td>
                                <td class="p-4 text-right">
                                    <a
                                        v-if="canEdit"
                                        :href="editUrl.replace(':id', element.id)"
                                        class="icon-edit cursor-pointer rounded-md p-1.5 text-2xl hover:bg-gray-200 dark:hover:bg-gray-800"
                                        title="@lang('admin::app.settings.assessment_outcomes.index.datagrid.edit')"
                                    ></a>
                                    <span
                                        v-if="canDelete"
                                        class="icon-delete cursor-pointer rounded-md p-1.5 text-2xl hover:bg-gray-200 dark:hover:bg-gray-800"
                                        title="@lang('admin::app.settings.assessment_outcomes.index.datagrid.delete')"
                                        @click="remove(element)"
                                    ></span>
                                </td>
                            </tr>
                        </template>
                    </draggable>
                </table>
            </div>
        </script>

        <script type="module">
            app.component('v-assessment-outcomes', {
                template: '#v-assessment-outcomes-template',

                data() {
                    return {
                        outcomes: @json($outcomes),
                        canEdit: @json(bouncer()->hasPermission('settings.lead.assessment_outcomes.edit')),
                        canDelete: @json(bouncer()->hasPermission('settings.lead.assessment_outcomes.delete')),
                        editUrl: "{{ route('admin.settings.assessment_outcomes.edit', ':id') }}",
                        deleteUrl: "{{ route('admin.settings.assessment_outcomes.delete', ':id') }}",
                    };
                },

                methods: {
                    saveOrder() {
                        this.$axios.put("{{ route('admin.settings.assessment_outcomes.reorder') }}", {
                            ids: this.outcomes.map(outcome => outcome.id),
                        })
                            .then(response => this.$emitter.emit('add-flash', { type: 'success', message: response.data.message }))
                            .catch(error => this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message }));
                    },

                    remove(outcome) {
                        this.$emitter.emit('open-confirm-modal', {
                            agree: () => {
                                this.$axios.delete(this.deleteUrl.replace(':id', outcome.id))
                                    .then(response => {
                                        this.outcomes = this.outcomes.filter(item => item.id !== outcome.id);

                                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                                    })
                                    .catch(error => this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message }));
                            },
                        });
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
