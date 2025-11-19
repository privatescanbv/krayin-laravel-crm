<x-admin::layouts>
    <x-slot:title>
        @lang ($partner_product->name)
    </x-slot>

    <div class="flex gap-4 max-lg:flex-wrap">
        <div class="max-lg:min-w-full max-lg:max-w-full [&>div:last-child]:border-b-0 lg:sticky lg:top-[73px] flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                <div class="flex items-center justify-between">
                    <x-admin::breadcrumbs name="partner_products.view" :entity="$partner_product" />
                </div>

                <div class="mb-2 flex flex-col gap-0.5">
                    <h3 class="break-words text-lg font-bold dark:text-white">
                        {{ $partner_product->name }}
                    </h3>
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-admin::activities.actions.note :entity="$partner_product" entity-control-name="partner_product_id" />
                    <x-admin::activities.actions.file :entity="$partner_product" entity-control-name="partner_product_id" />
                </div>
            </div>

            <div class="flex flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="text-gray-600 dark:text-gray-400">@lang('admin::app.partner_products.index.create.currency')</div>
                    <div class="dark:text-white">{{ $partner_product->currency }}</div>

                    <div class="text-gray-600 dark:text-gray-400">@lang('admin::app.partner_products.index.create.sales_price')</div>
                    <div class="dark:text-white">{{ number_format($partner_product->sales_price, 2, ',', '.') }}</div>

                    @if ($partner_product->related_sales_price > 0)
                        <div class="text-gray-600 dark:text-gray-400">@lang('admin::app.partner_products.index.create.related_sales_price')</div>
                        <div class="dark:text-white">{{ number_format($partner_product->related_sales_price, 2, ',', '.') }}</div>
                    @endif

                    <div class="text-gray-600 dark:text-gray-400">@lang('admin::app.partner_products.index.create.active')</div>
                    <div class="dark:text-white">{{ $partner_product->active ? trans('admin::app.common.yes') : trans('admin::app.common.no') }}</div>

                    <div class="text-gray-600 dark:text-gray-400">@lang('admin::app.partner_products.index.create.resource_type')</div>
                    <div class="dark:text-white">{{ optional($partner_product->resourceType)->name }}</div>
                </div>

                @if ($partner_product->description)
                    <div class="mt-2">
                        <div class="text-gray-600 dark:text-gray-400">@lang('admin::app.partner_products.index.create.description')</div>
                        <div class="dark:text-white">{{ $partner_product->description }}</div>
                    </div>
                @endif

                @if ($partner_product->clinic_description)
                    <div class="mt-2">
                        <div class="text-gray-600 dark:text-gray-400">@lang('admin::app.partner_products.index.create.clinic_description')</div>
                        <div class="dark:text-white">{{ $partner_product->clinic_description }}</div>
                    </div>
                @endif

                @if ($partner_product->duration)
                    <div class="mt-2">
                        <div class="text-gray-600 dark:text-gray-400">@lang('admin::app.partner_products.index.create.duration')</div>
                        <div class="dark:text-white">{{ $partner_product->duration }} min</div>
                    </div>
                @endif

                @php
                    $reportingLabels = $partner_product->getReportingLabels();
                @endphp
                @if (!empty($reportingLabels))
                    <div class="mt-2">
                        <div class="text-gray-600 dark:text-gray-400">@lang('admin::app.partner_products.index.create.reporting')</div>
                        <div class="grid grid-cols-1 gap-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            @foreach ($reportingLabels as $label)
                                <span class="inline-block rounded bg-blue-100 px-2 py-1 text-xs text-activity-task-text dark:bg-blue-900 dark:text-blue-200">{{ $label }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            @if ($partner_product->relatedProducts->count() > 0)
                <div class="flex flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                    <h4 class="font-semibold text-gray-800 dark:text-white">
                        @lang('admin::app.partner_products.index.create.related_products')
                    </h4>

                    <div class="flex flex-col gap-2">
                        @foreach ($partner_product->relatedProducts as $relatedProduct)
                            <a
                                href="{{ route('admin.partner_products.view', $relatedProduct->id) }}"
                                class="flex items-center justify-between rounded-lg border border-gray-200 p-3 text-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                            >
                                <div class="flex flex-col gap-1">
                                    <span class="font-medium text-gray-800 dark:text-white">{{ $relatedProduct->name }}</span>
                                </div>
                                <div class="text-right">
                                    <div class="font-semibold text-gray-800 dark:text-white">€ {{ number_format($relatedProduct->sales_price, 2, ',', '.') }}</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="flex w-full flex-col gap-4 rounded-lg">
            <x-admin::activities
                :endpoint="route('admin.partner_products.activities.index', $partner_product->id)"
                :types="[
                    ['name' => 'all', 'label' => trans('admin::app.products.view.all')],
                    ['name' => 'note', 'label' => trans('admin::app.products.view.notes')],
                    ['name' => 'file', 'label' => trans('admin::app.products.view.files')],
                    ['name' => 'system', 'label' => trans('admin::app.products.view.change-logs')],
                ]"
            />
        </div>
    </div>
</x-admin::layouts>

