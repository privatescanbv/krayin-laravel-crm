<label {{ $attributes->merge(['class' => 'absolute left-0 top-4 ml-2 -translate-y-6 text-xs bg-gradient-to-t from-neutral-bg to-white px-1 duration-100 ease-linear peer-placeholder-shown:-translate-y-1 peer-placeholder-shown:text-sm peer-placeholder-shown:text-gray-500 peer-placeholder-shown:bg-none']) }}>
    {{ $slot }}
</label>
