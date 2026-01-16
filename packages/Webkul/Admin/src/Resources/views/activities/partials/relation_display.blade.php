@props(['activity'])

@php
    $relation = null;

    if ($activity->lead) {
        $relation = [
            'type'  => 'lead',
            'label' => 'lead',
            'icon'  => 'icon-lead',
            'route' => route('admin.leads.view', $activity->lead->id),
            'name'  => $activity->lead->name,
            'class' => 'bg-blue-100 text-blue-800 border-blue-200
                        dark:bg-blue-900 dark:text-blue-200 dark:border-blue-800',
        ];
    } elseif ($activity->salesLead) {
        $relation = [
            'type'  => 'sales lead',
            'label' => 'sales lead',
            'icon'  => 'icon-sales-lead',
            'route' => route('admin.sales-leads.view', $activity->salesLead->id),
            'name'  => $activity->salesLead->name,
            'class' => 'bg-green-100 text-green-800 border-green-200
                        dark:bg-green-900 dark:text-green-200 dark:border-green-800',
        ];
    } elseif ($activity->clinic) {
        $relation = [
            'type'  => 'kliniek',
            'label' => 'kliniek',
            'icon'  => 'icon-clinic',
            'route' => route('admin.clinics.view', $activity->clinic->id),
            'name'  => $activity->clinic->name ?? '#' . $activity->clinic->id,
            'class' => 'bg-purple-100 text-purple-800 border-purple-200
                        dark:bg-purple-900 dark:text-purple-200 dark:border-purple-800',
        ];
    }
@endphp

<div class="mt-2 flex flex-wrap gap-2 text-xs">
    @if ($relation)
        <a href="{{ $relation['route'] }}"
           class="inline-flex items-center gap-1 rounded-md border px-2.5 py-1.5 font-medium {{ $relation['class'] }}">
            <span class="{{ $relation['icon'] }}"></span>
            <span>Gekoppeld aan <strong>{{ $relation['label'] }}</strong>:</span>
            <span>{{ $relation['name'] }}</span>
        </a>
    @else
            @if(!empty($activity->persons))
            @foreach($activity->persons as $person)
                <span>Gekoppeld aan Personen: </span>
                <a href="{{ route('admin.contacts.persons.view', $person->id) }}"
                   class="inline-flex items-center px-2.5 py-1.5 rounded-md text-xs font-medium bg-purple-100 text-purple-800 hover:bg-purple-200 dark:bg-purple-900 dark:text-purple-200 dark:hover:bg-purple-800">
                    <span class="icon-clinic mr-1"></span>
                    {{ $person->name ?? '#' . $person->id }}
                </a>
            @endforeach
            @else
                <span class="text-gray-500 italic">Relatie onbekend</span>
            @endif
    @endif


</div>



{{--<div class="flex flex-wrap gap-2 mt-2">--}}
{{--    @if($activity->lead)--}}
{{--        <a href="{{ route('admin.leads.view', $activity->lead->id) }}"--}}
{{--           class="inline-flex items-center px-2.5 py-1.5 rounded-md text-xs font-medium bg-blue-100 text-activity-task-text hover:bg-activity-task-bg dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800">--}}
{{--            <span class="icon-lead mr-1"></span>--}}
{{--            Gekoppeld aan lead: {{ $activity->lead->name }}--}}
{{--        </a>--}}
{{--    @elseif($activity->salesLead)--}}
{{--        <a href="{{ route('admin.sales-leads.view', $activity->salesLead->id) }}"--}}
{{--           class="inline-flex items-center px-2.5 py-1.5 rounded-md text-xs font-medium bg-green-100 text-green-800 hover:bg-activity-email-bg dark:bg-green-900 dark:text-green-200 dark:hover:bg-green-800">--}}
{{--            <span class="icon-sales-lead mr-1"></span>--}}
{{--            Sales: {{ $activity->salesLead->name }}--}}
{{--        </a>--}}
{{--    @elseif($activity->clinic)--}}
{{--        <a href="{{ route('admin.clinics.view', $activity->clinic->id) }}"--}}
{{--           class="inline-flex items-center px-2.5 py-1.5 rounded-md text-xs font-medium bg-purple-100 text-purple-800 hover:bg-purple-200 dark:bg-purple-900 dark:text-purple-200 dark:hover:bg-purple-800">--}}
{{--            <span class="icon-clinic mr-1"></span>--}}
{{--            {{ $activity->clinic->name ?? '#' . $activity->clinic->id }}--}}
{{--        </a>--}}
{{--    @elseif(!$activity->persons->isEmpty())--}}
{{--        @foreach($activity->persons as $person)--}}
{{--            <a href="{{ route('admin.contacts.persons.view', $person->id) }}"--}}
{{--               class="inline-flex items-center px-2.5 py-1.5 rounded-md text-xs font-medium bg-purple-100 text-purple-800 hover:bg-purple-200 dark:bg-purple-900 dark:text-purple-200 dark:hover:bg-purple-800">--}}
{{--                <span class="icon-clinic mr-1"></span>--}}
{{--                {{ $person->name ?? '#' . $person->id }}--}}
{{--            </a>--}}
{{--        @endforeach--}}
{{--    @elseif(!$activity->products->isEmpty())--}}
{{--        @foreach($activity->products as $product)--}}
{{--            <a href="{{ route('admin.products.view', $product->id) }}"--}}
{{--               class="inline-flex items-center px-2.5 py-1.5 rounded-md text-xs font-medium bg-purple-100 text-purple-800 hover:bg-purple-200 dark:bg-purple-900 dark:text-purple-200 dark:hover:bg-purple-800">--}}
{{--                <span class="icon-clinic mr-1"></span>--}}
{{--                {{ $product->name ?? '#' . $product->id }}--}}
{{--            </a>--}}
{{--        @endforeach--}}
{{--    @else--}}
{{--        Relatie onbekend--}}
{{--    @endif--}}
{{--</div>--}}
