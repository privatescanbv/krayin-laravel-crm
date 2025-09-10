<div
    class="flex flex-col gap-4"
    id="phones"
>
    <div class="flex flex-col gap-1">
        <p class="text-base font-semibold dark:text-white">
            Telefoonnummers
        </p>
    </div>

    <div class="w-1/2 max-md:w-full">
        @include('admin::components.phones', ['name' => ($name ?? 'phones'), 'value' => ($value ?? [])])
    </div>
</div>

