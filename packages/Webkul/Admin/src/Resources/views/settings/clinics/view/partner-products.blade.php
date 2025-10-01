<div class="p-4">
    <x-admin::datagrid :src="route('admin.settings.clinics.partner_products.index', $clinic->id)">
        <template #body="{
            available,
            applied,
            isLoading,
            selectAll,
            sort,
            performAction
        }">
            <template v-if="isLoading">
                <x-admin::shimmer.datagrid />
            </template>

            <template v-else>
                <div v-if="available.records.length">
                    <!-- Datagrid Table -->
                    <x-admin::datagrid.table>
                        <template #header="{
                            isLoading,
                            available,
                            applied,
                            selectAll,
                            sort,
                            performAction
                        }">
                            <tr>
                                <template v-for="column in available.columns">
                                    <th
                                        class="cursor-pointer select-none px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300"
                                        :class="column.sortable ? 'cursor-pointer' : 'cursor-default'"
                                        @click="sort(column)"
                                        v-if="column.index !== 'id'"
                                    >
                                        <span v-html="column.label"></span>
                                        <i
                                            class="align-middle text-base text-gray-800 dark:text-white ltr:ml-1.5 rtl:mr-1.5"
                                            :class="[applied.sort.column === column.index ? (applied.sort.order === 'asc' ? 'icon-down-stat' : 'icon-up-stat') : 'icon-sort']"
                                            v-if="column.sortable"
                                        ></i>
                                    </th>
                                </template>

                                <!-- Actions -->
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300" v-if="available.actions.length">
                                    @lang('admin::app.components.datagrid.table.actions')
                                </th>
                            </tr>
                        </template>

                        <template #body="{
                            isLoading,
                            available,
                            applied,
                            selectAll,
                            sort,
                            performAction
                        }">
                            <tr
                                class="hover:bg-gray-50 dark:hover:bg-gray-950"
                                v-for="record in available.records"
                            >
                                <template v-for="column in available.columns">
                                    <td
                                        class="px-4 py-3 dark:text-white"
                                        v-if="column.index !== 'id'"
                                        v-html="record[column.index]"
                                    >
                                    </td>
                                </template>

                                <!-- Actions -->
                                <td class="px-4 py-3" v-if="available.actions.length">
                                    <div class="flex gap-2.5">
                                        <div v-for="action in available.actions">
                                            <a
                                                :href="action.url.replace(':id', record.id || record.lead_id)"
                                                v-if="action.index === 'view'"
                                            >
                                                <span
                                                    :class="action.icon"
                                                    class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950 max-sm:place-self-center"
                                                    :title="action.title"
                                                ></span>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </x-admin::datagrid.table>
                </div>

                <!-- Empty State -->
                <div v-else class="py-16 text-center">
                    <img
                        class="m-auto h-[120px] w-[120px] dark:mix-blend-exclusion dark:invert"
                        src="{{ vite()->asset('images/empty-placeholders/products.svg') }}"
                        alt="@lang('admin::app.settings.clinics.view.partner-products.no-products')"
                    />

                    <p class="mt-4 text-base text-gray-600 dark:text-gray-300">
                        @lang('admin::app.settings.clinics.view.partner-products.no-products')
                    </p>
                </div>
            </template>
        </template>
    </x-admin::datagrid>
</div>
