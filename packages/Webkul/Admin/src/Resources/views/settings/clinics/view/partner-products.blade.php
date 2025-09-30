<div class="p-4">
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="mb-4 flex items-center justify-between">
            <h4 class="text-lg font-semibold dark:text-white">
                @lang('admin::app.settings.clinics.view.partner-products.title')
            </h4>
            <span class="text-sm text-gray-600 dark:text-gray-400">
                @lang('admin::app.settings.clinics.view.partner-products.total'): {{ $clinic->partnerProducts->count() }}
            </span>
        </div>

        @if ($clinic->partnerProducts->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <th class="p-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">
                                @lang('admin::app.settings.clinics.view.partner-products.table.name')
                            </th>
                            <th class="p-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">
                                @lang('admin::app.settings.clinics.view.partner-products.table.partner-name')
                            </th>
                            <th class="p-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">
                                @lang('admin::app.settings.clinics.view.partner-products.table.price')
                            </th>
                            <th class="p-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">
                                @lang('admin::app.settings.clinics.view.partner-products.table.status')
                            </th>
                            <th class="p-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">
                                @lang('admin::app.settings.clinics.view.partner-products.table.actions')
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clinic->partnerProducts as $partnerProduct)
                            <tr class="border-b border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="p-2 dark:text-white">
                                    {{ $partnerProduct->name }}
                                </td>
                                <td class="p-2 dark:text-white">
                                    {{ $partnerProduct->partner_name ?? '-' }}
                                </td>
                                <td class="p-2 dark:text-white">
                                    @if ($partnerProduct->sales_price)
                                        {{ $partnerProduct->currency ?? '€' }} {{ number_format($partnerProduct->sales_price, 2) }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="p-2">
                                    @if ($partnerProduct->active)
                                        <span class="rounded bg-green-100 px-2 py-1 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200">
                                            @lang('admin::app.settings.clinics.view.partner-products.table.active')
                                        </span>
                                    @else
                                        <span class="rounded bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800 dark:bg-gray-900 dark:text-gray-200">
                                            @lang('admin::app.settings.clinics.view.partner-products.table.inactive')
                                        </span>
                                    @endif
                                </td>
                                <td class="p-2">
                                    @if (bouncer()->hasPermission('settings.partner_products.view'))
                                        <a
                                            href="{{ route('admin.settings.partner_products.view', $partnerProduct->id) }}"
                                            class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                            title="@lang('admin::app.settings.clinics.view.partner-products.table.view')"
                                        >
                                            <i class="icon-eye text-lg"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="py-8 text-center">
                <p class="text-gray-600 dark:text-gray-400">
                    @lang('admin::app.settings.clinics.view.partner-products.no-products')
                </p>
            </div>
        @endif
    </div>
</div>