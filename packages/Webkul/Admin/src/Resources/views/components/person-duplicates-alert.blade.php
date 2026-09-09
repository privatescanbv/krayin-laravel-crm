@props([
    'count' => 0,
    'url'   => '',
])

@if ((int) $count > 0)
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 text-sm dark:border-orange-900 dark:bg-orange-950">
        <div class="flex items-center gap-2 text-orange-700 dark:text-orange-300">
            <span class="icon-warning text-lg"></span>
            <span>
                @if ((int) $count === 1)
                    Er is <strong>1</strong> persoon met mogelijke duplicaten.
                @else
                    Er zijn <strong>{{ $count }}</strong> personen met mogelijke duplicaten.
                @endif
            </span>
        </div>

        <a
            href="{{ $url }}"
            class="shrink-0 font-medium text-orange-700 underline hover:text-orange-900 dark:text-orange-300 dark:hover:text-orange-200"
        >
            Bekijk & opruimen
        </a>
    </div>
@endif
