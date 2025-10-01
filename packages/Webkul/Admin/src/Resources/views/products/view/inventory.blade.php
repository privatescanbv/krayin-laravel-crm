{!! view_render_event('admin.products.view.inventory.before', ['product' => $product]) !!}

<!-- Inventory feature disabled in this deployment -->
<div class="p-4">
    <div class="rounded-lg border border-gray-200 bg-white p-4 text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
        @lang('admin::app.products.view.inventory.disabled', [], app()->getLocale())
    </div>
</div>

{!! view_render_event('admin.products.view.inventory.after', ['product' => $product]) !!}
