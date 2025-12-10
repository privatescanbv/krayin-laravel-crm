@props([
    'type',
    'currentPipelineId'
])

@php
    if ($type == 'sales') {
        $routeNameIndex = 'admin.sales-leads.index';
        $cols = 2;
        $pipelines = app('Webkul\Lead\Repositories\PipelineRepository')->getWorkflowPipelines();
    } else {
        $routeNameIndex = 'admin.leads.index';
        $cols = 3;
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

    <!-- LEFT: NAV BAR -->
    <nav class="pipeline-nav flex-shrink-0 border border-gray-200 bg-white rounded-md px-1 h-11 flex items-center">
        <div class="flex items-center space-x-[2px]">
            @foreach ($pipelines as $tempPipeline)
                @php $active = $currentPipelineId == $tempPipeline->id; @endphp

                <a
                    href="{{ route($routeNameIndex, ['pipeline_id' => $tempPipeline->id, 'view_type' => request('view_type')]) }}"
                    class="
                        h-9 px-4 flex items-center rounded-md text-sm font-medium transition
                        {{ $active
                            ? 'active bg-brand-privatescan-main text-brand-privatescan-accent shadow-sm'
                            : 'text-brand-privatescan-main hover:bg-[#e8f0f9]'
                        }}
                    "
                >
                    {{ $tempPipeline->name }}
                </a>
            @endforeach
        </div>
    </nav>

    <!-- RIGHT BUTTON -->
    @if ($type == 'leads' && bouncer()->hasPermission('leads.create'))
        <a
            href="{{ route('admin.leads.create') }}{{ request('pipeline_id') ? '?pipeline_id=' . request('pipeline_id') : '' }}"
            class="primary-button h-11 flex items-center whitespace-nowrap ml-auto"
        >
            @lang('admin::app.leads.index.create-btn')
        </a>
    @endif
</div>

