@props([
    'gvlFormLink' => null,
    'attachUrl' => null,
    'detachUrl' => null,
    'statusUrl' => null,
    'entityId' => null,
    'entityType' => 'order', // 'order', 'anamnesis', or 'person'
    'personId' => null,
    'leadId' => null,
])

<x-admin::form.control-group>
    <x-admin::form.control-group.label>GVL Formulier Link</x-admin::form.control-group.label>
    <div class="flex items-center gap-2">
        <x-admin::form.control-group.control
            type="text"
            name="gvl_form_link"
            value="{{ $gvlFormLink ?? '' }}"
            class="flex-1"
            readonly
        />
        @if($gvlFormLink)
            <button
                type="button"
                id="reset-gvl-form-link-{{ $entityType }}-{{ $entityId }}"
                class="secondary-button"
                title="Ontkoppel GVL formulier"
                data-detach-url="{{ $detachUrl }}"
                data-entity-type="{{ $entityType }}"
                data-entity-id="{{ $entityId }}"
            >
                Ontkoppel
            </button>
        @else
            <button
                type="button"
                id="attach-gvl-form-link-{{ $entityType }}-{{ $entityId }}"
                class="primary-button"
                title="Koppel GVL formulier"
                data-attach-url="{{ $attachUrl }}"
                data-entity-type="{{ $entityType }}"
                data-entity-id="{{ $entityId }}"
                @if($personId) data-person-id="{{ $personId }}" @endif
                @if($leadId) data-lead-id="{{ $leadId }}" @endif
            >
                Koppelen
            </button>
        @endif
    </div>
    <x-admin::form.control-group.error control-name="gvl_form_link" />
    @if($gvlFormLink)
        <div class="mt-1 flex items-center gap-2">
            <p class="text-xs text-gray-600 dark:text-gray-400">
                <a href="{{ $gvlFormLink }}" target="_blank" class="text-activity-note-text hover:underline">
                    Open formulier
                </a>
            </p>
            <span class="text-xs text-gray-400">|</span>
            <div id="gvl-form-status-{{ $entityType }}-{{ $entityId }}" class="text-xs">
                <span class="text-gray-500">Status: </span>
                <span id="gvl-form-status-value-{{ $entityType }}-{{ $entityId }}" class="font-medium">Laden...</span>
            </div>
        </div>
    @endif
</x-admin::form.control-group>

