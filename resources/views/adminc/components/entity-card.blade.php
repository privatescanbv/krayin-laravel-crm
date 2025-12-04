@props([
    'entity',
    'entityName' => 'entity',
    'viewRoute' => '#',
    'viewButtonText' => 'Bekijk',
    'showStatusBadge' => false,
    'statusBadgeText' => null,
    'showActions' => true,
    'age' => null
])

<div class="relative">
    <!-- accent links -->
    <span class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-indigo-600 dark:bg-indigo-500"></span>

<dt class="flex items-start gap-3">

<dd class="min-w-0 flex-1">
    <div class="flex items-center justify-between gap-2">
        <h3 class="text-md font-semibold text-gray-900 dark:text-gray-100 truncate">
            {{ $entity->name }} @if ($age)( {{ $age }} jaar)@endif
        </h3>

        @if ($showStatusBadge && $statusBadgeText)
            <!-- status badge (optioneel) -->
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium
                         bg-white text-indigo-700 border border-indigo-200
                         dark:bg-indigo-900/40 dark:text-indigo-200 dark:border-indigo-700">
              {{ $statusBadgeText }}
            </span>
        @endif
    </div>

    <!-- Default contact info onder naam -->
    <div class="mt-2 flex flex-col items-start gap-2 text-sm">
        @php
            $defaultPhone = null;
            $defaultEmail = null;
            $otherPhones = collect();
            $otherEmails = collect();

            if($entity->phones && is_array($entity->phones) && count($entity->phones) > 0) {
                $defaultPhone = collect($entity->phones)->firstWhere('is_default', true) ?? collect($entity->phones)->first();
                $otherPhones = collect($entity->phones)->reject(function($phone) use ($defaultPhone) {
                    return $defaultPhone && isset($defaultPhone['value']) && ($phone['value'] ?? null) === ($defaultPhone['value'] ?? null);
                });
            }

            if($entity->emails && is_array($entity->emails) && count($entity->emails) > 0) {
                $defaultEmail = collect($entity->emails)->firstWhere('is_default', true) ?? collect($entity->emails)->first();
                $otherEmails = collect($entity->emails)->reject(function($email) use ($defaultEmail) {
                    return $email['value'] === $defaultEmail['value'];
                });
            }
        @endphp

        @if ($defaultPhone)
            <a href="tel:{{ $defaultPhone['value'] ?? '' }}"
               class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                </svg>
                {{ $defaultPhone['value'] ?? '' }}
            </a>
        @endif

        @if ($defaultEmail)
            <a href="mailto:{{ $defaultEmail['value'] ?? '' }}"
               class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                {{ $defaultEmail['value'] ?? '' }}
            </a>
        @endif
    </div>

    <!-- Uitklapbare sectie voor overige contactgegevens -->
    @if (($otherPhones && $otherPhones->count() > 0) || ($otherEmails && $otherEmails->count() > 0))
        <div class="mt-3">
            <button type="button"
                    onclick="toggleContactDetails('contact-{{ $entity->id }}')"
                    class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                <svg id="icon-contact-{{ $entity->id }}" class="w-3 h-3 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
                Overige contactgegevens
            </button>

            <div id="contact-{{ $entity->id }}" class="hidden mt-2 space-y-1 text-xs">
                @if ($otherPhones && $otherPhones->count() > 0)
                    <div class="pl-5 space-y-1">
                        @foreach ($otherPhones as $phone)
                            <a href="tel:{{ $phone['value'] ?? '' }}"
                               class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                                {{ $phone['value'] ?? '' }}
                                @if (isset($phone['label']) && $phone['label'])
                                    <span class="text-gray-400 dark:text-gray-500">({{ $phone['label'] }})</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif

                @if ($otherEmails && $otherEmails->count() > 0)
                    <div class="pl-5 space-y-1">
                        @foreach ($otherEmails as $email)
                            <a href="mailto:{{ $email['value'] ?? '' }}"
                               class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                {{ $email['value'] ?? '' }}
                                @if (isset($email['label']) && $email['label'])
                                    <span class="text-gray-400 dark:text-gray-500">({{ $email['label'] }})</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif


</div>

@pushOnce('scripts')
<script>
window.toggleContactDetails = window.toggleContactDetails || function(contactId) {
    try {
        var element = document.getElementById(contactId);
        var icon = document.getElementById('icon-' + contactId);
        if (!element || !icon) return;
        if (element.classList.contains('hidden')) {
            element.classList.remove('hidden');
            icon.style.transform = 'rotate(180deg)';
        } else {
            element.classList.add('hidden');
            icon.style.transform = 'rotate(0deg)';
        }
    } catch (e) {}
};
</script>
@endPushOnce
