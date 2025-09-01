<x-admin::layouts>
    <!-- Page Title -->
    <x-slot:title>
        @lang('admin::app.activities.edit.title')
    </x-slot>

    {!! view_render_event('admin.activities.edit.form.before') !!}

    <x-admin::form
        :action="route('admin.activities.update', $activity->id)"
        method="PUT"
    >
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <!-- Breadcrumbs -->
                    <x-admin::breadcrumbs
                        name="activities.edit"
                        :entity="$activity"
                    />

                    <!-- Page Title -->
                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.activities.edit.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <!-- Takeover Button -->
                    @if($activity->user_id && $activity->user_id != auth()->guard('user')->id() && $canTakeover)
                        <button
                            type="button"
                            class="secondary-button bg-orange-500 hover:bg-orange-600 text-white"
                            onclick="takeoverActivity({{ $activity->id }})"
                            title="Overnemen van {{ $activity->user ? $activity->user->name : 'onbekend' }}"
                        >
                            Overnemen
                        </button>
                    @endif

                    <!-- Create button for person -->
                    <div class="flex items-center gap-x-2.5">
                        {!! view_render_event('admin.activities.edit.save_button.before') !!}

                        <!-- Save Button -->
                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('admin::app.activities.edit.save-btn')
                        </button>

                        {!! view_render_event('admin.activities.edit.save_button.after') !!}
                    </div>
                </div>
            </div>

            <!-- Form Content -->
            <div class="flex gap-2.5 max-xl:flex-wrap-reverse">
                <!-- Left sub-component -->
                <div class="box-shadow flex-1 gap-2 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 max-xl:flex-auto">
                    {!! view_render_event('admin.activities.edit.form_controls.before') !!}

                    <!-- Schedule Date -->
                    <x-admin::form.control-group>
                        <div class="flex gap-2 max-sm:flex-wrap">
                            <div class="w-full">
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.activities.edit.schedule_from')
                                </x-admin::form.control-group.label>

                                <x-admin::flat-picker.datetime class="!w-full" ::allow-input="true">
                                    <input
                                        name="schedule_from"
                                        value="{{ old('schedule_from') ?? $activity->schedule_from }}"
                                        class="flex w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                                        placeholder="@lang('admin::app.activities.edit.schedule_from')"
                                    />
                                </x-admin::flat-picker.datetime>
                            </div>

                            <div class="w-full">
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.activities.edit.schedule_to')
                                </x-admin::form.control-group.label>

                                <x-admin::flat-picker.datetime class="!w-full" ::allow-input="true">
                                    <input
                                        name="schedule_to"
                                        value="{{ old('schedule_to') ?? $activity->schedule_to }}"
                                        class="flex w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                                        placeholder="@lang('admin::app.activities.edit.schedule_to')"
                                    />
                                </x-admin::flat-picker.datetime>
                            </div>
                        </div>
                    </x-admin::form.control-group>

                    <!-- Comment -->
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.activities.edit.comment')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="textarea"
                            name="comment"
                            id="comment"
                            :value="old('comment') ?? $activity->comment"
                            :label="trans('admin::app.activities.edit.comment')"
                            :placeholder="trans('admin::app.activities.edit.comment')"
                        />

                        <x-admin::form.control-group.error control-name="comment" />
                    </x-admin::form.control-group>

                    <!-- Participants -->
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.activities.edit.participants')
                        </x-admin::form.control-group.label>

                        <x-admin::activities.actions.activity.participants 
                            :participants="[
                                'users' => $activity->participants->where('user_id', '!=', null)->pluck('user')->toArray(),
                                'persons' => $activity->participants->where('person_id', '!=', null)->pluck('person')->toArray()
                            ]"
                        />
                    </x-admin::form.control-group>
                    <!-- Group -->
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            {{ __('admin::app.activities.group') }}
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="select"
                            name="group_id"
                            :value="old('group_id', $activity->group_id)"
                        >
                            <option value="">{{ __('admin::app.activities.select-group') }}</option>
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}" {{ $activity->group_id == $group->id ? 'selected' : '' }}>
                                    {{ $group->name }}
                                </option>
                            @endforeach
                        </x-admin::form.control-group.control>
                    </x-admin::form.control-group>

                    <!-- Related Entity Information -->
                    @if($relatedEntity && $relatedEntityName)
                        <x-admin::form.control-group class="!mb-0">
                            <x-admin::form.control-group.label>
                                Gerelateerd aan
                            </x-admin::form.control-group.label>

                            <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-md border dark:bg-gray-800 dark:border-gray-700">
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    {{ $relatedEntityName }}:
                                </span>
                                <span class="text-sm text-gray-900 dark:text-gray-100">
                                    @if($relatedEntityName === 'Lead')
                                        <a href="{{ route('admin.leads.view', $relatedEntity->id) }}" class="text-blue-600 hover:text-blue-800 underline">
                                            {{ $relatedEntity->name ?? $relatedEntity->title ?? 'Onbekende lead' }}
                                        </a>
                                    @elseif($relatedEntityName === 'Person')
                                        <a href="{{ route('admin.contacts.persons.view', $relatedEntity->id) }}" class="text-blue-600 hover:text-blue-800 underline">
                                            {{ $relatedEntity->name ?? 'Onbekende persoon' }}
                                        </a>
                                    @elseif($relatedEntityName === 'Product')
                                        <a href="{{ route('admin.products.view', $relatedEntity->id) }}" class="text-blue-600 hover:text-blue-800 underline">
                                            {{ $relatedEntity->name ?? 'Onbekend product' }}
                                        </a>
                                    @elseif($relatedEntityName === 'Warehouse')
                                        <a href="{{ route('admin.warehouses.view', $relatedEntity->id) }}" class="text-blue-600 hover:text-blue-800 underline">
                                            {{ $relatedEntity->name ?? 'Onbekende warehouse' }}
                                        </a>
                                    @endif
                                </span>
                            </div>
                        </x-admin::form.control-group>
                    @endif

                    

                    <!-- is_done Checkbox -->
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.activities.edit.is_done')
                        </x-admin::form.control-group.label>
                        <input
                            type="checkbox"
                            name="is_done"
                            id="is_done"
                            value="1"
                            {{ old('is_done', $activity->is_done) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-brandColor shadow-sm focus:border-brandColor focus:ring focus:ring-brandColor focus:ring-opacity-50"
                        />
                        <label for="is_done" class="ml-2 text-sm text-gray-700 dark:text-gray-200">
                            @lang('admin::app.activities.edit.is_done-label')
                        </label>
                    </x-admin::form.control-group>

                    {!! view_render_event('admin.activities.edit.form_controls.after') !!}
                </div>

                <!-- Right sub-component -->
                <div class="w-[360px] max-w-full gap-2 max-xl:w-full">
                    {!! view_render_event('admin.activities.edit.accordion.general.before') !!}

                    <x-admin::accordion>
                        <x-slot:header>
                            <div class="flex items-center justify-between">
                                <p class="p-2.5 text-base font-semibold text-gray-800 dark:text-white">
                                    @lang('admin::app.activities.edit.general')
                                </p>
                            </div>
                        </x-slot>

                        <x-slot:content>
                            <!-- Title -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.activities.edit.title')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="text"
                                    name="title"
                                    id="title"
                                    rules="required"
                                    :value="old('title') ?? $activity->title"
                                    :label="trans('admin::app.activities.edit.title')"
                                    :placeholder="trans('admin::app.activities.edit.title')"
                                />

                                <x-admin::form.control-group.error control-name="title" />
                            </x-admin::form.control-group>

                            <!-- Edit Type -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.activities.edit.type')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="select"
                                    name="type"
                                    id="type"
                                    :value="old('type') ?? $activity->type"
                                    rules="required"
                                    :label="trans('admin::app.activities.edit.type')"
                                    :placeholder="trans('admin::app.activities.edit.type')"
                                >
                                    <option value="call">
                                        @lang('admin::app.activities.edit.call')
                                    </option>

                                    <option value="meeting">
                                        @lang('admin::app.activities.edit.meeting')
                                    </option>

                                    <option value="task">
                                        @lang('admin::app.activities.edit.task')
                                    </option>
                                </x-admin::form.control-group.control>

                                <x-admin::form.control-group.error control-name="type" />
                            </x-admin::form.control-group>

                            <!-- Location -->
                            <x-admin::form.control-group class="!mb-0">
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.activities.edit.location')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="text"
                                    name="location"
                                    id="location"
                                    :value="old('location') ?? $activity->location"
                                    :label="trans('admin::app.activities.edit.location')"
                                    :placeholder="trans('admin::app.activities.edit.location')"
                                />

                                <x-admin::form.control-group.error control-name="location" />
                            </x-admin::form.control-group>
                        </x-slot>
                    </x-admin::accordion>

                    {!! view_render_event('admin.activities.edit.accordion.general.after') !!}
                </div>
            </div>
        </div>
    </x-admin::form>

    {!! view_render_event('admin.activities.edit.form.after') !!}

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-multi-lookup-component-template"
        >
            <!-- Search Button -->
            <div class="relative">
                <div class="relative rounded border border-gray-200 px-2 py-1 hover:border-gray-400 focus:border-gray-400 dark:border-gray-800" role="button">
                    <ul class="flex flex-wrap items-center gap-1">
                        <!-- Added Participants -->
                        <template v-for="userType in ['users']">
                            <template v-if="! addedParticipants[userType].length">
                                <input
                                    type="hidden"
                                    :name="`participants[${userType}][]`"
                                    value=""
                                />
                            </template>

                            <li
                                class="flex items-center gap-1 rounded-md bg-slate-100 pl-2 dark:bg-slate-950 dark:text-gray-300"
                                v-for="(user, index) in addedParticipants[userType]"
                            >
                                <!-- Person and User Hidden Input Field -->
                                <input
                                    type="hidden"
                                    :name="`participants[${userType}][]`"
                                    :value="user.id"
                                />

                                @{{ user.name }}

                                <span
                                    class="icon-cross-large cursor-pointer p-0.5 text-xl"
                                    @click="remove(userType, user)"
                                ></span>
                            </li>
                        </template>

                        <!-- Search Input Box -->
                        <li>
                            <input
                                type="text"
                                class="w-full px-1 py-1 dark:bg-gray-900 dark:text-gray-300"
                                placeholder="@lang('admin::app.activities.edit.participants')"
                                v-model.lazy="searchTerm"
                                v-debounce="500"
                            />
                        </li>
                    </ul>

                    <!-- Search and Spinner Icon -->
                    <div>
                        <template v-if="! isSearching.users">
                            <span
                                class="absolute top-1.5 text-2xl ltr:right-1.5 rtl:left-1.5"
                                :class="[searchTerm.length >= 2 ? 'icon-up-arrow' : 'icon-down-arrow']"
                            ></span>
                        </template>

                        <template v-else>
                            <x-admin::spinner class="absolute top-2 ltr:right-2 rtl:left-2" />
                        </template>
                    </div>
                </div>

                <!-- Search Dropdown -->
                <div
                    class="absolute z-10 w-full rounded bg-white shadow-[0px_10px_20px_0px_#0000001F] dark:bg-gray-900"
                    v-if="searchTerm.length >= 2"
                >
                    <ul class="flex flex-col gap-1 p-2">
                        <!-- Users and Person Searched Participants -->
                        <li
                            class="flex flex-col gap-2"
                            v-for="userType in ['users']"
                        >
                            <h3 class="text-sm font-bold text-gray-600 dark:text-gray-400">
                                <template v-if="userType === 'users'">
                                    @lang('admin::app.activities.edit.users')
                                </template>

                                <template v-else>
                                    @lang('admin::app.activities.edit.persons')
                                </template>
                            </h3>

                            <ul>
                                <li
                                    class="rounded-sm px-5 py-2 text-sm text-gray-800 dark:text-gray-300"
                                    v-if="! searchedParticipants[userType].length && ! isSearching[userType]"
                                >
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        @lang('admin::app.activities.edit.no-result-found')
                                    </p>
                                </li>

                                <li
                                    class="cursor-pointer rounded-sm px-3 py-2 text-sm text-gray-800 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-950"
                                    v-for="user in searchedParticipants[userType]"
                                    @click="add(userType, user)"
                                >
                                    @{{ user.name }}
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-multi-lookup-component', {
                template: '#v-multi-lookup-component-template',

                props: {
                    onlyUsers: {
                        type: Boolean,
                        default: false,
                    },
                },

                data() {
                    return {
                        isSearching: {
                            users: false,
                        },

                        searchTerm: '',

                        addedParticipants: {
                            users: [],
                        },

                        searchedParticipants: {
                            users: [],
                        },

                        searchEnpoints: {
                            users: "{{ route('admin.settings.users.search') }}",
                        },
                    };
                },

                watch: {
                    searchTerm(newVal, oldVal) {
                        this.search('users');
                        // Alleen users zoeken
                    },
                },

                created() {
                    const participants = @json($activity->participants);
                    console.log('Participants data:', participants);
                    
                    participants.forEach(participant => {
                        console.log('Processing participant:', participant);
                        if (participant.user) {
                            console.log('Adding user:', participant.user);
                            this.addedParticipants.users.push(participant.user);
                        } else {
                            console.log('No user data for participant:', participant);
                        }
                    });
                    
                    console.log('Final addedParticipants:', this.addedParticipants);
                },

                methods: {
                    search(userType) {
                        if (userType !== 'users') return;
                        if (this.searchTerm.length <= 1) {
                            this.searchedParticipants.users = [];
                            this.isSearching.users = false;
                            return;
                        }

                        this.isSearching.users = true;

                        this.$axios.get(this.searchEnpoints.users, {
                                params: {
                                    search: 'name:' + this.searchTerm,
                                    searchFields: 'name:like',
                                }
                            })
                            .then ((response) => {
                                this.addedParticipants.users.forEach(addedParticipant =>
                                    response.data.data = response.data.data.filter(participant => participant.id !== addedParticipant.id)
                                );

                                this.searchedParticipants.users = response.data.data;

                                this.isSearching.users = false;
                            })
                            .catch (function (error) {
                                this.isSearching.users = false;
                            });
                    },

                    add(userType, participant) {
                        if (userType !== 'users') return;
                        this.addedParticipants.users.push(participant);
                        this.searchTerm = '';
                        this.searchedParticipants = { users: [] };
                    },

                    remove(userType, participant) {
                        if (userType !== 'users') return;
                        this.addedParticipants.users = this.addedParticipants.users.filter(addedParticipant =>
                            addedParticipant.id !== participant.id
                        );
                    },
                },
            });
        </script>

        <script>
            /**
             * Takeover activity from another user.
             *
             * @param {Number} activityId
             * @return {void}
             */
            window.takeoverActivity = async function(activityId) {
                if (!activityId) return;

                try {
                    const response = await fetch(`/admin/activities/${activityId}/takeover`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                    });

                    if (!response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.message || 'Er is een fout opgetreden bij het overnemen van de activiteit.');
                    }

                    // Success - show message and redirect
                    window.location.reload();

                } catch (error) {
                    // Show error message
                    alert(error.message);
                }
            };
        </script>
    @endPushOnce
</x-admin::layouts>
