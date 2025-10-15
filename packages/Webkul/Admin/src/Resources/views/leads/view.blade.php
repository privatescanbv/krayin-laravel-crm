<x-admin::layouts>
    <x-slot:title>
        {{ $lead->name }}
    </x-slot>

    <!-- Content -->
    <div class="relative flex gap-4 max-lg:flex-wrap">
        <!-- Left Panel -->
        {!! view_render_event('admin.leads.view.left.before', ['lead' => $lead]) !!}

        <div class="max-lg:min-w-full max-lg:max-w-full [&>div:last-child]:border-b-0 lg:sticky lg:top-[73px] flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <!-- Lead Information -->
            <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                <!-- Breadcrumb's -->
                <div class="flex items-center justify-between">
                    <x-admin::breadcrumbs
                        name="leads.view"
                        :entity="$lead"
                    />
                </div>

                <div class="mb-2">
                    @if (($days = $lead->rotten_days) > 0)
                        @php
                            $lead->tags->prepend([
                                'name'  => '<span class="icon-rotten text-base"></span>' . trans('admin::app.leads.view.rotten-days', ['days' => $days]),
                                'color' => '#FEE2E2'
                            ]);
                        @endphp
                    @endif

                    {!! view_render_event('admin.leads.view.tags.before', ['lead' => $lead]) !!}

                    <!-- Tags -->
                    <x-admin::tags
                        :attach-endpoint="route('admin.leads.tags.attach', $lead->id)"
                        :detach-endpoint="route('admin.leads.tags.detach', $lead->id)"
                        :added-tags="$lead->tags"
                    />

                    {!! view_render_event('admin.leads.view.tags.after', ['lead' => $lead]) !!}
                </div>


                {!! view_render_event('admin.leads.view.title.before', ['lead' => $lead]) !!}

                <!-- Title -->
{{--                <h3 class="text-lg font-bold dark:text-white">--}}
{{--                    {{ $lead->name }}--}}
{{--                </h3>--}}

                {!! view_render_event('admin.leads.view.title.after', ['lead' => $lead]) !!}

                <!-- Duplicate Detection -->
                @if($lead->hasPotentialDuplicates())
                    <div class="mb-4 rounded-lg border border-orange-200 bg-orange-50 p-3 dark:border-orange-800 dark:bg-orange-900/20">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="icon-warning text-orange-600"></span>
                                <span class="text-sm font-medium text-orange-800 dark:text-orange-200">
                                    Potentiële duplicaten gevonden ({{ $lead->getPotentialDuplicatesCount() }} leads{{ $lead->getPotentialDuplicatesCount() > 1 ? 's' : '' }})
                                </span>
                            </div>
                            <a
                                href="{{ route('admin.leads.duplicates.index', $lead->id) }}"
                                class="rounded bg-orange-600 px-3 py-1 text-xs text-white hover:bg-orange-700"
                            >
                                Duplicaten samenvoegen
                            </a>
                        </div>
                    </div>
                @endif

                <!-- No Open Activities Warning (shown directly below duplicate block) -->
                @php
                    $isWonOrLost = ($lead->stage->is_won ?? false) || ($lead->stage->is_lost ?? false);
                @endphp
                @if(($lead->open_activities_count ?? $lead->openActivitiesCount ?? $lead->open_activities_count) === 0 && ! $isWonOrLost)
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                        <div class="flex items-center gap-2">
                            <span class="icon-warning text-red-600"></span>
                            <span class="text-sm font-medium text-red-800 dark:text-red-200">
                                Geen open activiteiten voor deze lead
                            </span>
                        </div>
                    </div>
                @endif

                <!-- Activity Actions -->
                <div class="flex flex-wrap gap-2">
                    {!! view_render_event('admin.leads.view.actions.before', ['lead' => $lead]) !!}

                    @if (bouncer()->hasPermission('mail.compose'))
                        <!-- Mail Activity Action -->
                        <x-admin::activities.actions.mail
                            :entity="$lead"
                            entity-control-name="lead_id"
                        />
                    @endif

                    @if (bouncer()->hasPermission('activities.create'))
                        <!-- File Activity Action -->
                        <x-admin::activities.actions.file
                            :entity="$lead"
                            entity-control-name="lead_id"
                        />

                        <!-- Note Activity Action -->
                        <x-admin::activities.actions.note
                            :entity="$lead"
                            entity-control-name="lead_id"
                        />

                        <!-- Activity Action -->
                        <x-admin::activities.actions.activity
                            :entity="$lead"
                            entity-control-name="lead_id"
                        />
                    @endif

                    {!! view_render_event('admin.leads.view.actions.after', ['lead' => $lead]) !!}
                </div>
            </div>

            @include('admin::leads.common.card', ['lead' => $lead, 'show_actions'=>false])

            <!-- Lead Overview (compact overview with all information) -->
            @include ('admin::leads.view.compact-overview')

            <!-- Contact Person -->
            @include ('admin::leads.view.person')

            <!-- Footer with creation and modification dates -->
            <div class="flex w-full flex-col gap-2 p-4 text-xs text-gray-500 dark:text-gray-400 border-t border-gray-200 dark:border-gray-800">
                <div class="flex justify-between">
                    <span>Aangemaakt:</span>
                    <span>{{ $lead->created_at->format('d-m-Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Laatst gewijzigd:</span>
                    <span>{{ $lead->updated_at->format('d-m-Y') }}</span>
                </div>
            </div>
        </div>

        {!! view_render_event('admin.leads.view.left.after', ['lead' => $lead]) !!}

        {!! view_render_event('admin.leads.view.right.before', ['lead' => $lead]) !!}

        <!-- Right Panel -->
        <div class="flex w-full flex-col gap-4 rounded-lg">
            <!-- Stages Navigation -->
            @include ('admin::leads.view.stages')

            <!-- Activities -->
            {!! view_render_event('admin.leads.view.activities.before', ['lead' => $lead]) !!}

            <x-admin::activities
                :endpoint="route('admin.leads.activities.index', $lead->id)"
                :email-detach-endpoint="route('admin.leads.emails.detach', $lead->id)"
                :activeType="'planned'"
                :extra-types="[
                    ['name' => 'description', 'label' => trans('admin::app.leads.view.tabs.description')],
                ]"
            >
                <!-- Description -->
                <x-slot:description>
                    <div class="p-4 dark:text-white">
                        {{ $lead->description }}
                    </div>
                </x-slot>
            </x-admin::activities>

            {!! view_render_event('admin.leads.view.activities.after', ['lead' => $lead]) !!}
        </div>

        {!! view_render_event('admin.leads.view.right.after', ['lead' => $lead]) !!}
    </div>
</x-admin::layouts>
