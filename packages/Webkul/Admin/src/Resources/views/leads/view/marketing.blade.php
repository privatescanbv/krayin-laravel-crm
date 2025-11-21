@php
// Marketing view - Lead & Campagne Details
// Format date to Dutch format: "23 sept 2024"
$createdDate = $lead->created_at;
$monthNames = [
1 => 'jan', 2 => 'feb', 3 => 'mrt', 4 => 'apr', 5 => 'mei', 6 => 'jun',
7 => 'jul', 8 => 'aug', 9 => 'sept', 10 => 'okt', 11 => 'nov', 12 => 'dec'
];
$formattedDate = $createdDate->format('d') . ' ' . $monthNames[(int)$createdDate->format('n')] . ' ' . $createdDate->format('Y');

// Get lead data
$requestType = $lead->type->name ?? 'Onbekend';
$leadSource = $lead->source->name ?? 'Onbekend';
$campaign = $lead->channel->name ?? 'Onbekend';

// Check if lead is qualified (has stage and not lost/won, or has certain status)
$isQualified = $lead->stage && !$lead->closed_at;
// Check if campaign is active (has channel)
$hasActiveCampaign = $lead->channel !== null;
@endphp

{!! view_render_event('admin.leads.view.marketing.before', ['lead' => $lead]) !!}

<div class="flex w-full flex-col gap-4">
    <!-- Top Block: Marketing Informatie -->
    <div class="rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900 p-6">
        <div class="flex items-start justify-between">
            <div class="flex items-start gap-4">
                <!-- Icon -->
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-200 dark:bg-gray-700">
                    <span class="icon-activity text-2xl text-gray-600 dark:text-gray-300"></span>
                </div>

                <!-- Title and Subtitle -->
                <div class="flex flex-col gap-2">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Marketing Informatie</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Lead bron en campagne details</p>

                    <!-- Status Tags -->
                    <div class="flex items-center gap-3 mt-2">
                        @if($isQualified)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-neutral-bg dark:bg-gray-800 text-sm text-gray-700 dark:text-gray-300">
                            <span class="w-2 h-2 rounded-full bg-succes"></span>
                            <span>Lead gekwalificeerd</span>
                        </span>
                        @endif

                        @if($hasActiveCampaign)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-neutral-bg dark:bg-gray-800 text-sm text-gray-700 dark:text-gray-300">
                            <span class="icon-activity text-xs text-gray-600 dark:text-gray-400"></span>
                            <span>Actieve campagne</span>
                        </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Marketing Report Button -->
            <button
                type="button"
                class="flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-300 transition-colors">
                <span class="icon-download text-base"></span>
                <span class="text-sm font-medium">Marketing rapport</span>
            </button>
        </div>
    </div>

    <!-- Bottom Block: Lead & Campagne Details -->
    <div class="rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
            <div class="flex items-center gap-3">
                <span class="icon-menu text-xl text-gray-600 dark:text-gray-400"></span>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Lead & Campagne Details</h3>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <span class="icon-calendar text-base"></span>
                <span>Lead aangemaakt: {{ $formattedDate }}</span>
            </div>
        </div>

        <!-- Three Column Layout -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-4">
            <!-- Column 1: AANVRAAG TYPE -->
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-2 mb-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/30">
                        <span class="icon-document text-lg text-purple-600 dark:text-purple-400"></span>
                    </div>
                    <h4 class="text-sm font-semibold uppercase text-gray-700 dark:text-gray-300">AANVRAAG TYPE</h4>
                </div>

                <!-- Soort aanvraag -->
                <div class="relative mb-1">

                        <input
                            type="text"
                            class="w-full pr-8"
                            value="{{ $requestType }}"
                            readonly />
                    <label class="">

                        <span>Soort aanvraag</span>
                    </label>
                </div>
            </div>

            <!-- Column 2: LEAD HERKOMST -->
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-2 mb-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                        <span class="icon-target text-lg text-activity-note-text dark:text-blue-400"></span>
                    </div>
                    <h4 class="text-sm font-semibold uppercase text-gray-700 dark:text-gray-300">LEAD HERKOMST</h4>
                </div>

                <!-- Bron voor lead -->
                <div class="relative mb-1">

                        <input
                            type="text"
                            class="w-full pr-8"
                            value="{{ $leadSource }}"
                            readonly />

                                        <label class="">

                        <span>Bron voor lead</span>
                    </label>
                </div>
            </div>

            <!-- Column 3: MARKETING CAMPAGNE -->
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-2 mb-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                        <span class="icon-grid text-lg text-status-active-text dark:text-green-400"></span>
                    </div>
                    <h4 class="text-sm font-semibold uppercase text-gray-700 dark:text-gray-300">MARKETING CAMPAGNE</h4>
                </div>

                <!-- Campagne -->
                <div class="relative mb-1">

                        <input
                            type="text"
                            class="w-full pr-8"
                            value="{{ $campaign }}"
                            readonly />
                    <label class="">

                        <span>Campagne</span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

{!! view_render_event('admin.leads.view.marketing.after', ['lead' => $lead]) !!}
