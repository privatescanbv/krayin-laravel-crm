@php
    $additional = $activity->additional ?? [];
    $isImpersonation = isset($additional['action']) || isset($additional['admin']) || isset($additional['network']);
@endphp

<div class="flex w-full flex-1 flex-col gap-4 rounded-lg">
    <div class="box-shadow rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">

        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">AVG Audit Details</h3>

        @if($isImpersonation)

            {{-- Actie --}}
            @if(isset($additional['action']) || isset($additional['timestamp']))
                <div class="mb-4">
                    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Actie</h4>
                    <dl class="space-y-1">
                        @if(isset($additional['action']))
                            <div class="flex gap-2">
                                <dt class="w-32 shrink-0 text-sm text-gray-500 dark:text-gray-400">Actie</dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $additional['action'] }}</dd>
                            </div>
                        @endif
                        @if(isset($additional['timestamp']))
                            <div class="flex gap-2">
                                <dt class="w-32 shrink-0 text-sm text-gray-500 dark:text-gray-400">Tijdstip</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $additional['timestamp'] }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
                <hr class="mb-4 border-gray-200 dark:border-gray-700">
            @endif

            {{-- Patiënt --}}
            @if(isset($additional['person']) || isset($additional['patient_keycloak_id']))
                <div class="mb-4">
                    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Patiënt</h4>
                    <dl class="space-y-1">
                        @if(isset($additional['person']['name']))
                            <div class="flex gap-2">
                                <dt class="w-32 shrink-0 text-sm text-gray-500 dark:text-gray-400">Naam</dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $additional['person']['name'] }}</dd>
                            </div>
                        @endif
                        @if(isset($additional['patient_keycloak_id']))
                            <div class="flex gap-2">
                                <dt class="w-32 shrink-0 text-sm text-gray-500 dark:text-gray-400">Keycloak ID</dt>
                                <dd class="break-all text-sm text-gray-900 dark:text-gray-100">{{ $additional['patient_keycloak_id'] }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
                <hr class="mb-4 border-gray-200 dark:border-gray-700">
            @endif

            {{-- Beheerder --}}
            @if(isset($additional['admin']))
                <div class="mb-4">
                    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Beheerder</h4>
                    <dl class="space-y-1">
                        @if(isset($additional['admin']['name']))
                            <div class="flex gap-2">
                                <dt class="w-32 shrink-0 text-sm text-gray-500 dark:text-gray-400">Naam</dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $additional['admin']['name'] }}</dd>
                            </div>
                        @endif
                        @if(isset($additional['admin']['email']))
                            <div class="flex gap-2">
                                <dt class="w-32 shrink-0 text-sm text-gray-500 dark:text-gray-400">E-mail</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $additional['admin']['email'] }}</dd>
                            </div>
                        @endif
                        @if(isset($additional['admin']['id']))
                            <div class="flex gap-2">
                                <dt class="w-32 shrink-0 text-sm text-gray-500 dark:text-gray-400">ID</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $additional['admin']['id'] }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
                <hr class="mb-4 border-gray-200 dark:border-gray-700">
            @endif

            {{-- Netwerk --}}
            @if(isset($additional['network']))
                <div class="mb-4">
                    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Netwerk</h4>
                    <dl class="space-y-1">
                        @if(isset($additional['network']['ip_address']))
                            <div class="flex gap-2">
                                <dt class="w-32 shrink-0 text-sm text-gray-500 dark:text-gray-400">IP-adres</dt>
                                <dd class="text-sm font-mono text-gray-900 dark:text-gray-100">{{ $additional['network']['ip_address'] }}</dd>
                            </div>
                        @endif
                        @if(isset($additional['network']['forwarded_for']) && $additional['network']['forwarded_for'])
                            <div class="flex gap-2">
                                <dt class="w-32 shrink-0 text-sm text-gray-500 dark:text-gray-400">Forwarded for</dt>
                                <dd class="break-all text-sm font-mono text-gray-900 dark:text-gray-100">{{ $additional['network']['forwarded_for'] }}</dd>
                            </div>
                        @endif
                        @if(isset($additional['network']['all_ips']) && $additional['network']['all_ips'])
                            <div class="flex gap-2">
                                <dt class="w-32 shrink-0 text-sm text-gray-500 dark:text-gray-400">Alle IPs</dt>
                                <dd class="break-all text-sm font-mono text-gray-900 dark:text-gray-100">{{ is_array($additional['network']['all_ips']) ? implode(', ', $additional['network']['all_ips']) : $additional['network']['all_ips'] }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
                <hr class="mb-4 border-gray-200 dark:border-gray-700">
            @endif

            {{-- Browser --}}
            @if(isset($additional['client']))
                <div class="mb-4">
                    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Browser</h4>
                    <dl class="space-y-1">
                        @if(isset($additional['client']['user_agent']))
                            <div class="flex gap-2">
                                <dt class="w-32 shrink-0 text-sm text-gray-500 dark:text-gray-400">User Agent</dt>
                                <dd class="break-words text-sm text-gray-900 dark:text-gray-100">{{ $additional['client']['user_agent'] }}</dd>
                            </div>
                        @endif
                        @if(isset($additional['client']['referer']) && $additional['client']['referer'])
                            <div class="flex gap-2">
                                <dt class="w-32 shrink-0 text-sm text-gray-500 dark:text-gray-400">Referer</dt>
                                <dd class="break-all text-sm text-gray-900 dark:text-gray-100">{{ $additional['client']['referer'] }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
                <hr class="mb-4 border-gray-200 dark:border-gray-700">
            @endif

            {{-- Sessie --}}
            @if(isset($additional['session_id']) || isset($additional['duration_seconds']))
                <div class="mb-4">
                    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Sessie</h4>
                    <dl class="space-y-1">
                        @if(isset($additional['session_id']))
                            <div class="flex gap-2">
                                <dt class="w-32 shrink-0 text-sm text-gray-500 dark:text-gray-400">Sessie ID</dt>
                                <dd class="break-all text-sm font-mono text-gray-900 dark:text-gray-100">{{ $additional['session_id'] }}</dd>
                            </div>
                        @endif
                        @if(isset($additional['duration_seconds']))
                            <div class="flex gap-2">
                                <dt class="w-32 shrink-0 text-sm text-gray-500 dark:text-gray-400">Duur</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $additional['duration_seconds'] }} seconden</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif

        @else

            {{-- Fallback: old/new label change --}}
            @if(isset($additional['old']) || isset($additional['new']))
                <div class="mb-4">
                    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Wijziging</h4>
                    <dl class="space-y-1">
                        @if(isset($additional['old']['label']))
                            <div class="flex gap-2">
                                <dt class="w-20 shrink-0 text-sm text-gray-500 dark:text-gray-400">Van</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ strip_tags($additional['old']['label']) }}</dd>
                            </div>
                        @endif
                        @if(isset($additional['new']['label']))
                            <div class="flex gap-2">
                                <dt class="w-20 shrink-0 text-sm text-gray-500 dark:text-gray-400">Naar</dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    @if(isset($additional['new']['link']))
                                        <a href="{{ $additional['new']['link'] }}" class="hover:underline text-activity-note-text">
                                            {{ strip_tags($additional['new']['label']) }}
                                        </a>
                                    @else
                                        {{ strip_tags($additional['new']['label']) }}
                                    @endif
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">Geen aanvullende details beschikbaar.</p>
            @endif

        @endif

    </div>
</div>
