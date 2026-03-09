<div class="flex items-center gap-2">
    @if ($value)
        <a
            href="{{ route('admin.settings.attributes.download', ['path' => $value]) }}"
            target="_blank"
        >
            <img
                src="{{ Storage::url($value) }}"
                alt="{{ $attribute->code }}"
                class="top-15 rounded-3 border-3 relative h-[33px] w-[33px] border-gray-500"
            />
        </a>
    @endif

    <x-adminc::components.field
        type="file"
        :id="$attribute->code"
        :name="$attribute->code"
        class="!w-full"
        :rules="$validations"
        :label="$attribute->name"
    />
</div>

@if ($value)
    <x-adminc::components.field
        type="checkbox"
        name="{{ $attribute->code }}[delete]"
        id="{{ $attribute->code }}[delete]"
        value="1"
        :label="__('admin::app.components.attributes.edit.delete')"
        labelClass="cursor-pointer !text-gray-600 dark:!text-gray-300"
    />
@endif