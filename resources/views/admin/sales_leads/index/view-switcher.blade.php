{!! view_render_event('admin.sales-leads.index.view-switcher.before') !!}

<div class="flex items-center gap-2">
    <!-- Kanban View -->
    <a
        href="{{ route('admin.sales-leads.index', ['view_type' => 'kanban']) }}"
        class="flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-all hover:bg-gray-100 dark:hover:bg-gray-800"
        :class="'{{ request('view_type', 'kanban') }}' === 'kanban' ? 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-white' : 'text-gray-600 dark:text-gray-400'"
    >
        <span class="icon-kanban text-lg"></span>
        Kanban
    </a>

    <!-- Table View -->
    <a
        href="{{ route('admin.sales-leads.index', ['view_type' => 'table']) }}"
        class="flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-all hover:bg-gray-100 dark:hover:bg-gray-800"
        :class="'{{ request('view_type', 'kanban') }}' === 'table' ? 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-white' : 'text-gray-600 dark:text-gray-400'"
    >
        <span class="icon-table text-lg"></span>
        Tabel
    </a>
</div>

{!! view_render_event('admin.sales-leads.index.view-switcher.after') !!}