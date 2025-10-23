<div
    class="flex flex-col gap-4"
    id="phones"
>
    <div class="flex flex-col gap-1">
        <p class="text-base font-semibold dark:text-white">
            Telefoonnummers
        </p>
    </div>

    <div class="{{ $widthClass ?? 'w-1/2 max-md:w-full' }}">
        @if ($errors->has('phones'))
            <div class="mb-2 rounded border border-red-400 bg-red-100 px-3 py-2 text-red-800 dark:bg-red-900 dark:text-red-200">
                @foreach ($errors->get('phones') as $msg)
                    <div>{{ $msg }}</div>
                @endforeach
            </div>
        @endif
        <x-adminc::components.phones :name="$name ?? 'phones'" :value="($value ?? [])" :readonly="$readonly ?? false" />
    </div>
</div>

