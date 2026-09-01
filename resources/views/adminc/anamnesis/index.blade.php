@props(['person'])
@php
    use App\Services\Anamnesis\AnamnesisFormsOverviewBuilder;

    $builder = app(AnamnesisFormsOverviewBuilder::class);
    $chains = $builder->chainsForPerson($person);
    $returnUrlAnamnese = strtok(url()->current(), '#') . '#anamnese';
@endphp

<div class="flex w-full flex-col gap-4 rounded-lg">

    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Anamnese</h3>
        </div>
    </div>

    @if ($chains->isNotEmpty())
        @foreach ($chains as $chain)
            <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                @if ($chains->count() > 1)
                    <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            @if ($chain['lead'])
                                {{ $chain['lead']->name }}
                            @elseif ($chain['entity_type'] === 'sales')
                                Sales: {{ $chain['entity']->name ?? '#'.$chain['entity']->id }}
                            @else
                                Order: {{ $chain['entity']->title ?? $chain['entity']->name ?? '#'.$chain['entity']->id }}
                            @endif
                        </span>
                    </div>
                @endif

                <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                    <x-adminc::anamnesis.forms-overview
                        :entity="$chain['entity']"
                        :entityType="$chain['entity_type']"
                        :person="$person"
                        :effectiveAnamnesis="$chain['effective_anamnesis']"
                        :personHasPortalAccount="!empty($person->keycloak_user_id)"
                        :showDiagnosisAttach="$chain['hernia_sales_lead'] !== null"
                        :salesLead="$chain['hernia_sales_lead']"
                        :returnUrl="$returnUrlAnamnese"
                    />
                </div>

                <x-adminc::anamnesis.card
                    :person="$person"
                    :anamnesis="$chain['effective_anamnesis']"
                    :showCreatedDate="true"
                />
            </div>
        @endforeach
    @else
        <div class="rounded-lg border border-neutral-border bg-neutral-muted p-6 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
            Geen anamneses gevonden voor deze persoon.
        </div>
    @endif
</div>
