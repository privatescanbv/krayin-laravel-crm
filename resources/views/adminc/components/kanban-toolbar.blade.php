@props([
    'type',
    'currentPipelineId'
])

@php
    if ($type == 'sales') {
        $routeNameIndex = 'admin.sales-leads.index';
        $pipelines = app('Webkul\Lead\Repositories\PipelineRepository')->getWorkflowPipelines();
    } elseif ($type === 'orders') {
        $routeNameIndex = 'admin.orders.index';
        $pipelines = app('Webkul\Lead\Repositories\PipelineRepository')->getPipelinesByType(\App\Enums\PipelineType::ORDER);
    } else {
        $routeNameIndex = 'admin.leads.index';
        $pipelines = app('Webkul\Lead\Repositories\PipelineRepository')->getLeadPipelines();
    }

    $tabs = collect($pipelines)->map(function ($p) use ($routeNameIndex) {
        return [
            'label' => $p->name,
            'href' => route($routeNameIndex, [
                'pipeline_id' => $p->id,
                'view_type'   => request('view_type')
            ]),
            'id' => $p->id,
        ];
    });

@endphp
{{--<div class="flex items-center justify-between rounded-md border bg-white p-2 shadow-xs w-full space-x-2">--}}
<div class="flex items-center rounded-md border bg-white p-2 shadow-xs w-full" style="gap: 0.625rem;">

    <div class="h-6 ml-2">
        @include('adminc::components.vue.wonlost-toggle')
    </div>
    <span class="icon-more text-xl"></span>

    @if ($type !== 'orders')
        <div class="h-6 ml-2">
            @include('adminc::components.vue.duplicates-toggle')
        </div>
    @endif

    <!-- LEFT: NAV BAR -->
    <x-adminc::components.pipeline-nav :tabs="$tabs" :current-id="$currentPipelineId" />

    <!-- RIGHT BUTTON -->
    @if ($type === 'leads' && bouncer()->hasPermission('leads.create'))
        <a
            href="{{ route('admin.leads.create') }}{{ request('pipeline_id') ? '?pipeline_id=' . request('pipeline_id') : '' }}"
            class="primary-button h-11 flex items-center whitespace-nowrap ml-auto"
        >
            @lang('admin::app.leads.index.create-btn')
        </a>
    @endif

    @if ($type === 'orders' && bouncer()->hasPermission('orders.create'))
        <span class="icon-more text-xl"></span>
        <a href="{{ route('admin.orders.payment-overview') }}" class="secondary-button h-11 flex items-center whitespace-nowrap">
            <span class="icon-dollar text-xl"></span>
            Betalingsoverzicht
        </a>
        <a
            href="{{ route('admin.orders.create') }}{{ request('pipeline_id') ? '?pipeline_id=' . request('pipeline_id') : '' }}"
            class="primary-button h-11 flex items-center whitespace-nowrap ml-auto"
        >
            Nieuwe Order
        </a>
    @endif
</div>

