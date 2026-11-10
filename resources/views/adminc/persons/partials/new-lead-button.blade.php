@props([
    'persons',
])

@if (bouncer()->hasPermission('leads.create') && $persons->isNotEmpty())
    @php
        $tileClass = 'group flex h-[74px] w-[84px] flex-col items-center justify-center gap-1 rounded-lg border border-transparent bg-activity-note-bg font-medium text-activity-note-text transition-all hover:border-activity-note-border hover:text-blue-700';
        $leadUrl = fn ($person) => route('admin.leads.create', ['person_id' => $person->id]);
        $tile = '<span class="icon-leads text-2xl text-activity-note-text transition-all group-hover:text-blue-700 dark:!text-activity-note-text"></span>Lead';
    @endphp

    @if ($persons->count() === 1)
        <a href="{{ $leadUrl($persons->first()) }}" title="Nieuwe lead aanmaken" class="{{ $tileClass }}">{!! $tile !!}</a>
    @else
        <x-admin::dropdown position="bottom-left">
            <x-slot:toggle>
                <button type="button" title="Nieuwe lead aanmaken voor…" class="{{ $tileClass }}">{!! $tile !!}</button>
            </x-slot>

            <x-slot:menu class="!min-w-40">
                @foreach ($persons as $person)
                    <x-admin::dropdown.menu.item>
                        <a href="{{ $leadUrl($person) }}" class="block">{{ $person->name }}</a>
                    </x-admin::dropdown.menu.item>
                @endforeach
            </x-slot>
        </x-admin::dropdown>
    @endif
@endif
