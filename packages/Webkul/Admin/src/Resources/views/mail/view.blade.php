@php
    if (! $email->is_read) {
        $email->is_read = true;
        $email->save();
    }

    // Prepare email data with accessors for Vue component
    $emailData = $email->getAttributes();
    $emailData['sender_email'] = $email->sender_email;
    $emailData['has_relationships'] = $email->has_relationships;
@endphp

@include('adminc.components.entity-selector')
@include('adminc.components.contact-person-selector')
@include('adminc.emails.email-item')
@include('adminc.emails.email-action-panel', ['email' => $email])

<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.mail.view.subject', ['subject' => $email->subject])
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                {!! view_render_event('admin.mail.view.form.before', ['email' => $email]) !!}

                <!-- Back Button and Breadcrumbs -->
                <div class="flex items-center gap-3">
                    <!-- Back Button -->
                    <a href="{{ route('admin.mail.index', ['route' => request('route')]) }}"
                       class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-md transition-colors dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700">
                        <i class="icon-arrow-left text-lg"></i>
                        @lang('admin::app.mail.view.back-to-folder')
                    </a>

                    <!-- Breadcrumbs -->
                    <x-admin::breadcrumbs
                        name="mail.route.view"
                        :entity="$email"
                        :route="request('route')"
                    />
                </div>

                {!! view_render_event('admin.mail.view.form.after', ['email' => $email]) !!}

                <!-- Title -->
                <div class="flex items-center gap-2">
                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.mail.view.title')
                    </div>

                    <span class="label-active">{{ ucfirst(request('route')) }}</span>

                    {!! view_render_event('admin.mail.view.tags.before', ['email' => $email]) !!}

                    @php
                        // Load tags eagerly to avoid N+1 query during rendering
                        $emailTags = $email->relationLoaded('tags') ? $email->tags : $email->tags()->get();
                    @endphp
                    <x-admin::tags
                        :attach-endpoint="route('admin.mail.tags.attach', $email->id)"
                        :detach-endpoint="route('admin.mail.tags.detach', $email->id)"
                        :added-tags="$emailTags"
                    />

                    {!! view_render_event('admin.mail.view.tags.after', ['email' => $email]) !!}
                </div>
            </div>
        </div>

        {!! view_render_event('admin.mail.view.email-list.before', ['email' => $email]) !!}

        <!-- Email List Vue Component -->
        <v-email-list>
           @php
               // Use count from eager loaded relationship instead of query
               $emailCount = $email->relationLoaded('emails') ? $email->emails->count() : ($email->emails ? $email->emails->count() : 1);
           @endphp
           <x-admin::shimmer.leads.view.mail :count="$emailCount"/>
        </v-email-list>

        {!! view_render_event('admin.mail.view.email-list.before', ['email' => $email]) !!}
    </div>

    @pushOnce('scripts')
        <!-- Email List Template -->
        <script
            type="text/x-template"
            id="v-email-list-template"
        >
            <div class="mt-3.5 flex gap-2.5 max-xl:flex-wrap">
                <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
                    {!! view_render_event('admin.mail.view.email-item.before', ['email' => $email]) !!}

                    <!-- Email Item Vue Component -->
                    <v-email-item
                        :email="email"
                        :key="0"
                        :index="0"
                        :action="action"
                        @on-discard="action = {}"
                        @on-email-action="emailAction($event)"
                    ></v-email-item>

                    {!! view_render_event('admin.mail.view.email-item.after', ['email' => $email]) !!}

                    {!! view_render_event('admin.mail.view.email-item.before', ['email' => $email]) !!}

                    <!-- Email Item Vue Component -->
                    <v-email-item
                        v-for='(email, index) in email.emails'
                        :email="email"
                        :key="index + 1"
                        :index="index + 1"
                        :action="action"
                        @on-discard="action = {}"
                        @on-email-action="emailAction($event)"
                    ></v-email-item>

                    {!! view_render_event('admin.mail.view.email-item.after', ['email' => $email]) !!}
                </div>

                @if (
                    bouncer()->hasPermission('contacts.persons.edit')
                )
                    <!-- Email Actions -->
                    <div class="sticky top-4 flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
                        <div class="box-shadow rounded bg-white dark:bg-gray-900">
                            <div class="flex flex-col gap-4 p-4">
                                <!-- Email Action Vue Component -->
                                <v-action-email ref="emailAction"></v-action-email>
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        </script>

        <!-- Email Form Template -->
        <script
            type="text/x-template"
            id="v-email-form-template"
        >
            <div class="flex w-full gap-2">
                <x-admin::avatar ::name="email.name ?? email.from" />

                {!! view_render_event('admin.mail.view.form.before', ['email' => $email]) !!}

                <div class="w-[926px] gap-2 rounded border p-4 dark:border-gray-800">
                    <x-admin::form
                        v-slot="{ meta, errors, handleSubmit }"
                        enctype="multipart/form-data"
                        as="div"
                    >
                        <form
                            @submit="handleSubmit($event, save)"
                            ref="mailActionForm"
                        >
                            <div class="flex flex-col gap-2">
                                <div class="border-b dark:border-gray-800">
                                    <!-- Activity Type -->
                                    <x-admin::form.control-group.control
                                        type="hidden"
                                        name="parent_id"
                                        value="{{ request('id') }}"
                                    />

                                    <!-- To -->
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.label class="required">
                                            @lang('admin::app.mail.view.to')
                                        </x-admin::form.control-group.label>

                                        <div class="relative">
                                            <x-admin::form.control-group.controls.tags
                                                name="reply_to"
                                                input-rules="email"
                                                rules="required"
                                                ::data="reply_to"
                                                :label="trans('admin::app.mail.view.to')"
                                                :placeholder="trans('admin::app.mail.view.enter-mails')"
                                            />

                                            <div class="absolute top-[9px] flex items-center gap-2 ltr:right-2 rtl:left-2">
                                                <span
                                                    class="cursor-pointer font-medium hover:underline dark:text-gray-300"
                                                    @click="showCC = ! showCC"
                                                >
                                                    @lang('admin::app.mail.view.cc')
                                                </span>

                                                <span
                                                    class="cursor-pointer font-medium hover:underline dark:text-gray-300"
                                                    @click="showBCC = ! showBCC"
                                                >
                                                    @lang('admin::app.mail.view.bcc')
                                                </span>
                                            </div>
                                        </div>

                                        <x-admin::form.control-group.error control-name="reply_to" />
                                    </x-admin::form.control-group>

                                    <template v-if="showCC">
                                        <!-- Cc -->
                                        <x-admin::form.control-group>
                                            <x-admin::form.control-group.label>
                                                @lang('admin::app.mail.view.cc')
                                            </x-admin::form.control-group.label>

                                            <x-admin::form.control-group.control
                                                type="tags"
                                                name="cc"
                                                input-rules="email"
                                                :label="trans('admin::app.mail.view.cc')"
                                                :placeholder="trans('admin::app.mail.view.enter-mails')"
                                            />

                                            <x-admin::form.control-group.error control-name="cc" />
                                        </x-admin::form.control-group>
                                    </template>

                                    <template v-if="showBCC">
                                        <!-- Cc -->
                                        <x-admin::form.control-group>
                                            <x-admin::form.control-group.label>
                                                @lang('admin::app.mail.view.bcc')
                                            </x-admin::form.control-group.label>

                                            <x-admin::form.control-group.control
                                                type="tags"
                                                name="bcc"
                                                input-rules="email"
                                                :label="trans('admin::app.mail.view.bcc')"
                                                :placeholder="trans('admin::app.mail.view.enter-mails')"
                                            />

                                            <x-admin::form.control-group.error control-name="bcc" />
                                        </x-admin::form.control-group>
                                    </template>

                                    <!-- Content -->
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.control
                                            type="textarea"
                                            name="reply"
                                            id="reply"
                                            rules="required"
                                            ::value="reply"
                                            :tinymce="true"
                                            :label="trans('admin::app.mail.view.message')"
                                        />

                                        <x-admin::form.control-group.error control-name="reply" />
                                    </x-admin::form.control-group>

                                    <!-- Attachments -->
                                    <x-admin::form.control-group>
                                        <x-admin::attachments
                                            allow-multiple="true"
                                            hide-button="true"
                                        />
                                    </x-admin::form.control-group>

                                    <!-- Divider -->
                                    {{-- <hr class="h-1 dark:text-gray-800"> --}}
                                </div>

                                <!-- Action and Attachement -->
                                <div class="flex w-full items-center justify-between">
                                    <label
                                        class="flex cursor-pointer items-center gap-1 dark:text-gray-300"
                                        for="file-upload"
                                    >
                                        <i class="icon-attachment text-xl font-medium"></i>

                                        @lang('admin::app.mail.view.add-attachments')
                                    </label>

                                    <div class="flex items-center justify-center gap-4">
                                        <label
                                            class="flex cursor-pointer items-center gap-1 font-semibold dark:text-gray-300"
                                            @click="$emit('onDiscard')"
                                        >
                                            @lang('admin::app.mail.view.discard')
                                        </label>

                                        <x-admin::button
                                            class="primary-button"
                                            :title="trans('admin::app.mail.view.send')"
                                            ::loading="isStoring"
                                            ::disabled="isStoring"
                                        />
                                    </div>
                                </div>
                            </div>
                        </form>
                    </x-admin::form>
                </div>

                {!! view_render_event('admin.mail.view.form.after', ['email' => $email]) !!}
            </div>
        </script>

        <!-- Contact Lookup Template -->
        <script
            type="text/x-template"
            id="v-contact-lookup-template"
        >
            <div>
                {!! view_render_event('admin.mail.view.contact_lookup.before', ['email' => $email]) !!}

                <template v-if="email?.person_id">
                    <div class="flex justify-between">
                        <div class="flex gap-2">
                            <x-admin::avatar ::name="email.person.name" />

                            <!-- Mailer receivers -->
                            <div class="flex flex-col gap-1">
                                <!-- Mailer Name -->
                                <span class="text-xs font-medium text-gray-800 dark:text-gray-300">
                                    @{{ email.person?.name }}
                                </span>

                                <!-- Mailer Additional Deatils -->
                                <div class="flex flex-col gap-1">
                                    <span class="text-[10px] dark:text-gray-300">@{{ email.person.job_title }}</span>

                                    <!-- Emails -->
                                    <template v-for="email in (Array.isArray(email?.person?.emails) ? email.person.emails.map(item => item.value) : [])">
                                        <a
                                            class="text-brandColor"
                                            :href="`mailto:${email}`"
                                        >
                                            @{{ email }}
                                        </a>
                                    </template>

                                    <!-- Contact Numbers -->
                                    <template v-for="contactNumber in (Array.isArray(email?.person?.contact_numbers) ? email.person.contact_numbers.map(item => item.value) : [])">
                                        <a
                                            class="text-brandColor"
                                            :href="`tel:${contactNumber}`"
                                        >
                                            @{{ contactNumber }}
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <template v-if="! unlinking.contact">
                                <button
                                    type="button"
                                    class="icon-delete flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-2xl hover:rounded-md hover:bg-gray-100 dark:hover:bg-gray-950"
                                    @click="unlinkContact"
                                ></button>
                            </template>

                            <template v-else>
                                <x-admin::spinner />
                            </template>

                            <a
                                :href="'{{ route('admin.contacts.persons.edit', ':id') }}'.replace(':id', email.person_id)"
                                target="_blank"
                                class="icon-right-arrow flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-2xl hover:rounded-md hover:bg-gray-100 dark:hover:bg-gray-950"
                            ></a>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <div
                        class="relative"
                        ref="lookup"
                    >
                        <!-- Input Box (Button) -->
                        <div
                            class="relative inline-block w-full"
                            @click="toggle"
                        >
                            <!-- Input-like div -->
                            <div class="w-full cursor-pointer rounded-md border border-gray-300 px-3 py-2 text-gray-800 dark:border-gray-800 dark:text-gray-300">
                                @{{ selectedItem.name ?? '@lang('admin::app.mail.view.search-an-existing-contact')'}}
                            </div>

                            <!-- Arrow down icon -->
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                <i class="fas fa-chevron-down text-gray-400"></i>
                            </div>
                        </div>

                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                            <div class="flex items-center justify-center space-x-1">
                                <i
                                    class="text-2xl"
                                    :class="showPopup ? 'icon-up-arrow': 'icon-down-arrow'"
                                ></i>
                            </div>
                        </span>

                        <!-- Popup Box -->
                        <div
                            v-if="showPopup"
                            class="transcontact_lookup absolute top-full z-10 mt-1 flex w-full origin-top flex-col gap-2 rounded-lg border border-gray-200 bg-white p-2 shadow-lg transition-transform dark:border-gray-900 dark:bg-gray-800"
                        >
                            <!-- Search Bar -->
                            <div class="relative">
                                <!-- Input Box -->
                                <input
                                    type="text"
                                    v-model.lazy="searchTerm"
                                    v-debounce="500"
                                    class="w-full rounded border border-gray-200 px-2.5 py-2 pr-10 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                                    placeholder="Search..."
                                    ref="searchInput"
                                    @keyup="search"
                                />

                                <!-- Search Icon (absolute positioned) -->
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3">
                                    <div class="flex items-center justify-center space-x-1">
                                        <!-- Loader (optional, based on condition) -->
                                        <div
                                            class="relative"
                                            v-if="isSearching"
                                        >
                                            <x-admin::spinner />
                                        </div>

                                        <!-- Search Icon -->
                                        <i class="fas fa-search text-gray-500"></i>
                                    </div>
                                </span>
                            </div>

                            <!-- Results List -->
                            <ul class="max-h-40 divide-y divide-gray-100 overflow-y-auto">
                                <li
                                    v-for="person in persons"
                                    :key="person.id"
                                    class="flex cursor-pointer gap-2 px-4 py-2 text-gray-800 transition-colors hover:bg-blue-100 dark:text-white dark:hover:bg-gray-900"
                                    @click="linkContact(person)"
                                >
                                    <x-admin::avatar ::name="person.name" />

                                    <!-- Mailer receivers -->
                                    <div class="flex flex-col gap-1">
                                        <!-- Mailer Name -->
                                        <span>@{{ person.name }}</span>

                                        <div class="flex flex-col gap-1">
                                            <span class="text-sm">@{{ (Array.isArray(person.emails) ? person.emails.map(item => item.value) : []).join(', ') }}</span>
                                        </div>
                                    </div>
                                </li>

                                <li
                                    v-if="persons.length === 0"
                                    class="px-4 py-2 text-gray-800 dark:text-gray-300"
                                >
                                    @lang('admin::app.mail.view.no-result-found')
                                </li>
                            </ul>

                            <!-- Add New Contact Button -->
                            @if (bouncer()->hasPermission('contacts.persons.create'))
                                <button
                                    type="button"
                                    class="flex cursor-pointer items-center gap-2 border-t border-gray-200 p-2 text-brandColor transition-colors"
                                    @click="toggleContactModal"
                                >
                                    <i class="icon-add text-md !text-brandColor"></i>

                                    @lang('admin::app.mail.view.add-new-contact')
                                </button>
                            @endif
                        </div>
                    </div>
                </template>

                {!! view_render_event('admin.mail.view.contact_lookup.after', ['email' => $email]) !!}
            </div>
        </script>

        <script
            type="text/x-template"
            id="v-lead-lookup-template"
        >
            <div>
                {!! view_render_event('admin.mail.view.lead_lookup.before', ['email' => $email]) !!}

                <template v-if="email?.lead_id">
                    <div class="flex">
                        <div class="lead-item flex flex-col gap-5 rounded-md border border-gray-100 bg-gray-50 p-2 dark:border-gray-400 dark:bg-gray-400">
                            <!-- Header -->
                            <div
                                class="flex items-start justify-between"
                                v-if="email.lead?.person"
                            >
                                <div class="flex items-center gap-1">
                                    <x-admin::avatar ::name="email.lead.person?.name" />

                                    <div class="flex flex-col gap-1">
                                        <span class="text-xs font-medium">
                                            @{{ email.lead.person?.name }}
                                        </span>

                                        <span class="text-[10px] leading-normal">
                                            @{{ email.lead.person?.organization?.name }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center justify-center gap-2">
                                    <div
                                        class="group relative"
                                        v-if="email.lead.rotten_days > 0"
                                    >
                                        <span class="icon-rotten flex cursor-default items-center justify-center text-2xl text-rose-600"></span>

                                        <div class="absolute bottom-0 right-0 mb-7 hidden w-max flex-col items-center group-hover:flex">
                                            <span class="whitespace-no-wrap relative rounded-md bg-black px-4 py-2 text-xs leading-none text-white shadow-lg">
                                                @{{ "@lang('admin::app.mail.view.rotten-days', ['days' => 'replaceDays'])".replace('replaceDays', email.lead.rotten_days) }}
                                            </span>

                                            <div class="absolute -bottom-0.5 right-1 h-3 w-3 rotate-45 bg-black"></div>
                                        </div>
                                    </div>

                                    <template v-if="! unlinking.lead">
                                        <button
                                            type="button"
                                            class="icon-delete flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-2xl hover:rounded-md hover:bg-gray-100 dark:hover:bg-gray-950"
                                            @click="unlinkLead"
                                        ></button>
                                    </template>

                                    <template v-else>
                                        <x-admin::spinner />
                                    </template>

                                    <a
                                        :href="'{{ route('admin.leads.view', ':id') }}'.replace(':id', email.lead_id)"
                                        target="_blank"
                                        class="icon-right-arrow flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-2xl hover:rounded-md hover:bg-gray-100 dark:hover:bg-gray-950"
                                    ></a>
                                </div>
                            </div>

                           {{-- Lead card (only rendered server-side, not in Vue template) --}}
                           {{--
                           @if ($email->lead)
                               @include('admin::leads.common.card', ['lead' => $email->lead, 'show_actions' => false])
                           @endif
                           --}}
                            <!-- Lead Name (clickable to lead view) -->
                            <a
                                :href="'{{ route('admin.leads.view', ':id') }}'.replace(':id', email.lead_id)"
                                target="_blank"
                                class="text-xs font-medium text-brandColor hover:underline"
                            >
                                @{{ email.lead.name }}
                            </a>

                            <!-- Lead Additional Information -->
                            <div
                                class="flex flex-wrap gap-1"
                                v-if="email.lead"
                            >
                                <!-- Tags -->
                                <template v-for="tag in email.lead.tags">
                                    <div
                                        class="rounded-xl bg-slate-200 px-3 py-1 text-xs font-medium"
                                        :style="{
                                            backgroundColor: tag.color,
                                            color: tagTextColor[tag.color]
                                        }"
                                    >
                                        @{{ tag?.name }}
                                    </div>
                                </template>

                                <!-- Source Name -->
                                <div class="rounded-xl bg-slate-200 px-3 py-1 text-xs font-medium">
                                    @{{ email.lead.source?.name }}
                                </div>

                                <!-- Lead Type Name -->
                                <div class="rounded-xl bg-slate-200 px-3 py-1 text-xs font-medium">
                                    @{{ email.lead.type?.name }}
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                @if (bouncer()->hasPermission('leads.view'))
                    <template v-else>
                        <div
                            class="relative"
                            ref="lookup"
                        >
                            <!-- Input Box (Button) -->
                            <div
                                class="relative inline-block w-full"
                                @click="toggle"
                            >
                                <!-- Input-like div -->
                                <div class="w-full cursor-pointer rounded-md border border-gray-300 px-3 py-2 text-gray-800 dark:border-gray-800 dark:text-gray-300">
                                    @{{ selectedItem.name ?? '@lang('admin::app.mail.view.search-an-existing-lead')'}}
                                </div>

                                <!-- Arrow down icon -->
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                    <i class="fas fa-chevron-down text-gray-400"></i>
                                </div>
                            </div>

                            <!-- toggle popup -->
                            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                <div class="flex items-center justify-center space-x-1">
                                    <i
                                        class="text-2xl"
                                        :class="showPopup ? 'icon-up-arrow': 'icon-down-arrow'"
                                    ></i>
                                </div>
                            </span>

                            <!-- Popup Box -->
                            <div
                                v-if="showPopup"
                                class="absolute top-full z-10 mt-1 flex w-full origin-top transform flex-col gap-2 rounded-lg border border-gray-200 bg-white p-2 shadow-lg transition-transform dark:border-gray-900 dark:bg-gray-800"
                            >
                                <!-- Search Bar -->
                                <div class="relative">
                                    <!-- Input Box -->
                                    <input
                                        type="text"
                                        v-model.lazy="searchTerm"
                                        v-debounce="500"
                                        class="w-full rounded border border-gray-200 px-2.5 py-2 pr-10 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                                        placeholder="@lang('admin::app.mail.view.search')"
                                        ref="searchInput"
                                        @keyup="search"
                                    />

                                    <!-- Search Icon (absolute positioned) -->
                                    <span class="absolute inset-y-0 right-0 flex items-center pr-3">
                                        <div class="flex items-center justify-center space-x-1">
                                            <!-- Loader (optional, based on condition) -->
                                            <div
                                                class="relative"
                                                v-if="isSearching"
                                            >
                                                <x-admin::spinner />
                                            </div>

                                            <!-- Search Icon -->
                                            <i class="fas fa-search text-gray-500"></i>
                                        </div>
                                    </span>
                                </div>

                                <!-- Results List -->
                                <ul class="max-h-40 divide-y divide-gray-100 overflow-y-auto dark:divide-gray-700">
                                    <li
                                        v-for="lead in leads"
                                        :key="lead.id"
                                        class="flex cursor-pointer gap-2 px-4 py-2 text-gray-800 transition-colors hover:bg-blue-100 dark:text-white dark:hover:bg-gray-900"
                                        @click="linkLead(lead)"
                                    >
                                        <x-admin::avatar ::name="lead.name" />

                                        <!-- Lead Title -->
                                        <div class="flex flex-col gap-1">
                                            <span>@{{ lead.name }}</span>
                                        </div>
                                    </li>

                                    <li
                                        v-if="leads.length === 0"
                                        class="px-4 py-2 text-gray-800 dark:text-gray-300"
                                    >
                                        @lang('admin::app.mail.view.no-result-found')
                                    </li>
                                </ul>

                                <!-- Add New Lead Button -->
                                @if (bouncer()->hasPermission('leads.create'))
                                    <button
                                        type="button"
                                        class="flex cursor-pointer items-center gap-2 border-t border-gray-200 p-2 text-brandColor transition-colors dark:border-gray-700"
                                        @click="toggleLeadModal"
                                    >
                                        <i class="icon-add text-md !text-brandColor"></i>

                                        @lang('admin::app.mail.view.add-new-lead')
                                    </button>
                                @endif
                            </div>
                        </div>
                    </template>
                @endif

                {!! view_render_event('admin.mail.view.lead_lookup.after', ['email' => $email]) !!}
            </div>
        </script>

        <!-- Sales Lead Lookup Template -->
        <script
            type="text/x-template"
            id="v-sales-lead-lookup-template"
        >
            <div>
                <div class="relative" ref="lookup">
                    <div class="relative inline-block w-full" @click="toggle">
                        <div class="w-full cursor-pointer rounded-md border border-gray-300 px-3 py-2 text-gray-800 dark:border-gray-800 dark:text-gray-300">
                            @{{ selectedItem.name ?? 'Zoek een sales'}}
                        </div>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                            <i class="fas fa-chevron-down text-gray-400"></i>
                        </div>
                    </div>

                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                        <div class="flex items-center justify-center space-x-1">
                            <i class="text-2xl" :class="showPopup ? 'icon-up-arrow': 'icon-down-arrow'"></i>
                        </div>
                    </span>

                    <div v-if="showPopup" class="absolute top-full z-10 mt-1 flex w-full origin-top transform flex-col gap-2 rounded-lg border border-gray-200 bg-white p-2 shadow-lg transition-transform dark:border-gray-900 dark:bg-gray-800">
                        <div class="relative">
                            <input
                                type="text"
                                v-model.lazy="searchTerm"
                                v-debounce="500"
                                class="w-full rounded border border-gray-200 px-2.5 py-2 pr-10 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                                placeholder="Zoeken..."
                                ref="searchInput"
                                @keyup="search"
                            />
                            <span class="absolute inset-y-0 right-0 flex items-center pr-3">
                                <div class="flex items-center justify-center space-x-1">
                                    <div class="relative" v-if="isSearching">
                                        <x-admin::spinner />
                                    </div>
                                    <i class="fas fa-search text-gray-500"></i>
                                </div>
                            </span>
                        </div>

                        <ul class="max-h-40 divide-y divide-gray-100 overflow-y-auto dark:divide-gray-700">
                            <li
                                v-for="salesLead in salesLeads"
                                :key="salesLead.id"
                                class="flex cursor-pointer gap-2 px-4 py-2 text-gray-800 transition-colors hover:bg-blue-100 dark:text-white dark:hover:bg-gray-900"
                                @click="linkSalesLead(salesLead)"
                            >
                                <x-admin::avatar ::name="salesLead.name" />
                                <div class="flex flex-col gap-1">
                                    <span>@{{ salesLead.name }}</span>
                                    <span class="text-xs text-gray-500">@{{ salesLead.stage?.name }}</span>
                                </div>
                            </li>
                            <li v-if="salesLeads.length === 0" class="px-4 py-2 text-gray-800 dark:text-gray-300">
                                Geen resultaten gevonden
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </script>

        <!-- Create Contact Template -->
        <script
            type="text/x-template"
            id="v-create-contact-template"
        >
            {!! view_render_event('admin.mail.view.contact_form.before', ['email' => $email]) !!}

            <Teleport to="body">
                <x-admin::form
                    v-slot="{ meta, errors, handleSubmit }"
                    as="div"
                >
                    <form
                        @submit="handleSubmit($event, create)"
                        ref="contactForm"
                    >
                        <!-- Add Contact Modal -->
                        <x-admin::modal
                            ref="contactModal"
                            @toggle="toggleModal"
                        >
                            <x-slot:header>
                                <div class="flex items-center justify-between">
                                    <p class="text-xl font-semibold text-gray-800 dark:text-white">
                                        @lang('admin::app.mail.view.create-new-contact')
                                    </p>
                                </div>
                            </x-slot>

                            <x-slot:content>
                                <x-admin::attributes
                                    :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                        'entity_type' => 'persons',
                                    ])"
                                />
                            </x-slot>

                            <x-slot:footer>
                                <x-admin::button
                                    class="primary-button"
                                    :title="trans('admin::app.mail.view.save-contact')"
                                    ::loading="isStoring"
                                    ::disabled="isStoring"
                                />
                            </x-slot>
                        </x-admin::modal>
                    </form>
                </x-admin::form>
            </Teleport>

            {!! view_render_event('admin.mail.view.contact_form.after', ['email' => $email]) !!}
        </script>

        <script
            type="text/x-template"
            id="v-create-lead-template"
        >
            {!! view_render_event('admin.mail.view.lead_form.before', ['email' => $email]) !!}

            <Teleport to="body">
                <x-admin::form
                    v-slot="{ meta, errors, handleSubmit }"
                    as="div"
                >
                    <form
                        @submit="handleSubmit($event, create)"
                        ref="leadForm"
                    >
                        <!-- Add Contact Modal -->
                        <x-admin::modal
                            ref="leadModal"
                            @toggle="toggleModal"
                            size="large"
                        >
                            <x-slot:header>
                                <div class="flex items-center justify-between">
                                    <p class="text-xl font-semibold text-gray-800 dark:text-white">
                                        @lang('admin::app.mail.view.create-lead')
                                    </p>
                                </div>
                            </x-slot>

                            <x-slot:content>
                                <div class="flex flex-col gap-2">
                                    <div class="flex gap-2 border-b border-gray-200 dark:border-gray-800">
                                        <!-- Tabs -->
                                        <template
                                            v-for="type in types"
                                            :key="type.name"
                                        >
                                            <span
                                                :class="[
                                                    'inline-block px-3 py-2.5 border-b-2 cursor-pointer text-sm font-medium ',
                                                    selectedType == type.name
                                                    ? 'text-brandColor border-brandColor dark:brandColor dark:brandColor'
                                                    : 'text-gray-600 dark:text-gray-300  border-transparent hover:text-gray-800 hover:border-gray-400 dark:hover:border-gray-400  dark:hover:text-white'
                                                ]"
                                                @click="selectedType = type.name"
                                            >
                                                @{{ type.label }}
                                            </span>
                                        </template>
                                    </div>

                                    <!-- Container -->
                                    <div>
                                        <div v-show="selectedType == 'lead'">
                                            <div class="w-full">
                                                <div class="flex gap-4 max-sm:flex-wrap">
                                                    <div class="w-1/2">
                                                        <x-admin::attributes
                                                            :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                                                ['code', 'IN', ['title']],
                                                                'entity_type' => 'leads',
                                                                'quick_add'   => 1
                                                            ])"
                                                        />
                                                    </div>

                                                    <div class="w-1/2">
                                                        <!-- Lead value field has been removed -->
                                                    </div>
                                                </div>

                                                <div class="flex w-full gap-4 max-sm:flex-wrap">
                                                    <!-- Description -->
                                                    <x-admin::attributes
                                                        :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                                            ['code', 'IN', ['description']],
                                                            'entity_type' => 'leads',
                                                            'quick_add'   => 1
                                                        ])"
                                                    />
                                                </div>


                                                <div class="flex gap-4 max-sm:flex-wrap">
                                                    <div class="w-1/2">
                                                        <x-admin::attributes
                                                            :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                                                ['code', 'IN', ['lead_pipeline_id']],
                                                                'entity_type' => 'leads',
                                                                'quick_add'   => 1
                                                            ])"
                                                        />
                                                    </div>

                                                    <div class="w-1/2">
                                                        <x-admin::attributes
                                                            :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                                                ['code', 'IN', ['lead_pipeline_stage_id']],
                                                                'entity_type' => 'leads',
                                                                'quick_add'   => 1
                                                            ])"
                                                        />
                                                    </div>
                                                </div>

                                                <div class="flex gap-4 max-sm:flex-wrap">
                                                    <div class="w-1/2">
                                                        <x-admin::attributes
                                                            :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                                                ['code', 'IN', ['lead_type_id']],
                                                                'entity_type' => 'leads',
                                                                'quick_add'   => 1
                                                            ])"
                                                        />
                                                    </div>

                                                    <div class="w-1/2">
                                                        <x-admin::attributes
                                                            :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                                                ['code', 'IN', ['lead_source_id']],
                                                                'entity_type' => 'leads',
                                                                'quick_add'   => 1
                                                            ])"
                                                        />
                                                    </div>
                                                </div>

                                                <div class="flex gap-4 max-sm:flex-wrap">
                                                    <div class="w-1/2">
                                                        <x-admin::attributes
                                                            :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                                                ['code', 'IN', ['user_id']],
                                                                'entity_type' => 'leads',
                                                                'quick_add'   => 1
                                                            ])"
                                                        />
                                                    </div>

                                                    <div class="w-1/2">
                                                        <x-admin::attributes
                                                            :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                                                ['code', 'IN', []],
                                                                'entity_type' => 'leads',
                                                                'quick_add'   => 1
                                                            ])"
                                                            :custom-validations="[

                                                            ]"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div v-show="selectedType == 'person'">
                                            @include('admin::leads.common.contact')
                                        </div>

                                        <div
                                            class="overflow-y-auto"
                                            v-show="selectedType == 'product'"
                                        >
                                            <!-- Products functionality removed -->
                                        </div>
                                    </div>
                                </div>
                            </x-slot>

                            <x-slot:footer>
                                <x-admin::button
                                    class="primary-button"
                                    :title="trans('Save Lead')"
                                    ::loading="isStoring"
                                    ::disabled="isStoring"
                                />
                            </x-slot>
                        </x-admin::modal>
                    </form>
                </x-admin::form>
            </Teleport>

            {!! view_render_event('admin.mail.view.lead_form.after', ['email' => $email]) !!}
        </script>


        <!-- Email List Vue Component -->
        <script type="module">
            app.component('v-email-list', {
                template: '#v-email-list-template',

                data() {
                    return {
                        email: @json($email),
                        hierarchicalFolders: @json($hierarchicalFolders ?? []),

                        action: {},
                    };
                },

                mounted() {
                    this.$emitter.on('on-email-save', (email) => {
                        this.email.emails.push(email);

                        this.action = {};

                        setTimeout(() => this.scrollBottom(), 0);
                    });
                },

                methods: {
                    emailAction(action) {
                        this.action[action.email.id] = action;

                        if (! this.action.email) {
                            this.action.email = this.lastEmail();
                        }
                    },

                    scrollBottom() {
                        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

                        const windowHeight = window.innerHeight;

                        const scrollBottom = scrollTop + windowHeight;

                        window.scrollTo({
                            top: scrollBottom,
                            behavior: 'smooth',
                        });
                    },

                    lastEmail() {
                        if (
                            this.email.emails === undefined
                            || ! this.email.emails.length
                        ) {
                            return this.email;
                        }

                        return this.email.emails[this.email.emails.length - 1];
                    },
                },
            });
        </script>


        <!-- Email Form Vue Component -->
        <script type="module">
            app.component('v-email-form', {
                template: '#v-email-form-template',

                props: ['action', 'email'],

                data() {
                    return {
                        showCC: false,

                        showBCC: false,

                        isStoring: false,
                    };
                },

                computed: {
                    reply_to() {
                        if (this.getActionType == 'forward') {
                            return [];
                        }

                        // Extract email from from field if it's an object
                        const extractEmail = (value) => {
                            if (!value) return null;
                            if (typeof value === 'string') return value;
                            if (typeof value === 'object' && !Array.isArray(value)) {
                                return value.email || value.value || null;
                            }
                            return value;
                        };

                        const fromEmail = extractEmail(this.action.email.from);

                        if (this.getActionType == 'reply-all') {
                            const ccEmails = (this.action.email?.cc || []).map(extractEmail).filter(Boolean);
                            const bccEmails = (this.action.email?.bcc || []).map(extractEmail).filter(Boolean);
                            
                            return [
                                fromEmail,
                                ...ccEmails,
                                ...bccEmails,
                            ].filter(Boolean);
                        }

                        return fromEmail ? [fromEmail] : [];
                    },

                    cc() {
                        if (this.getActionType != 'reply-all') {
                            return [];
                        }

                        return this.action.email.cc;
                    },

                    bcc() {
                        if (this.getActionType != 'reply-all') {
                            return [];
                        }

                        return this.action.email.bcc;
                    },

                    reply() {
                        // For reply action, we need to include the original email content
                        if (this.getActionType == 'reply' || this.getActionType == 'reply-all') {
                            // Return empty string - content will be set via TinyMCE
                            // The original email content should be included in the reply
                            return '';
                        }
                        
                        if (this.getActionType == 'forward') {
                            return this.action.email.reply;
                        }

                        // For new emails (compose), add user signature if available
                        @if(auth()->guard('user')->user() && auth()->guard('user')->user()->signature)
                            return `{{ auth()->guard('user')->user()->signature }}`;
                        @else
                            return '';
                        @endif
                    },

                    getActionType() {
                        return this.action[this.email.id].type;
                    },
                },

                watch: {
                    reply(newVal) {
                        // When reply content changes, set it in TinyMCE
                        if (newVal && newVal.trim()) {
                            this.$nextTick(() => {
                                this.setContentInTinyMCE(newVal);
                            });
                        }
                    },
                    
                    'action.email.reply'(newVal) {
                        // When the original email reply content changes (e.g., when switching emails), update TinyMCE
                        if (this.getActionType == 'reply' || this.getActionType == 'reply-all') {
                            if (newVal && newVal.trim()) {
                                this.$nextTick(() => {
                                    this.setContentInTinyMCE(newVal);
                                });
                            }
                        }
                    },
                },

                mounted() {
                    // Set initial content in TinyMCE when component is mounted
                    // For reply/reply-all, use the original email content
                    let contentToSet = '';
                    if (this.getActionType == 'reply' || this.getActionType == 'reply-all') {
                        contentToSet = this.action.email?.reply || '';
                    } else if (this.reply && this.reply.trim()) {
                        contentToSet = this.reply;
                    }
                    
                    if (contentToSet) {
                        this.$nextTick(() => {
                            setTimeout(() => {
                                this.setContentInTinyMCE(contentToSet);
                            }, 500);
                        });
                    }
                },

                methods: {
                    setContentInTinyMCE(html, retries = 25) {
                        if (!html || !html.trim()) return;

                        // Check if TinyMCE is available and editor is ready
                        if (window.tinymce) {
                            try {
                                const editor = window.tinymce.get('reply');
                                if (editor && !editor.removed && editor.initialized) {
                                    // Editor exists, is not removed, and is initialized - set content
                                    editor.setContent(html);
                                    
                                    // Also update the underlying textarea value to keep it in sync
                                    const textarea = document.getElementById('reply');
                                    if (textarea) {
                                        textarea.value = html;
                                    }
                                    return;
                                }
                            } catch (e) {
                                // Editor not ready yet, continue to retry
                            }
                        }

                        // Retry if TinyMCE not ready yet
                        if (retries > 0) {
                            setTimeout(() => this.setContentInTinyMCE(html, retries - 1), 200);
                        }
                    },

                    save(params, { resetForm, setErrors  }) {
                        let formData = new FormData(this.$refs.mailActionForm);

                        this.isStoring = true;

                        this.$axios.post("{{ route('admin.mail.store') }}", formData, {
                                headers: {
                                    'Content-Type': 'multipart/form-data'
                                }
                            })
                            .then ((response) => {
                                this.isStoring = false;

                                this.$emitter.emit('on-email-save', response.data.data);

                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            })
                            .catch ((error) => {
                                this.isStoring = false;

                                if (error.response.status == 422) {
                                    setErrors(error.response.data.errors);
                                } else {
                                    this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                                }
                            });
                    },
                },
            });
        </script>

        <!-- Contact Lookup Component -->
        <script type="module">
            app.component('v-contact-lookup', {
                template: '#v-contact-lookup-template',

                props: ['email', 'unlinking', 'tagTextColor'],

                emits: ['link-contact', 'unlink-contact', 'open-contact-modal'],

                data() {
                    return {
                        showPopup: false,

                        searchTerm: '',

                        selectedItem: {},

                        searchedResults: [],

                        isSearching: false,

                        cancelToken: null,
                    };
                },

                mounted() {
                    if (this.value) {
                        this.selectedItem = this.value;
                    }
                    // Prefill search with sender email to suggest a match
                    try {
                        const raw = this.email?.from || '';
                        const match = String(raw).match(/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/);
                        if (match && match[0]) {
                            this.searchTerm = match[0];
                            this.showPopup = true;
                            this.$nextTick(() => this.search());
                        }
                    } catch (e) {}
                },

                created() {
                    window.addEventListener('click', this.handleFocusOut);
                },

                beforeUnmount() {
                    window.removeEventListener('click', this.handleFocusOut);
                },

                watch: {
                    searchTerm(newVal, oldVal) {
                        this.search();
                    },
                },

                computed: {
                    /**
                     * Filter the searchedResults based on the search query.
                     *
                     * @return {Array}
                     */
                    persons() {
                        const term = (this.searchTerm || '').toLowerCase();
                        const results = this.searchedResults.filter(item =>
                            (item.name || '').toLowerCase().includes(term)
                            || (Array.isArray(item.emails) && item.emails.some(e => (e.value || '').toLowerCase().includes(term)))
                        );
                        // If a person matches sender email exactly, move to top
                        try {
                            const sender = (this.email?.from || '').toLowerCase();
                            const senderEmail = (sender.match(/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/) || [])[0];
                            if (senderEmail) {
                                results.sort((a, b) => {
                                    const aHas = Array.isArray(a.emails) && a.emails.some(e => (e.value || '').toLowerCase() === senderEmail);
                                    const bHas = Array.isArray(b.emails) && b.emails.some(e => (e.value || '').toLowerCase() === senderEmail);
                                    return (aHas === bHas) ? 0 : (aHas ? -1 : 1);
                                });
                            }
                        } catch (e) {}
                        return results;
                    }
                },

                methods: {
                    /**
                     * Toggle the popup.
                     *
                     * @return {void}
                     */
                    toggle() {
                        this.showPopup = ! this.showPopup;

                        if (this.showPopup) {
                            this.$nextTick(() => this.$refs.searchInput.focus());
                        }
                    },

                    /**
                     * Select an item from the list.
                     *
                     * @param {Object} item
                     *
                     * @return {void}
                     */
                    linkContact(person) {
                        this.showPopup = false;

                        this.searchTerm = '';

                        this.selectedItem = person;

                        this.$emit('link-contact', person);
                    },

                    unlinkContact() {
                        this.selectedItem = {};

                        this.$emit('unlink-contact');
                    },

                    /**
                     * Initialize the items.
                     *
                     * @return {void}
                     */
                    search() {
                        if (this.searchTerm.length <= 2) {
                            this.searchedResults = [];

                            this.isSearching = false;

                            return;
                        }

                        this.isSearching = true;

                        if (this.cancelToken) {
                            this.cancelToken.cancel();
                        }

                        this.cancelToken = this.$axios.CancelToken.source();

                        this.$axios.get('{{ route('admin.contacts.persons.search') }}', {
                                params: {
                                    ...this.params,
                                    query: this.searchTerm
                                },
                                cancelToken: this.cancelToken.token,
                            })
                            .then(response => {
                                this.searchedResults = response.data.data;
                            })
                            .catch(error => {
                                if (! this.$axios.isCancel(error)) {
                                    console.error("Search request failed:", error);
                                }

                                this.isSearching = false;
                            })
                            .finally(() => this.isSearching = false);
                    },

                    /**
                     * Handle the focus out event.
                     *
                     * @param {Event} event
                     *
                     * @return {void}
                     */
                    handleFocusOut(event) {
                        const lookup = this.$refs.lookup;

                        if (
                            lookup &&
                            ! lookup.contains(event.target)
                        ) {
                            this.showPopup = false;
                        }
                    },

                    toggleContactModal() {
                        this.showPopup = false;

                        this.$emit('open-contact-modal');
                    },
                },
            });
        </script>

        <!-- Contact Lookup Component -->
        <script type="module">
            app.component('v-lead-lookup', {
                template: '#v-lead-lookup-template',

                props: ['email', 'unlinking', 'tagTextColor'],

                emits: ['link-lead', 'unlink-lead', 'open-lead-modal'],

                data() {
                    return {
                        showPopup: false,

                        searchTerm: '',

                        selectedItem: {},

                        searchedResults: [],

                        isSearching: false,

                        cancelToken: null,
                    };
                },

                mounted() {
                    if (this.value) {
                        this.selectedItem = this.value;
                    }
                    // Auto-fetch open leads for selected person when available
                    try {
                        if (this.email?.person_id) {
                            this.search();
                        }
                    } catch(e) {}
                },

                created() {
                    window.addEventListener('click', this.handleFocusOut);
                },

                beforeUnmount() {
                    window.removeEventListener('click', this.handleFocusOut);
                },

                watch: {
                    searchTerm(newVal, oldVal) {
                        this.search();
                    },
                },

                computed: {
                    /**
                     * Filter the searchedResults based on the search query.
                     *
                     * @return {Array}
                     */
                    leads() {
                        const term = (this.searchTerm || '').toLowerCase();
                        // Filter by name/title
                        let list = this.searchedResults.filter(item =>
                            ((item.name || item.title || '')).toLowerCase().includes(term)
                        );
                        // Exclude won/lost stages
                        list = list.filter(lead => {
                            const code = lead?.stage?.code || '';
                            return !(code.startsWith('won') || code.startsWith('lost'));
                        });
                        // If email has a selected person, filter to leads containing that person
                        const pid = this.$parent?.email?.person_id || this.email?.person_id || null;
                        if (pid) {
                            list = list.filter(lead => {
                                const arr = Array.isArray(lead.persons)
                                    ? lead.persons
                                    : (Array.isArray(lead.persons?.data) ? lead.persons.data : []);
                                return arr.some(p => p.id === pid);
                            });
                        }
                        return list;
                    },
                },

                methods: {
                    /**
                     * Toggle the popup.
                     *
                     * @return {void}
                     */
                    toggle() {
                        this.showPopup = ! this.showPopup;

                        if (this.showPopup) {
                            this.$nextTick(() => this.$refs.searchInput.focus());
                        }
                    },

                    /**
                     * Select an item from the list.
                     *
                     * @param {Object} item
                     *
                     * @return {void}
                     */
                    linkLead(lead) {
                        this.showPopup = false;

                        this.searchTerm = '';

                        this.selectedItem = lead;

                        this.$emit('link-lead', lead);
                    },

                    unlinkLead() {
                        this.selectedItem = {};

                        this.$emit('unlink-lead');
                    },

                    /**
                     * Initialize the items.
                     *
                     * @return {void}
                     */
                    search() {
                        const pid = this.email?.person_id || null;
                        if (!pid && this.searchTerm.length <= 2) {
                            this.searchedResults = [];
                            this.isSearching = false;
                            return;
                        }

                        this.isSearching = true;

                        if (this.cancelToken) {
                            this.cancelToken.cancel();
                        }

                        this.cancelToken = this.$axios.CancelToken.source();

                        const request = pid
                            ? this.$axios.get('{{ route('admin.leads.open_by_person', ['person' => ':id']) }}'.replace(':id', pid), {
                                cancelToken: this.cancelToken.token,
                              })
                            : this.$axios.get('{{ route('admin.leads.search') }}', {
                                params: {
                                    ...this.params,
                                    query: this.searchTerm
                                },
                                cancelToken: this.cancelToken.token,
                              });

                        request
                            .then(response => {
                                this.searchedResults = response.data.data;
                            })
                            .catch(error => {
                                if (! this.$axios.isCancel(error)) {
                                    console.error("Search request failed:", error);
                                }

                                this.isSearching = false;
                            })
                            .finally(() => this.isSearching = false);
                    },

                    /**
                     * Handle the focus out event.
                     *
                     * @param {Event} event
                     *
                     * @return {void}
                     */
                    handleFocusOut(event) {
                        const lookup = this.$refs.lookup;

                        if (
                            lookup &&
                            ! lookup.contains(event.target)
                        ) {
                            this.showPopup = false;
                        }
                    },

                    toggleLeadModal() {
                        this.showPopup = false;

                        this.$emit('open-lead-modal');
                    },
                },
            });
        </script>

        <!-- Create Contact Modal Component -->
        <script type="module">
            app.component('v-create-contact', {
                template: '#v-create-contact-template',

                data() {
                    return {
                        isStoring: false,
                    };
                },

                methods: {
                    toggleModal({ isActive }) {
                        if (! isActive) {
                            this.$parent.$refs.emailLinkDrawer.toggle();
                        }
                    },

                    create(params, { setErrors }) {
                        this.isStoring = true;

                        const formData = new FormData(this.$refs.contactForm);

                        this.$axios.post('{{ route('admin.contacts.persons.store') }}', formData)
                            .then(response => {
                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                                this.$refs.contactModal.close();
                            })
                            .catch(error => {
                                if (error.response.status == 422) {
                                    setErrors(error.response.data.errors);
                                } else {
                                    this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                                }
                            })
                            .finally(() => {
                                this.isStoring = false;

                                this.$parent.$refs.emailLinkDrawer.open();
                            });
                    },
                },
            });
        </script>

        <!-- Create Lead Modal Component -->
        <script type="module">
            app.component('v-create-lead', {
                template: '#v-create-lead-template',

                data() {
                    return {
                        isStoring: false,


                        selectedType: "lead",

                        types: [
                            {
                                name: 'lead',
                                label: "{{ trans('admin::app.mail.view.lead-details') }}",
                            }, {
                                name: 'person',
                                label: "{{ trans('admin::app.mail.view.contact-person') }}",
                            }, {
                                name: 'product',
                                label: "{{ trans('admin::app.mail.view.product') }}",
                            },
                        ],
                    };
                },

                methods: {
                    toggleModal({ isActive }) {
                        if (! isActive) {
                            this.$parent.$refs.emailLinkDrawer.toggle();
                        }
                    },

                    create(params, { setErrors }) {
                        this.isStoring = true;

                        const formData = new FormData(this.$refs.leadForm);

                        formData.append('lead_pipeline_stage_id', 1)

                        this.$axios.post('{{ route('admin.leads.store') }}', formData)
                            .then(response => {
                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                                this.$refs.leadModal.close();
                            })
                            .catch(error => {
                                if (error.response.status == 422) {
                                    setErrors(error.response.data.errors);
                                } else {
                                    this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                                }
                            })
                            .finally(() => {
                                this.isStoring = false;

                                this.$parent.$refs.emailLinkDrawer.open();
                            });
                    },
                },
            });
        </script>

        <!-- sales Lookup Component -->
        <script type="module">
            app.component('v-sales-lead-lookup', {
                template: '#v-sales-lead-lookup-template',

                props: ['email'],

                data() {
                    return {
                        showPopup: false,
                        isSearching: false,
                        searchTerm: '',
                        salesLeads: [],
                        selectedItem: {},
                    };
                },

                methods: {
                    toggle() {
                        this.showPopup = !this.showPopup;

                        if (this.showPopup) {
                            this.$nextTick(() => {
                                this.$refs.searchInput.focus();
                            });
                        }
                    },

                    search() {
                        this.isSearching = true;

                        this.$axios.get('{{ route('admin.sales-leads.search') }}', {
                            params: {
                                search: this.searchTerm,
                            }
                        })
                            .then(response => {
                                this.salesLeads = response.data;
                            })
                            .catch(error => {
                                this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                            })
                            .finally(() => {
                                this.isSearching = false;
                            });
                    },

                    linkSalesLead(salesLead) {
                        this.$emit('link-sales-lead', salesLead);
                        this.showPopup = false;
                        this.selectedItem = salesLead;
                    },
                },

                mounted() {
                    document.addEventListener('click', this.handleClick);
                },

                beforeUnmount() {
                    document.removeEventListener('click', this.handleClick);
                },

                updated() {
                    this.handleClick = (event) => {
                        if (!this.$refs.lookup.contains(event.target)) {
                            this.showPopup = false;
                        }
                    };
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
