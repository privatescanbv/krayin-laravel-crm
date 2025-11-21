<div class="p-4">
    <div class="flex flex-col gap-4 dark:text-white">
        <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <h4 class="mb-4 text-lg font-semibold dark:text-white">
                @lang('admin::app.settings.clinics.view.overview.general-info')
            </h4>

            <div class="flex flex-col gap-3">
                <div class="grid grid-cols-[200px_1fr] gap-2">
                    <span class="font-medium text-gray-600 dark:text-gray-400">
                        @lang('admin::app.settings.clinics.view.overview.name'):
                    </span>
                    <span class="dark:text-white">{{ $clinic->name }}</span>
                </div>

                @if (!empty($clinic->external_id))
                    <div class="grid grid-cols-[200px_1fr] gap-2">
                        <span class="font-medium text-gray-600 dark:text-gray-400">
                            @lang('admin::app.settings.clinics.view.overview.external-id'):
                        </span>
                        <span class="dark:text-white">{{ $clinic->external_id }}</span>
                    </div>
                @endif

                @php
                    $emailList = $clinic->emails ?? [];
                    if (!is_array($emailList)) {
                        $emailList = [];
                    }
                    $validEmails = collect($emailList)->filter(function($field) {
                        if (is_array($field)) {
                            return !empty($field['value']);
                        }
                        return !empty($field);
                    });
                @endphp
                @if ($validEmails->count() > 0)
                    <div class="grid grid-cols-[200px_1fr] gap-2">
                        <span class="font-medium text-gray-600 dark:text-gray-400">
                            @lang('admin::app.settings.clinics.view.overview.emails'):
                        </span>
                        <div>
                            @foreach ($validEmails as $field)
                                @php
                                    $value = is_array($field) ? ($field['value'] ?? '') : $field;
                                    $fieldLabel = is_array($field) ? ($field['label'] ?? '') : '';
                                    $isDefault = is_array($field) ? (!empty($field['is_default'])) : false;
                                @endphp
                                @if (!empty($value))
                                    <div class="flex items-center gap-2 dark:text-white">
                                        <a href="mailto:{{ $value }}" class="text-activity-note-text hover:text-activity-task-text dark:text-blue-400">
                                            {{ $value }}
                                        </a>
                                        @if (!empty($fieldLabel))
                                            <span class="text-xs text-gray-500 dark:text-gray-400">({{ $fieldLabel }})</span>
                                        @endif
                                        @if ($isDefault)
                                            <span class="text-xs rounded bg-blue-100 px-1.5 py-0.5 text-activity-task-text dark:bg-blue-900 dark:text-blue-200">
                                                standaard
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                @php
                    $phoneList = $clinic->phones ?? [];
                    if (!is_array($phoneList)) {
                        $phoneList = [];
                    }
                    $validPhones = collect($phoneList)->filter(function($field) {
                        if (is_array($field)) {
                            return !empty($field['value']);
                        }
                        return !empty($field);
                    });
                @endphp
                @if ($validPhones->count() > 0)
                    <div class="grid grid-cols-[200px_1fr] gap-2">
                        <span class="font-medium text-gray-600 dark:text-gray-400">
                            @lang('admin::app.settings.clinics.view.overview.phones'):
                        </span>
                        <div>
                            @foreach ($validPhones as $field)
                                @php
                                    $value = is_array($field) ? ($field['value'] ?? '') : $field;
                                    $fieldLabel = is_array($field) ? ($field['label'] ?? '') : '';
                                    $isDefault = is_array($field) ? (!empty($field['is_default'])) : false;
                                @endphp
                                @if (!empty($value))
                                    <div class="flex items-center gap-2 dark:text-white">
                                        <a href="tel:{{ $value }}" class="text-activity-note-text hover:text-activity-task-text dark:text-blue-400">
                                            {{ $value }}
                                        </a>
                                        @if (!empty($fieldLabel))
                                            <span class="text-xs text-gray-500 dark:text-gray-400">({{ $fieldLabel }})</span>
                                        @endif
                                        @if ($isDefault)
                                            <span class="text-xs rounded bg-blue-100 px-1.5 py-0.5 text-activity-task-text dark:bg-blue-900 dark:text-blue-200">
                                                standaard
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($clinic->address)
                    <div class="grid grid-cols-[200px_1fr] gap-2">
                        <span class="font-medium text-gray-600 dark:text-gray-400">
                            @lang('admin::app.settings.clinics.view.overview.address'):
                        </span>
                        <div class="dark:text-white">
                            @if (!empty($clinic->address->address_line_1))
                                <div>{{ $clinic->address->address_line_1 }}</div>
                            @endif
                            @if (!empty($clinic->address->address_line_2))
                                <div>{{ $clinic->address->address_line_2 }}</div>
                            @endif
                            @if (!empty($clinic->address->postal_code) || !empty($clinic->address->city))
                                <div>
                                    @if (!empty($clinic->address->postal_code))
                                        {{ $clinic->address->postal_code }}
                                    @endif
                                    @if (!empty($clinic->address->city))
                                        {{ $clinic->address->city }}
                                    @endif
                                </div>
                            @endif
                            @if (!empty($clinic->address->state))
                                <div>{{ $clinic->address->state }}</div>
                            @endif
                            @if (!empty($clinic->address->country))
                                <div>{{ $clinic->address->country }}</div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <!-- Partner Products Count -->
            <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            @lang('admin::app.settings.clinics.view.overview.total-partner-products')
                        </p>
                        <p class="text-2xl font-bold dark:text-white">
                            {{ $clinic->partnerProducts->count() }}
                        </p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                        <i class="icon-product text-2xl text-activity-note-text dark:text-blue-400"></i>
                    </div>
                </div>
            </div>

            <!-- Resources Count -->
            <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            @lang('admin::app.settings.clinics.view.overview.total-resources')
                        </p>
                        <p class="text-2xl font-bold dark:text-white">
                            {{ $clinic->resources->count() }}
                        </p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                        <i class="icon-setting text-2xl text-status-active-text dark:text-green-400"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
