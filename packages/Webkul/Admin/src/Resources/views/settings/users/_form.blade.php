@php
    /** @var \Webkul\User\Models\User|null $user */
    $user = $user ?? null;

    $settingsMap = $settingsMap ?? [];

    $selectedGroups = old('groups', $user?->groups?->pluck('id')?->all() ?? []);
    if (! is_array($selectedGroups)) {
        $selectedGroups = [$selectedGroups];
    }

    $roleId = old('role_id', $user?->role_id);

    $viewPermission = old('view_permission', $user?->view_permission ?? 'global');
@endphp

<div class="rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
    <div class="p-6">
        @include('adminc.components.validation-errors')

        <div class="grid gap-6">
            <div class="flex gap-4">
                <!-- First Name -->
                <x-adminc::components.field
                    type="text"
                    id="first_name"
                    name="first_name"
                    :label="trans('admin::app.settings.users.index.create.first-name')"
                    value="{{ old('first_name', $user?->first_name) }}"
                    rules="required"
                    :placeholder="trans('admin::app.settings.users.index.create.first-name')"
                    class="flex-1"
                />

                <!-- Last Name -->
                <x-adminc::components.field
                    type="text"
                    id="last_name"
                    name="last_name"
                    :label="trans('admin::app.settings.users.index.create.last-name')"
                    value="{{ old('last_name', $user?->last_name) }}"
                    rules="required"
                    :placeholder="trans('admin::app.settings.users.index.create.last-name')"
                    class="flex-1"
                />
            </div>

            <!-- Email -->
            <x-adminc::components.field
                type="email"
                id="email"
                name="email"
                :label="trans('admin::app.settings.users.index.create.email')"
                value="{{ old('email', $user?->email) }}"
                rules="required"
                :placeholder="trans('admin::app.settings.users.index.create.email')"
            />

            <div class="flex gap-4">
                <!-- Password -->
                <x-adminc::components.field
                    type="password"
                    id="password"
                    name="password"
                    :label="trans('admin::app.settings.users.index.create.password')"
                    rules=""
                    :placeholder="trans('admin::app.settings.users.index.create.password')"
                    class="flex-1"
                />

                <!-- Confirm Password -->
                <x-adminc::components.field
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    :label="trans('admin::app.settings.users.index.create.confirm-password')"
                    rules=""
                    :placeholder="trans('admin::app.settings.users.index.create.confirm-password')"
                    class="flex-1"
                />
            </div>

            <div class="flex gap-4">
                <!-- Role -->
                <x-adminc::components.field
                    type="select"
                    name="role_id"
                    id="role_id"
                    :label="trans('admin::app.settings.users.index.create.role')"
                    value="{{ $roleId }}"
                    rules="required"
                    class="flex-1"
                >
                    @foreach ($roles as $role)
                        <option
                            value="{{ $role->id }}"
                            @selected((int) old('role_id', $user?->role_id) === (int) $role->id)
                        >
                            {{ $role->name }}
                        </option>
                    @endforeach
                </x-adminc::components.field>

                <!-- View Permission -->
                <x-adminc::components.field
                    type="select"
                    name="view_permission"
                    id="view_permission"
                    :label="trans('admin::app.settings.users.index.create.view-permission')"
                    value="{{ $viewPermission }}"
                    rules="required"
                    class="flex-1"
                >
                    <option value="global" @selected($viewPermission === 'global')>
                        @lang('admin::app.settings.users.index.create.global')
                    </option>

                    <option value="group" @selected($viewPermission === 'group')>
                        @lang('admin::app.settings.users.index.create.group')
                    </option>

                    <option value="individual" @selected($viewPermission === 'individual')>
                        @lang('admin::app.settings.users.index.create.individual')
                    </option>
                </x-adminc::components.field>
            </div>

            <!-- Signature -->
            @if($user === null)
                {{-- Create mode: signature is auto-generated, show read-only placeholder --}}
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.settings.users.index.create.signature')
                    </x-admin::form.control-group.label>
                    <textarea
                        class="flex w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-500"
                        rows="3"
                        disabled
                    >{{ __('Wordt automatisch gegenereerd na opslaan') }}</textarea>
                </x-admin::form.control-group>
            @else
                {{-- Edit mode: fully editable --}}
                <x-adminc::components.field
                    type="textarea"
                    id="signature"
                    name="signature"
                    :label="trans('admin::app.settings.users.index.create.signature')"
                    value="{{ old('signature', $user->signature) }}"
                    :placeholder="trans('admin::app.settings.users.index.create.signature')"
                    :tinymce="true"
                    rows="4"
                />
            @endif

            <!-- Groups -->
            <x-admin::form.control-group>
                <label class="mb-1.5 block text-xs font-medium leading-6 text-gray-800 dark:text-white">
                    @lang('admin::app.settings.users.index.create.group')
                </label>

                <select
                    name="groups[]"
                    multiple
                    class="flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                >
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}" @selected(in_array((int) $group->id, array_map('intval', $selectedGroups), true))>
                            {{ $group->name }}
                        </option>
                    @endforeach
                </select>

                <x-admin::form.control-group.error name="groups" />
            </x-admin::form.control-group>

            <!-- Status -->
            <input type="hidden" name="status" value="0" />

            <x-adminc::components.field
                type="switch"
                name="status"
                id="status"
                value="1"
                label="Actief"
                :checked="(bool) old('is_active', $user->status ?? true)"
            />

            <!-- User Default Field Values -->
            <div class="mt-2 rounded border border-gray-200 p-4 dark:border-gray-800">
                <div class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                    Standaard waardes voor leads
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @php
                        $selectedDept    = old('user_default_values.lead\.lead_department_id',   $settingsMap['lead.lead_department_id']    ?? '');
                        $selectedChannel = old('user_default_values.lead\.lead_channel_id', $settingsMap['lead.lead_channel_id']  ?? '');
                        $selectedSource  = old('user_default_values.lead\.lead_source_id',  $settingsMap['lead.lead_source_id']   ?? '');
                        $selectedType    = old('user_default_values.lead\.lead_type_id',         $settingsMap['lead.lead_type_id']          ?? '');
                    @endphp

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Afdeling</label>
                        <select name="user_default_values[lead.lead_department_id]" class="custom-select">
                            <option value="">— geen standaard —</option>
                            @foreach ($departments as $id => $name)
                                <option value="{{ $id }}" @selected((string) $selectedDept === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Kanaal</label>
                        <select name="user_default_values[lead.lead_channel_id]" class="custom-select">
                            <option value="">— geen standaard —</option>
                            @foreach ($channels as $id => $name)
                                <option value="{{ $id }}" @selected((string) $selectedChannel === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Bron</label>
                        <select name="user_default_values[lead.lead_source_id]" class="custom-select">
                            <option value="">— geen standaard —</option>
                            @foreach ($sources as $id => $name)
                                <option value="{{ $id }}" @selected((string) $selectedSource === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                        <select name="user_default_values[lead.lead_type_id]" class="custom-select">
                            <option value="">— geen standaard —</option>
                            @foreach ($leadTypes as $id => $name)
                                <option value="{{ $id }}" @selected((string) $selectedType === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

