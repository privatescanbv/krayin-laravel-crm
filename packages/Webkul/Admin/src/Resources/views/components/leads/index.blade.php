@props(['leads'])

<div class="w-full rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 p-4">
    <h4 class="font-semibold dark:text-white mb-2">
        @lang('admin::app.contacts.persons.view.linked-leads')
    </h4>
    @if($leads && $leads->count())
        <ul class="flex flex-col gap-1">
            @foreach($leads as $lead)
                <li class="flex flex-col gap-1">
                    <div class="flex items-center justify-between">
                        <a 
                            href="{{ route('admin.leads.view', $lead->id) }}" 
                            class="text-brandColor hover:underline"
                        >
                            {{ $lead->name ?? 'Lead #' . $lead->id }}
                        </a>
                        <span class="text-xs text-gray-500">{{ $lead->created_at->format('d-m-Y') }}</span>
                    </div>
                    <span class="text-xs text-gray-500 ml-0">{{ $lead->stage->name ?? '' }}</span>
                </li>
            @endforeach
        </ul>
    @else
        <span class="text-gray-500">@lang('Geen leads gevonden')</span>
    @endif
</div> 