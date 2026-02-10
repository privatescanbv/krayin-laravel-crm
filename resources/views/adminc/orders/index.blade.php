<x-admin::layouts>
    <x-slot:title>
        Orders
    </x-slot>

    <div class="flex items-center justify-between text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 backdrop-blur-md pt-4 sticky top-16 z-10">
        <div class="flex flex-col">
            <x-admin::breadcrumbs name="orders" />

            <div class="text-xl font-bold dark:text-white">
                Orders
            </div>
        </div>

        <div class="flex items-center gap-x-2.5">
            @include('adminc::components.kanban-toolbar', ['type' => 'orders', 'currentPipelineId' => $pipeline->id])
        </div>
    </div>

    <div class="mt-4">
        @include('adminc::orders.index.kanban', ['stages' => $stages, 'pipeline' => $pipeline])
    </div>
</x-admin::layouts>

