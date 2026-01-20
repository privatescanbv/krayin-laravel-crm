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
                        class="secondary-button flex items-center gap-1 border hover:border-neutral-text hover:text-neutral-text"><
                            class="icon-edit text-base"><span>Bewerk sales</span></a>
                @endif

                @if (bouncer()->hasPermission('sales-leads.delete'))
                    <v-sales-delete delete-url="{{ route('admin.sales-leads.delete', $sales->id) }}"
                        redirect-url="{{ route('admin.sales-leads.index') }}" sales-name="{{ $sales->name }}"></v-sales-delete>
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

    <!-- Person Blocks Grid -->
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-2">
        @if ($sales->hasContactPerson())
            @include('adminc::persons.person', [
                'lead' => $sales,
                'person' => $sales->contactPerson,
                'isContactPerson' => true
            ])
        @endif

        <!-- Person Blocks - One for each person -->
        @foreach ($sales->persons as $person)
            @include('adminc::persons.person', ['lead' => $sales->lead, 'person' => $person])
        @endforeach
   </div>

</div>
