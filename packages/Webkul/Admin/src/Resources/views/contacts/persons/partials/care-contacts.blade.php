@php
    $fields = [
        'Huisarts' => [
            'general_practitioner_name'     => ['Naam huisarts', 'Dr. J. de Vries'],
            'general_practitioner_practice' => ['Praktijk', 'Huisartsenpraktijk Centrum'],
            'general_practitioner_phone'    => ['Telefoon huisarts', '0201234567'],
        ],
        'Noodcontact' => [
            'emergency_contact_name'         => ['Naam noodcontact', 'M. Jansen'],
            'emergency_contact_relationship' => ['Relatie', 'Partner'],
            'emergency_contact_phone'        => ['Telefoon noodcontact', '0612345678'],
        ],
    ];
@endphp

<div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
    <div class="grid grid-cols-2 gap-6 max-md:grid-cols-1">
        @foreach ($fields as $title => $group)
            <div>
                <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">{{ $title }}</h3>

                <div class="flex flex-col gap-2">
                    @foreach ($group as $name => [$label, $placeholder])
                        <x-adminc::components.field
                            type="text"
                            :name="$name"
                            :label="$label"
                            :value="old($name, $entity?->{$name} ?? '')"
                            :placeholder="$placeholder"
                        />
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
