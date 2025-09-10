@php
    use Webkul\Lead\Models\Channel;
    use Webkul\Lead\Models\Source;
    use Webkul\Lead\Models\Type;
    use App\Models\Department;
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
            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    Kanaal
                </x-admin::form.control-group.label>
                <x-admin::form.control-group.control
                    type="select"
                    name="lead_channel_id"
                    @if($useVueModel) v-model="formData.lead_channel_id" @endif
                    value="{{ $val('lead_channel_id', '') }}"
                >
                    <option value="">-- Kies kanaal --</option>
                    @foreach ($channelOptions as $id => $name)
                        <option value="{{ $id }}" {{ ($val('lead_channel_id', '') == $id) ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </x-admin::form.control-group.control>
            </x-admin::form.control-group>
        </div>
        <div class="flex-1">
            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    Bron
                </x-admin::form.control-group.label>
                <x-admin::form.control-group.control
                    type="select"
                    name="lead_source_id"
                    @if($useVueModel) v-model="formData.lead_source_id" @endif
                    value="{{ $val('lead_source_id', '') }}"
                >
                    <option value="">-- Kies bron --</option>
                    @foreach ($sourceOptions as $id => $name)
                        <option value="{{ $id }}" {{ ($val('lead_source_id', '') == $id) ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </x-admin::form.control-group.control>
            </x-admin::form.control-group>
        </div>
    </div>

    <!-- Department & Type -->
    <div class="flex gap-4 mb-4">
        <div class="flex-1">
            <x-admin::form.control-group>
                <x-admin::form.control-group.label class="required">
                    Afdeling
                </x-admin::form.control-group.label>
                <x-admin::form.control-group.control
                    type="select"
                    name="department_id"
                    @if($useVueModel) v-model="formData.department_id" @endif
                    rules="required"
                    value="{{ $val('department_id', '') }}"
                >
                    <option value="">-- Kies afdeling --</option>
                    @foreach ($departmentOptions as $id => $name)
                        <option value="{{ $id }}" {{ ($val('department_id', '') == $id) ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </x-admin::form.control-group.control>
            </x-admin::form.control-group>
        </div>
        <div class="flex-1">
            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    Type
                </x-admin::form.control-group.label>
                <x-admin::form.control-group.control
                    type="select"
                    name="lead_type_id"
                    @if($useVueModel) v-model="formData.lead_type_id" @endif
                    value="{{ $val('lead_type_id', '') }}"
                >
                    <option value="">-- Kies type --</option>
                    @foreach ($typeOptions as $id => $name)
                        <option value="{{ $id }}" {{ ($val('lead_type_id', '') == $id) ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </x-admin::form.control-group.control>
            </x-admin::form.control-group>
        </div>
    </div>

    <!-- MRI & Combine Order -->
    <div class="flex gap-4 mb-4">
        <div class="flex-1">
            @php
                $currentMRI = $val('mri_status');
            @endphp
            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    MRI Status
                </x-admin::form.control-group.label>
                <x-admin::form.control-group.control
                    type="select"
                    name="mri_status"
                    @if($useVueModel) v-model="formData.mri_status" @endif
                    value="{{ $currentMRI }}"
                >
                    <option value="">-- Selecteer MRI status --</option>
                    @foreach (App\Enums\MRIStatus::cases() as $case)
                        <option value="{{ $case->value }}" {{ ($currentMRI == $case->value) ? 'selected' : '' }}>{{ $case->label() }}</option>
                    @endforeach
                </x-admin::form.control-group.control>
            </x-admin::form.control-group>
        </div>
        <div class="flex-1">
            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    Orders combineren
                </x-admin::form.control-group.label>
                <x-admin::form.control-group.control
                    type="select"
                    name="combine_order"
                    @if($useVueModel) v-model="formData.combine_order" @endif
                    value="{{ (string)($val('combine_order', 1)) }}"
                >
                    <option value="1" {{ ((string)$val('combine_order', 1) === '1') ? 'selected' : '' }}>Ja</option>
                    <option value="0" {{ ((string)$val('combine_order', 1) === '0') ? 'selected' : '' }}>Nee</option>
                </x-admin::form.control-group.control>
            </x-admin::form.control-group>
        </div>
    </div>

    <!-- Owner -->
    <div class="mb-2">
        @php
            $userOptions = \Webkul\User\Models\User::query()->pluck('name', 'id')->toArray();
            $currentUserId = $val('user_id');
        @endphp
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                Toegewezen gebruiker
            </x-admin::form.control-group.label>
            <x-admin::form.control-group.control
                type="select"
                name="user_id"
                value="{{ $currentUserId }}"
            >
                <option value="">-- Kies gebruiker --</option>
                @foreach ($userOptions as $id => $name)
                    <option value="{{ $id }}" {{ ($currentUserId == $id) ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </x-admin::form.control-group.control>
        </x-admin::form.control-group>
    </div>
</div>

