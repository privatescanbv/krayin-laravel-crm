@props([
    'sales'
])

<div class="flex w-full flex-col gap-4 rounded-lg">

    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Algemene informatie Sales</h3>

            <div class="direction-row flex items-center gap-4">
                @if (bouncer()->hasPermission('sales-leads.edit'))
                    <a href="{{ route('admin.sales-leads.edit', $sales->id) }}"
                        class="secondary-button flex items-center gap-1 border hover:border-neutral-text hover:text-neutral-text">
                        <span class="icon-edit text-base"></span><span>Bewerk sales</span>
                    </a>
                @endif

                @if (bouncer()->hasPermission('sales-leads.delete'))
                    <v-sales-delete delete-url="{{ route('admin.sales-leads.delete', $sales->id) }}"
                        redirect-url="{{ route('admin.sales-leads.index') }}" :sales-name='@json($sales->name)'></v-sales-delete>
                @endif
            </div>
        </div>
    </div>

    <!-- Stages Navigation -->
    @include('admin::leads.view.stages',[
        'overridePipeline' => $salesLead->stage->pipeline ?? $lead->pipeline,
        'overrideStage' => $salesLead->stage ?? $lead->stage,
        'overrideUpdateUrl' => route('admin.sales-leads.stage.update', $salesLead->id),
        'salesLead' => $salesLead,
    ])

    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between">
            <div class="direction-row flex items-center break-all">

                <read-more
                    :text='@json($sales->description ?? "")'
                    :lines="5"
                />
            </div>
        </div>
    </div>
    <x-adminc::leads.compact-overview :lead="$sales->lead" showViewLink="true" />

    @php
        $linkedPreventieSales = $sales->linkedPreventieSales()->with('stage')->get();
    @endphp

    @if ($linkedPreventieSales->isNotEmpty())
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-700 dark:bg-blue-900/20">
            <h4 class="mb-3 text-sm font-semibold text-blue-800 dark:text-blue-200">
                Gekoppelde Preventie Sales
            </h4>
            <div class="flex flex-col gap-2">
                @foreach ($linkedPreventieSales as $preventieSales)
                    <div class="flex items-center justify-between rounded border border-blue-100 bg-white px-3 py-2 dark:border-blue-800 dark:bg-gray-800">
                        <a href="{{ route('admin.sales-leads.view', $preventieSales->id) }}"
                           class="text-sm font-medium text-blue-700 hover:underline dark:text-blue-300">
                            {{ $preventieSales->name ?: 'Sales #' . $preventieSales->id }}
                        </a>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium
                            {{ $preventieSales->stage?->is_won ? 'bg-green-100 text-green-700' : ($preventieSales->stage?->is_lost ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600') }}">
                            {{ $preventieSales->stage?->name ?? 'Onbekend' }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @php
        $linkedHerniaSales = $sales->linkedHerniaSales()->with('stage')->get();
    @endphp

    @if ($linkedHerniaSales->isNotEmpty())
        <div class="rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-700 dark:bg-orange-900/20">
            <h4 class="mb-3 text-sm font-semibold text-orange-800 dark:text-orange-200">
                Gekoppelde Herniapoli Sales
            </h4>
            <div class="flex flex-col gap-2">
                @foreach ($linkedHerniaSales as $herniaSales)
                    <div class="flex items-center justify-between rounded border border-orange-100 bg-white px-3 py-2 dark:border-orange-800 dark:bg-gray-800">
                        <a href="{{ route('admin.sales-leads.view', $herniaSales->id) }}"
                           class="text-sm font-medium text-orange-700 hover:underline dark:text-orange-300">
                            {{ $herniaSales->name ?: 'Sales #' . $herniaSales->id }}
                        </a>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium
                            {{ $herniaSales->stage?->is_won ? 'bg-green-100 text-green-700' : ($herniaSales->stage?->is_lost ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600') }}">
                            {{ $herniaSales->stage?->name ?? 'Onbekend' }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Person Blocks Grid -->
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-2">
        @if ($sales->hasContactPerson())
            @include('adminc::persons.person', [
                'lead' => $sales,
                'person' => $sales->contactPerson,
                'isContactPerson' => true,
                'returnUrl' => route('admin.sales-leads.view', $sales->id),
            ])
        @endif

        <!-- Person Blocks - One for each person -->
        @foreach ($sales->persons as $person)
            @include('adminc::persons.person', [
                'lead' => $sales->lead,
                'person' => $person,
                'returnUrl' => route('admin.sales-leads.view', $sales->id),
            ])
        @endforeach
   </div>

</div>
