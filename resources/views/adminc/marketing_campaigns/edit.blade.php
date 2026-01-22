<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.marketing.campaigns.index.edit.title')
    </x-slot>

    <x-admin::form :action="route('admin.settings.marketing.campaigns.update', $campaign->id)" method="POST">
        @method('PUT')
        @include('adminc.components.validation-errors')

        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.marketing.campaigns.edit" />

                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.settings.marketing.campaigns.index.edit.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <button type="submit" class="primary-button">
                        Opslaan
                    </button>
                </div>
            </div>

            <div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <x-adminc::components.field
                    type="text"
                    name="name"
                    value="{{ old('name', $campaign->name) }}"
                    rules="required|min:1|max:255"
                    :label="trans('admin::app.settings.marketing.campaigns.index.create.name')"
                    :placeholder="trans('admin::app.settings.marketing.campaigns.index.create.name')"
                />

                <x-adminc::components.field
                    type="text"
                    name="subject"
                    value="{{ old('subject', $campaign->subject) }}"
                    rules="required|min:1|max:255"
                    :label="trans('admin::app.settings.marketing.campaigns.index.create.subject')"
                    :placeholder="trans('admin::app.settings.marketing.campaigns.index.create.subject')"
                />

                <x-adminc::components.field
                    type="select"
                    name="marketing_event_id"
                    value="{{ old('marketing_event_id', $campaign->marketing_event_id) }}"
                    :label="trans('admin::app.settings.marketing.campaigns.index.create.event')"
                >
                    <option value="">@lang('admin::app.select')</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}" @selected((string) old('marketing_event_id', $campaign->marketing_event_id) === (string) $event->id)>
                            {{ $event->name }}
                        </option>
                    @endforeach
                </x-adminc::components.field>

                <x-adminc::components.field
                    type="select"
                    name="marketing_template_id"
                    value="{{ old('marketing_template_id', $campaign->marketing_template_id) }}"
                    :label="trans('admin::app.settings.marketing.campaigns.index.create.email-template')"
                >
                    <option value="">@lang('admin::app.select')</option>
                    @foreach ($emailTemplates as $template)
                        <option value="{{ $template->id }}" @selected((string) old('marketing_template_id', $campaign->marketing_template_id) === (string) $template->id)>
                            {{ $template->name }}
                        </option>
                    @endforeach
                </x-adminc::components.field>

                <x-adminc::components.field
                    type="text"
                    name="external_id"
                    value="{{ old('external_id', $campaign->external_id) }}"
                    rules="min:1|max:255"
                    label="Extern ID"
                    placeholder="Extern ID"
                />
                <x-adminc::components.field
                    type="switch"
                    name="status"
                    value="1"
                    :checked="(bool) old('status', (bool) $campaign->status)"
                    :label="trans('admin::app.settings.marketing.campaigns.index.create.status')"
                />
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>

