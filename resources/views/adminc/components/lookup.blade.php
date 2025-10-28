@props([
    'src' => null,
    'name' => 'lookup',
    'label' => 'Selectie',
    'placeholder' => 'Kies een item...',
    'value' => null,
    'rules' => '',
    'canAddNew' => false,
])

<div class="relative" v-pre>
    <v-lookup
        :src="'{{ $src }}'"
        :name="'{{ $name }}'"
        :label="'{{ $label }}'"
        :placeholder="'{{ $placeholder }}'"
        :value='@json($value)'
        :rules="'{{ $rules }}'"
        :can-add-new="{{ $canAddNew ? 'true' : 'false' }}"
        @on-selected="$emit('on-selected', $event.detail)"
    ></v-lookup>
</div>
