@php use Illuminate\Support\Carbon; @endphp
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
    {{-- <span class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-indigo-600 dark:bg-indigo-500"></span> --}}

    <dl class="flex items-start gap-3">
        <dt class="sr-only">{{ $entityName }}</dt>
        <dd class="min-w-0 flex-1">
            <!-- Header sectie: naam + status -->
            <div class="mb-3">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-md font-bold text-gray-900 dark:text-gray-100 truncate">
                        {{ $entity->name }} @if ($age)
                            <span class="font-normal text-gray-500 dark:text-gray-400">({{ $age }} jaar)</span>
                        @endif
                    </h3>

                    @if ($showStatusBadge && $statusBadgeText)
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold tracking-wide
                             bg-indigo-50 text-indigo-700 border border-indigo-100
                             dark:bg-indigo-900/30 dark:text-indigo-300 dark:border-indigo-800">
                  {{ $statusBadgeText }}
                </span>
                    @endif
                </div>
            </div>

            <!-- Contact gegevens panel -->
            <div class="contact-panel flex flex-col gap-2">

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
                @if ($entity->date_of_birth)
                <div class="contact-row">
                    <span class="contact-icon">
                        <span class="icon-calendar text-xs"></span>
                    </span>
                    <span>
                        {{ $entity->date_of_birth?->format('d-m-Y') }}
                        <span class="contact-meta">({{ $entity->age }} jaar)</span>
                   </span>
                </div>
                @endif
                @if ($defaultPhone)
                    <a href="tel:{{ $defaultPhone['value'] ?? '' }}" class="contact-link">
               <span class="contact-icon">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                </span>
                        <span class="contact-value">{{ $defaultPhone['value'] ?? '' }}</span>
                    </a>
                @endif

                @if ($defaultEmail)
                    <a href="mailto:{{ $defaultEmail['value'] ?? '' }}" class="contact-link">
                <span class="contact-icon">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </span>
                        <span class="contact-value">{{ $defaultEmail['value'] ?? '' }}</span>
                    </a>
                @endif
            </div>

            <!-- Uitklapbare sectie voor overige contactgegevens -->
            @if (($otherPhones && $otherPhones->count() > 0) || ($otherEmails && $otherEmails->count() > 0))
                <div class="mt-3">
                    <button type="button"
                            onclick="toggleContactDetails('contact-{{ $entity->id }}')"
                            class="contact-toggle">
                        <svg id="icon-contact-{{ $entity->id }}" class="w-3 h-3 transition-transform duration-200"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 9l-7 7-7-7"></path>
                        </svg>
                        Overige contactgegevens
                    </button>

                    <div id="contact-{{ $entity->id }}" class="contact-extra hidden">
                        @if ($otherPhones && $otherPhones->count() > 0)
                            <div class="pl-5 space-y-1">
                                @foreach ($otherPhones as $phone)
                                    <a href="tel:{{ $phone['value'] ?? '' }}"
                                       class="contact-subrow">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                        </svg>
                                        @if (!empty($phone['label']))
                                            <span class="contact-label">({{ $phone['label'] }})</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        @if ($otherEmails && $otherEmails->count() > 0)
                            <div class="pl-5 space-y-1">
                                @foreach ($otherEmails as $email)
                                    <a href="mailto:{{ $email['value'] ?? '' }}"
                                       class="contact-subrow">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                        </svg>
                                        @if (!empty($email['value']))
                                            <span class="contact-label">({{ $email['value'] }})</span>
                                        @endif
                                        {{ $email['value'] ?? '' }}
                                        @if (isset($email['label']) && $email['label'])
                                            <span class="contact-label">({{ $email['label'] }})</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif

        </dd>
    </dl>
</div>

@pushOnce('scripts')
    <script>
        window.toggleContactDetails = window.toggleContactDetails || function (contactId) {
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
            } catch (e) {
            }
        };
    </script>
@endPushOnce
