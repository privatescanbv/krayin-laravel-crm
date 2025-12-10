@props([
    'stages',
    'pipeline',
])

<x-adminc::components.kanban-abstract
    type="sales"
    :stages="$stages"
    :pipeline="$pipeline"
/>
