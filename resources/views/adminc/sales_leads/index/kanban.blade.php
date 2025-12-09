@props([
    'columns',
    'stages',
    'pipeline',
])

<x-adminc::components.kanban-abstract
    type="sales"
    :columns="$columns"
    :stages="$stages"
    :pipeline="$pipeline"
/>