@pushOnce('scripts')
<script type="module">
    (function() {
        const entityType = '{{ $entityType }}';
        const entityId = '{{ $entityId }}';
        const attachButtonId = `attach-gvl-form-link-${entityType}-${entityId}`;
        const detachButtonId = `reset-gvl-form-link-${entityType}-${entityId}`;
        const statusContainerId = `gvl-form-status-${entityType}-${entityId}`;
        const statusValueId = `gvl-form-status-value-${entityType}-${entityId}`;

        const getEmitter = () => window.app?.config?.globalProperties?.$emitter || window.app?.$emitter;

        const emitFlash = (type, message) => {
            const emitter = getEmitter();
            if (emitter) {
                try {
                    emitter.emit('add-flash', { type, message });
                } catch (err) {
                    console.error('[GVLForm] Flash emit failed', err, message);
                }
            } else {
                (type === 'error' ? console.error : console.log)(message);
            }
        };

        const getCsrfToken = () => {
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (metaToken) return metaToken;
            const inputToken = document.querySelector('input[name="_token"]')?.value;
            if (inputToken) return inputToken;
            return '';
        };

        const setButtonLoadingState = (button, isLoading, loadingLabel = 'Bezig...') => {
            if (!button) return;

            if (isLoading) {
                const originalLabel = button.dataset.originalLabel || button.textContent.trim();
                button.dataset.originalLabel = originalLabel;
                button.textContent = loadingLabel;
                button.dataset.loading = 'true';
                button.classList.add('pointer-events-none', 'opacity-60');
            } else {
                button.dataset.loading = 'false';
                if (button.dataset.originalLabel) {
                    button.textContent = button.dataset.originalLabel;
                }
                button.classList.remove('pointer-events-none', 'opacity-60');
            }
        };

        // Attach handler
        document.addEventListener('click', async function (e) {
            if (e.target && e.target.id === attachButtonId) {
                e.preventDefault();
                const button = e.target;

                if (button.dataset.loading === 'true') return;

                const attachUrl = button.dataset.attachUrl;
                if (!attachUrl) {
                    emitFlash('error', 'Koppel-URL ontbreekt.');
                    return;
                }

                setButtonLoadingState(button, true);

                // Prepare request body for person type (create and attach)
                let requestBody = {};
                if (button.dataset.entityType === 'person' && button.dataset.personId && button.dataset.leadId) {
                    requestBody = {
                        person_id: parseInt(button.dataset.personId),
                        lead_id: parseInt(button.dataset.leadId),
                    };
                }

                let response;
                try {
                    response = await fetch(attachUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': getCsrfToken(),
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: Object.keys(requestBody).length > 0 ? JSON.stringify(requestBody) : undefined,
                    });
                } catch (error) {
                    emitFlash('error', 'GVL formulier koppelen is mislukt. Forms API niet bereikbaar.');
                    console.error('[GVLForm] Attach request failed', error);
                    setButtonLoadingState(button, false);
                    return;
                }

                let payload = {};
                try {
                    payload = await response.clone().json();
                } catch (error) {
                    payload = {};
                }

                if (response.status === 200) {
                    const input = document.querySelector(`input[name="gvl_form_link"]`);
                    if (input && payload.gvl_form_link) {
                        input.value = payload.gvl_form_link;
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                    }

                    emitFlash('success', payload?.message ?? 'GVL formulier is gekoppeld.');

                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    emitFlash('error', payload?.message ?? 'GVL formulier koppelen is mislukt.');
                    setButtonLoadingState(button, false);
                }
            }

            // Detach handler
            if (e.target && e.target.id === detachButtonId) {
                e.preventDefault();
                const button = e.target;

                if (button.dataset.loading === 'true') return;

                const detachUrl = button.dataset.detachUrl;
                if (!detachUrl) {
                    emitFlash('error', 'Ontkoppel-URL ontbreekt.');
                    return;
                }

                if (!window.confirm('Weet je zeker dat je het GVL formulier wil ontkoppelen?')) {
                    return;
                }

                setButtonLoadingState(button, true);

                let response;
                try {
                    response = await fetch(detachUrl, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': getCsrfToken(),
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                } catch (error) {
                    emitFlash('error', 'GVL formulier ontkoppelen is mislukt. Forms API niet bereikbaar.');
                    console.error('[GVLForm] Detach request failed', error);
                    setButtonLoadingState(button, false);
                    return;
                }

                let payload = {};
                try {
                    payload = await response.clone().json();
                } catch (error) {
                    payload = {};
                }

                if (response.status === 200) {
                    const input = document.querySelector(`input[name="gvl_form_link"]`);
                    if (input) {
                        input.value = '';
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                    }

                    emitFlash('success', payload?.message ?? 'GVL formulier is ontkoppeld.');

                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    emitFlash('error', payload?.message ?? 'GVL formulier ontkoppelen is mislukt.');
                    setButtonLoadingState(button, false);
                }
            }
        });

        // Load GVL form status
        const loadGvlFormStatus = async () => {
            const statusContainer = document.getElementById(statusContainerId);
            const statusElement = document.getElementById(statusValueId);

            if (!statusContainer || !statusElement) {
                return;
            }

            // Check if already loaded (not showing "Laden...")
            const currentText = statusElement.textContent.trim();
            if (currentText && currentText !== 'Laden...') {
                return;
            }

            const statusUrl = '{{ $statusUrl }}';
            if (!statusUrl) {
                statusElement.textContent = 'Niet beschikbaar';
                statusElement.className = 'font-medium text-gray-400';
                return;
            }

            try {
                const response = await fetch(statusUrl, {
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error(`Status kon niet worden opgehaald: ${response.status} ${response.statusText}`);
                }

                const data = await response.json();
                const status = data.data?.status || data.data?.state || null;
                const statusLower = status ? status.toLowerCase() : '';

                const statusMap = {
                    'new': { text: 'Nieuw', color: 'text-gray-600' },
                    'step1': { text: 'Stap 1 voltooid', color: 'text-yellow-600' },
                    'step2': { text: 'Stap 2 voltooid', color: 'text-activity-note-text' },
                    'step3': { text: 'Stap 3 voltooid', color: 'text-status-active-text' },
                    'completed': { text: 'Voltooid', color: 'text-status-active-text' },
                };

                const statusInfo = statusMap[statusLower] || { text: 'Onbekend', color: 'text-gray-400' };

                // Function to update the status element
                const updateStatusElement = () => {
                    const currentStatusElement = document.getElementById(statusValueId);
                    if (currentStatusElement) {
                        const currentText = currentStatusElement.textContent.trim();
                        if (currentText === 'Laden...' || currentText === '' || currentText !== statusInfo.text) {
                            currentStatusElement.textContent = statusInfo.text;
                            currentStatusElement.className = `font-medium ${statusInfo.color}`;
                            return true;
                        }
                        return true;
                    }
                    return false;
                };

                // Try to update immediately
                if (!updateStatusElement()) {
                    // Fallback: update via parent innerHTML
                    const statusContainer = document.getElementById(statusContainerId);
                    if (statusContainer) {
                        statusContainer.innerHTML = `<span class="text-gray-500">Status: </span><span id="${statusValueId}" class="font-medium ${statusInfo.color}">${statusInfo.text}</span>`;
                    }
                }

                // Set up a MutationObserver to re-update if Vue changes it back
                const statusContainer = document.getElementById(statusContainerId);
                if (statusContainer) {
                    const observer = new MutationObserver((mutations) => {
                        mutations.forEach((mutation) => {
                            if (mutation.type === 'childList' || mutation.type === 'characterData') {
                                const statusElement = document.getElementById(statusValueId);
                                if (statusElement && statusElement.textContent.trim() === 'Laden...') {
                                    setTimeout(() => {
                                        updateStatusElement();
                                    }, 50);
                                }
                            }
                        });
                    });

                    observer.observe(statusContainer, {
                        childList: true,
                        subtree: true,
                        characterData: true,
                    });

                    // Stop observing after 5 seconds
                    setTimeout(() => {
                        observer.disconnect();
                    }, 5000);
                }
            } catch (error) {
                console.error('[GVLForm] Error loading status:', error);
                const errorStatusElement = document.getElementById(statusValueId);
                if (errorStatusElement) {
                    errorStatusElement.textContent = 'Niet beschikbaar';
                    errorStatusElement.className = 'font-medium text-gray-400';
                }
            }
        };

        // Load status when DOM is ready
        const initStatusLoading = () => {
            const tryLoadStatus = () => {
                const statusContainer = document.getElementById(statusContainerId);
                const statusElement = document.getElementById(statusValueId);

                if (statusContainer && statusElement) {
                    loadGvlFormStatus();
                    return true;
                }
                return false;
            };

            // Wait for Vue to be ready if it exists
            const waitForVue = (callback, maxWait = 3000) => {
                const startTime = Date.now();
                const checkVue = () => {
                    const vueReady = window.app || window.Vue || document.querySelector('[data-v-app]');

                    if (vueReady || (Date.now() - startTime) > maxWait) {
                        callback();
                        return;
                    }
                    setTimeout(checkVue, 100);
                };
                checkVue();
            };

            // Try immediately
            if (tryLoadStatus()) {
                return;
            }

            // Wait for Vue and DOM to be ready
            waitForVue(() => {
                setTimeout(() => {
                    if (!tryLoadStatus()) {
                        // Retry multiple times with increasing delays
                        [500, 1000, 2000].forEach((delay) => {
                            setTimeout(() => tryLoadStatus(), delay);
                        });
                    }
                }, 200);
            });
        };

        // Start initialization
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                initStatusLoading();
            }, { once: true });
        } else {
            initStatusLoading();
        }
    })();
</script>
@endPushOnce

