{!! view_render_event('admin.workflow-leads.index.view_switcher.before') !!}

<div class="flex items-center gap-4 max-md:w-full max-md:!justify-between">
    <x-admin::dropdown>
        <x-slot:toggle>
            {!! view_render_event('admin.workflow-leads.index.view_switcher.pipeline.button.before') !!}

            <button
                type="button"
                class="flex cursor-pointer appearance-none items-center justify-between gap-x-2 rounded-md border bg-white px-2.5 py-[7px] text-center leading-6 text-gray-600 transition-all marker:shadow hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
            >
                <span class="whitespace-nowrap">
                    {{ $pipeline->name }}
                </span>

                <span class="icon-down-arrow text-2xl"></span>
            </button>

            {!! view_render_event('admin.workflow-leads.index.view_switcher.pipeline.button.after') !!}
        </x-slot>

        <x-slot:content class="!p-0">
            {!! view_render_event('admin.workflow-leads.index.view_switcher.pipeline.content.header.before') !!}

            {!! view_render_event('admin.workflow-leads.index.view_switcher.pipeline.content.header.after') !!}

            <!-- Pipeline Links -->
            @foreach (app('Webkul\Lead\Repositories\PipelineRepository')->getWorkflowPipelines() as $tempPipeline)
                {!! view_render_event('admin.workflow-leads.index.view_switcher.pipeline.content.before', ['tempPipeline' => $tempPipeline]) !!}

                <a
                    href="{{ route('admin.workflow-leads.index', [
                        'pipeline_id' => $tempPipeline->id,
                        'view_type'   => request('view_type')
                    ]) }}"
                    class="block px-3 py-2.5 pl-4 text-gray-600 transition-all hover:bg-gray-100 dark:hover:bg-gray-950 dark:text-gray-300 {{ $pipeline->id == $tempPipeline->id ? 'bg-gray-100 dark:bg-gray-950' : '' }}"
                >
                    {{ $tempPipeline->name }}
                </a>

                {!! view_render_event('admin.workflow-leads.index.view_switcher.pipeline.content.after', ['tempPipeline' => $tempPipeline]) !!}
            @endforeach

            {!! view_render_event('admin.workflow-leads.index.view_switcher.pipeline.content.footer.before') !!}

            <!-- Footer -->
            <a
                href="{{ route('admin.settings.pipelines.create') }}"
                target="_blank"
                class="flex items-center justify-between border-t border-gray-300 px-3 py-2.5 text-brandColor dark:border-gray-800"
            >
                <span class="font-medium">
                    Create New Pipeline
                </span>
            </a>

            {!! view_render_event('admin.workflow-leads.index.view_switcher.pipeline.content.footer.after') !!}
        </x-slot>
    </x-admin::dropdown>

    <div class="flex items-center gap-0.5">
        {!! view_render_event('admin.workflow-leads.index.view_switcher.pipeline.view_type.before') !!}

        @if (request('view_type'))
            <a
                class="flex"
                href="{{ route('admin.workflow-leads.index', ['pipeline_id' => request('pipeline_id')]) }}"
            >
                <span class="icon-kanban p-2 text-2xl"></span>
            </a>

            <span class="icon-list rounded-md bg-gray-100 p-2 text-2xl dark:bg-gray-950"></span>
        @else
            <span class="icon-kanban rounded-md bg-white p-2 text-2xl dark:bg-gray-900"></span>

            <a
                href="{{ route('admin.workflow-leads.index', [
                    'view_type' => 'table',
                    'pipeline_id' => request('pipeline_id')
                ]) }}"
                class="flex"
            >
                <span class="icon-list p-2 text-2xl"></span>
            </a>
        @endif

        {!! view_render_event('admin.workflow-leads.index.view_switcher.pipeline.view_type.after') !!}
    </div>
</div>

{!! view_render_event('admin.workflow-leads.index.view_switcher.after') !!}
