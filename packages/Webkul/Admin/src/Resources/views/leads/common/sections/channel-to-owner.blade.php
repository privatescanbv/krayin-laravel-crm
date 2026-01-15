@php
    use Webkul\Lead\Models\Channel;
    use Webkul\Lead\Models\Source;
    use Webkul\Lead\Models\Type;
    use App\Models\Department;use Webkul\User\Models\User;
    $channelOptions = Channel::query()->pluck('name', 'id')->toArray();
    $sourceOptions = Source::query()->pluck('name', 'id')->toArray();
    $departmentOptions = Department::query()->pluck('name', 'id')->toArray();
    $typeOptions = Type::query()->pluck('name', 'id')->toArray();

    $entity = $entity ?? null;
    $defaults = $defaults ?? [];
    $useVueModel = $useVueModel ?? false;

    $val = function(string $key, $fallback = null) use ($entity, $defaults) {
        $old = old($key);
        if ($old !== null) { return $old; }
        if ($entity && isset($entity->{$key})) { return $entity->{$key}; }
        if (array_key_exists($key, $defaults)) { return $defaults[$key]; }
        return $fallback;
    };
@endphp

<div class="flex flex-col gap-4">
    <!-- Channel & Source -->
    <div class="flex gap-4 mb-4">
        <div class="flex-1">
            @if ($useVueModel)
                <x-adminc::components.field
                    type="select"
                    name="lead_channel_id"
                    :label="'Kanaal'"
                    value="{{ $val('lead_channel_id', '') }}"
                    v-model="formData.lead_channel_id"
                >
                    <option value="">-- Kies kanaal --</option>
                    @foreach ($channelOptions as $id => $name)
                        <option
                            value="{{ $id }}" {{ ($val('lead_channel_id', '') == $id) ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </x-adminc::components.field>
            @else
            <x-adminc::components.field
                type="select"
                name="lead_channel_id"
                :label="'Kanaal'"
                value="{{ $val('lead_channel_id', '') }}"
            >
                <option value="">-- Kies kanaal --</option>
                @foreach ($channelOptions as $id => $name)
                    <option
                        value="{{ $id }}" {{ ($val('lead_channel_id', '') == $id) ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </x-adminc::components.field>
            @endif
        </div>
        <div class="flex-1">
            @if ($useVueModel)
                <x-adminc::components.field
                    type="select"
                    name="lead_source_id"
                    :label="'Bron'"
                    value="{{ $val('lead_source_id', '') }}"
                    v-model="formData.lead_source_id"
                >
                    <option value="">-- Kies bron --</option>
                    @foreach ($sourceOptions as $id => $name)
                        <option
                            value="{{ $id }}" {{ ($val('lead_source_id', '') == $id) ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </x-adminc::components.field>
            @else
            <x-adminc::components.field
                type="select"
                name="lead_source_id"
                :label="'Bron'"
                value="{{ $val('lead_source_id', '') }}"
            >
                <option value="">-- Kies bron --</option>
                @foreach ($sourceOptions as $id => $name)
                    <option
                        value="{{ $id }}" {{ ($val('lead_source_id', '') == $id) ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </x-adminc::components.field>
            @endif
        </div>
    </div>

    <!-- Department & Type -->
    <div class="flex gap-4 mb-4">
        <div class="flex-1">
            @if ($useVueModel)
                <x-adminc::components.field
                    type="select"
                    name="department_id"
                    rules="required"
                    :label="'Afdeling'"
                    value="{{ $val('department_id', '') }}"
                    v-model="formData.department_id"
                >
                    <option value="">-- Kies afdeling --</option>
                    @foreach ($departmentOptions as $id => $name)
                        <option
                            value="{{ $id }}" {{ ($val('department_id', '') == $id) ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </x-adminc::components.field>
            @else
            <x-adminc::components.field
                type="select"
                name="department_id"
                rules="required"
                :label="'Afdeling'"
                value="{{ $val('department_id', '') }}"
            >
                <option value="">-- Kies afdeling --</option>
                @foreach ($departmentOptions as $id => $name)
                    <option
                        value="{{ $id }}" {{ ($val('department_id', '') == $id) ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </x-adminc::components.field>
            @endif
        </div>
        <div class="flex-1">
            @if ($useVueModel)
                <x-adminc::components.field
                    type="select"
                    name="lead_type_id"
                    :label="'Type'"
                    value="{{ $val('lead_type_id', '') }}"
                    v-model="formData.lead_type_id"
                >
                    <option value="">-- Kies type --</option>
                    @foreach ($typeOptions as $id => $name)
                        <option
                            value="{{ $id }}" {{ ($val('lead_type_id', '') == $id) ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </x-adminc::components.field>
            @else
            <x-adminc::components.field
                type="select"
                name="lead_type_id"
                :label="'Type'"
                value="{{ $val('lead_type_id', '') }}"
            >
                <option value="">-- Kies type --</option>
                @foreach ($typeOptions as $id => $name)
                    <option
                        value="{{ $id }}" {{ ($val('lead_type_id', '') == $id) ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </x-adminc::components.field>
            @endif
        </div>
    </div>

    <!-- MRI Status -->
    <div class="flex gap-4 mb-4">
        <div class="flex-1">
            @php
                $currentMRI = $val('mri_status');
            @endphp
            @if ($useVueModel)
                <x-adminc::components.field
                    type="select"
                    name="mri_status"
                    :label="'MRI Status'"
                    value="{{ $currentMRI }}"
                    v-model="formData.mri_status"
                >
                    <option value="">-- Selecteer MRI status --</option>
                    @foreach (App\Enums\MRIStatus::cases() as $case)
                        <option
                            value="{{ $case->value }}" {{ ($currentMRI == $case->value) ? 'selected' : '' }}>{{ $case->label() }}</option>
                    @endforeach
                </x-adminc::components.field>
            @else
                <x-adminc::components.field
                    type="select"
                    name="mri_status"
                    :label="'MRI Status'"
                    value="{{ $currentMRI }}"
                >
                    <option value="">-- Selecteer MRI status --</option>
                    @foreach (App\Enums\MRIStatus::cases() as $case)
                        <option
                            value="{{ $case->value }}" {{ ($currentMRI == $case->value) ? 'selected' : '' }}>{{ $case->label() }}</option>
                    @endforeach
                </x-adminc::components.field>
            @endif
        </div>
        <div class="flex-1">
            <!-- Empty div to maintain layout -->
        </div>
    </div>
    <div class="flex gap-4 mb-4">
        <div class="flex-1">
            <!-- Diagnoseformulier aanwezig? -->

            <x-adminc::components.field
                type="switch"
                name="has_diagnosis_form"
                label="Diagnoseformulier aanwezig?"
                value="{{ $has_diagnosis_form ?? '' }}"
                 />


            <!-- <x-admin::form.control-group class="mt-2">
                <x-admin::form.control-group.label static>
                    Diagnoseformulier aanwezig?
                </x-admin::form.control-group.label>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="has_diagnosis_form" value="0"/>
                    <input
                        type="checkbox"
                        name="has_diagnosis_form"
                        value="1"
                        class="cursor-pointer"
                    />
                    <span class="text-sm text-gray-600 dark:text-gray-300">Ja</span>
                </div>

            </x-admin::form.control-group> -->
        </div>

    </div>
</div>

