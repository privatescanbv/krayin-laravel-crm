@props(['type', 'size' => 'text-lg', 'class' => ''])

@php
    $activityType = $type?->value ?? $type;
    $iconClass = match($activityType) {
        'call' => 'icon-call',
        'email' => 'icon-mail',
        'note' => 'icon-note',
        'meeting', 'task' => 'icon-activity',
        'file' => 'icon-file',
        'system' => 'icon-system-generate',
        default => 'icon-activity'
    };
@endphp

<span class="{{ $iconClass }} {{ $size }} {{ $class }}"></span>