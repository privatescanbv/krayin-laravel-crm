<div
    class="flex flex-col gap-4"
    id="emails"
>
    <div class="flex flex-col gap-1">
        <p class="text-base font-semibold dark:text-white">
            @lang('admin::app.leads.common.emails.title')
        </p>
    </div>

    <div class="{{ $widthClass ?? 'w-1/2 max-md:w-full' }}">
        @if ($errors->has('emails'))
            <div class="mb-2 rounded border border-red-400 bg-red-100 px-3 py-2 text-red-800 dark:bg-red-900 dark:text-red-200">
                @foreach ($errors->get('emails') as $msg)
                    <div>{{ $msg }}</div>
                @endforeach
            </div>
        @endif
        @include('admin::components.emails', ['name' => ($name ?? 'emails'), 'value' => ($value ?? [])])
    </div>
</div>

