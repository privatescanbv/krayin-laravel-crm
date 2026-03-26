@props([
    'tabs'      => [],   // [['label' => '...', 'href' => '...', 'id' => ...], ...]
    'currentId' => null,
])

<nav class="pipeline-nav flex-shrink-0 border border-gray-200 bg-white rounded-md px-1 h-11 flex items-center dark:border-gray-700 dark:bg-gray-900">
    <div class="flex items-center space-x-[2px]">
        @foreach ($tabs as $tab)
            @php $active = $currentId === $tab['id']; @endphp
            <a
                href="{{ $tab['href'] }}"
                class="h-9 px-4 flex items-center rounded-md text-sm font-medium transition
                    {{ $active
                        ? 'active bg-brand-privatescan-main text-brand-privatescan-accent shadow-sm'
                        : 'text-brand-privatescan-main hover:bg-[#e8f0f9]' }}"
            >
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>
</nav>
