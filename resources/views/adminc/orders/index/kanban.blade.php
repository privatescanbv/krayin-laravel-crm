@props([
    'stages',
    'pipeline',
])

<x-adminc::components.kanban-abstract
    type="orders"
    :stages="$stages"
    :pipeline="$pipeline"
/>

