@props(['form', 'muted' => false])
<div @class([
    'forms-overview-row grid grid-cols-[8rem_4.5rem_minmax(0,1fr)_6.5rem_6.5rem_4.5rem_5rem] items-center gap-x-3 rounded-md border px-2.5 py-1.5 text-sm',
    'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900' => ! $muted,
    'border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/50' => $muted,
])>
    <span class="truncate font-medium text-gray-800 dark:text-gray-100" title="{{ $form['type_label'] }}">
        {{ $form['type_label'] }}
    </span>

    <span class="{{ $form['status_color'] }} text-xs font-medium">
        {{ $form['status_label'] }}
    </span>

    <a href="{{ $form['entity_url'] }}" class="truncate text-xs text-gray-500 hover:underline dark:text-gray-400" title="{{ $form['coupling_label'] }}">
        {{ $form['coupling_label'] }}
    </a>

    <span class="truncate text-xs text-gray-500 dark:text-gray-400" title="Aangemaakt op {{ $form['created_at'] ?? '—' }}">
        {{ $form['created_at'] ?? '—' }}
    </span>

    <span class="truncate text-xs text-gray-500 dark:text-gray-400" title="Voltooid op {{ $form['completed_at'] ?? '—' }}">
        {{ $form['completed_at'] ?? '—' }}
    </span>

    <span class="text-xs">
        @if ($form['open_url'])
            <a href="{{ $form['open_url'] }}" target="_blank" class="text-activity-note-text hover:underline">Bekijken</a>
        @endif
    </span>

    <span class="text-right text-xs">
        <button
            type="button"
            class="forms-overview-action text-red-600 hover:underline"
            data-action="detach"
            data-url="{{ $form['detach_url'] }}"
            data-confirm="Weet je zeker dat je dit formulier wil ontkoppelen?"
        >
            Ontkoppel
        </button>
    </span>
</div>
