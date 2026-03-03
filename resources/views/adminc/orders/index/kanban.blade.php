@props([
    'stages',
    'pipeline',
])

<x-adminc::components.kanban-abstract
    type="order"
    :stages="$stages"
    :pipeline="$pipeline"
/>

