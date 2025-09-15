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
        @include('admin::components.emails', ['name' => ($name ?? 'emails'), 'value' => ($value ?? [])])
    </div>
</div>

