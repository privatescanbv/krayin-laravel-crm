export default function registerEntitySelector(Alpine) {
    Alpine.data('entitySelector', (config) => ({
        name: config.name,
        label: config.label,
        placeholder: config.placeholder,
        searchRoute: config.searchRoute,
        canAddNew: config.canAddNew,
        multiple: config.multiple,
        style: config.style || 'default',
        eventName: config.eventName,
        items: config.items || [],

        init() {
            try {
                window.addEventListener(this.eventName, e => this.add(e.detail));
            } catch (e) {
                console.error('entitySelector init error', e);
            }
        },

        add(item) {
            if (!item || !item.id) return;
            if (this.items.some(i => i.id === item.id)) return;
            if (this.multiple) this.items.push(item);
            else this.items = [item];
        },

        remove(index) {
            this.items.splice(index, 1);
        },
    }));
}

document.addEventListener('alpine:init', () => {
    initEntitySelectors();
});

function initEntitySelectors() {
    const containers = document.querySelectorAll('[id^="entity-selector-"]');

    containers.forEach(container => {
        try {
            const config = JSON.parse(container.dataset.entityConfig || '{}');
            createEntitySelector(container, config);
        } catch (e) {
            console.error('Invalid entity-selector config', e);
        }
    });
}

function createEntitySelector(container, config) {
    const {name, label, placeholder, searchRoute, canAddNew, multiple, items} = config;


    // <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
    //     ${label}
    // </label>
    container.innerHTML = `
        <div class="space-y-3">

            <div id="lookup-${name}"></div>

            <div id="selected-items-${name}" class="space-y-2" style="display: none;">
            </div>

            <div id="hidden-inputs-${name}">
            </div>

            <p id="no-items-${name}" class="text-sm text-gray-400">Nog geen items geselecteerd</p>
        </div>
    `;

    initEntitySelector(name, {
        label,
        placeholder,
        searchRoute,
        canAddNew,
        multiple,
        selectedItems: items || []
    });
}

function initEntitySelector(name, config) {
    let selectedItems = [...config.selectedItems];

    renderSelectedItems(name, selectedItems);
    renderHiddenInputs(name, selectedItems, config.multiple);
    initLookupComponent(name, config);

    // Store the multiple config for this entity selector
    window[`entityConfig_${name}`] = config;

    // Listen for item selection
    window.addEventListener(`entity-add-${name}`, (e) => {
        addItem(name, e.detail);
    });
}
function renderSelectedItems(name, items) {
    const container = document.getElementById(`selected-items-${name}`);
    const noItemsMsg = document.getElementById(`no-items-${name}`);

    if (items.length === 0) {
        container.style.display = "none";
        noItemsMsg.style.display = "block";
        return;
    }

    container.style.display = "block";
    noItemsMsg.style.display = "none";

    container.innerHTML = items
        .map(
            (item, index) => `
      <div
        id="entity-item-${name}-${index}"
        class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 transform transition-all duration-300 ease-out opacity-0 translate-y-2 animate-[fadeInUp_0.3s_ease-out_forwards]"
      >
        <span>${item.name}</span>
        <button
          type="button"
          class="text-red-500 hover:text-red-700 text-xs transition-colors duration-150"
          onclick="removeItem('${name}', ${index})"
        >
          ✕
        </button>
      </div>
    `
        )
        .join("");
}


function renderHiddenInputs(name, items, multiple) {
    const container = document.getElementById(`hidden-inputs-${name}`);

    if (multiple) {
        container.innerHTML = items.map(item =>
            `<input type="hidden" name="${name}[]" value="${item.id}" data-name="${item.name}">`
        ).join('');
    } else {
        const value = items.length > 0 ? items[0].id : '';
        const itemName = items.length > 0 ? items[0].name : '';
        container.innerHTML = `<input type="hidden" name="${name}" value="${value}" data-name="${itemName}">`;
    }
}

function initLookupComponent(name, config) {
    const container = document.getElementById(`lookup-${name}`);

    container.innerHTML = `
        <div class="relative">
            <input
                type="text"
                id="lookup-input-${name}"
                placeholder="${config.placeholder}"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                autocomplete="off"
            />
            <div id="lookup-results-${name}" class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg hidden max-h-60 overflow-auto"></div>
        </div>
    `;

    const input = document.getElementById(`lookup-input-${name}`);
    const results = document.getElementById(`lookup-results-${name}`);
    let abortController = null;

    input.addEventListener('input', function () {
        const query = this.value.trim();

        if (query.length < 2) {
            results.classList.add('hidden');
            return;
        }

        if (abortController) {
            abortController.abort();
        }

        abortController = new AbortController();

        fetch(`${config.searchRoute}?q=${encodeURIComponent(query)}`, {
            signal: abortController.signal
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Search response:', data);
                // Handle API response format: {data: [...]}
                const results = data.data || data;
                displayResults(name, results);
            })
            .catch(error => {
                if (error.name !== 'AbortError') {
                    console.error('Search error:', error);
                    const container = document.getElementById(`lookup-results-${name}`);
                    container.innerHTML = '<div class="px-3 py-2 text-red-500 text-sm">Zoekfout: ' + error.message + '</div>';
                    container.classList.remove('hidden');
                }
            });
    });

    input.addEventListener('focus', function () {
        if (results.children.length > 0) {
            results.classList.remove('hidden');
        }
    });

    document.addEventListener('click', function (e) {
        if (!container.contains(e.target)) {
            results.classList.add('hidden');
        }
    });
}

function displayResults(name, results) {
    const container = document.getElementById(`lookup-results-${name}`);

    if (!Array.isArray(results)) {
        console.error('Search results is not an array:', results);
        container.innerHTML = '<div class="px-3 py-2 text-gray-500 text-sm">Ongeldige response van server</div>';
        container.classList.remove('hidden');
        return;
    }

    const hiddenInputsContainer = document.getElementById(`hidden-inputs-${name}`);
    const selectedIds = Array.from(hiddenInputsContainer.querySelectorAll('input')).map(input => input.value);
    const filteredResults = results.filter(item => !selectedIds.includes(item.id.toString()));

    if (filteredResults.length === 0) {
        container.innerHTML = `<div class="px-3 py-2 text-gray-500 text-sm">Geen resultaten gevonden</div>`;
    } else {
        container.innerHTML = filteredResults
            .map(
                (item) => `
            <div
                class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-100 last:border-b-0 transform transition-all duration-200 ease-out opacity-0 translate-y-2 animate-[fadeInUp_0.35s_ease-out_forwards]"
                onclick="selectItem('${name}', ${JSON.stringify(item).replace(/"/g, '&quot;')})"
            >
                <div class="font-medium">${item.name || item.title || 'Geen naam'}</div>
                ${item.description ? `<div class="text-sm text-gray-600">${item.description}</div>` : ''}
            </div>
        `
            )
            .join('');
    }

    container.classList.remove('hidden');
}

window.selectItem = function (name, item) {
    document.getElementById(`lookup-results-${name}`).classList.add('hidden');
    document.getElementById(`lookup-input-${name}`).value = '';
    window.dispatchEvent(new CustomEvent(`entity-add-${name}`, {detail: item}));
}

function addItem(name, item) {
    const hiddenInputsContainer = document.getElementById(`hidden-inputs-${name}`);
    const existingInputs = hiddenInputsContainer.querySelectorAll(`input[value="${item.id}"]`);
    if (existingInputs.length > 0) {
        return; // Already exists
    }

    const currentItems = Array.from(hiddenInputsContainer.querySelectorAll('input')).map(input => ({
        id: input.value,
        name: input.dataset.name || 'Geen naam'
    }));

    currentItems.push({
        id: item.id,
        name: item.name || item.title || 'Geen naam'
    });

    // Get the multiple config from stored configuration
    const config = window[`entityConfig_${name}`];
    const isMultiple = config ? config.multiple : false;

    renderSelectedItems(name, currentItems);
    renderHiddenInputs(name, currentItems, isMultiple);

    // Update search results if they are visible to remove the newly added item
    const resultsContainer = document.getElementById(`lookup-results-${name}`);
    if (resultsContainer && !resultsContainer.classList.contains('hidden')) {
        // Re-trigger search to update results
        const input = document.getElementById(`lookup-input-${name}`);
        if (input && input.value.trim().length >= 2) {
            input.dispatchEvent(new Event('input'));
        }
    }
}
window.removeItem = function (name, index) {
    const itemEl = document.getElementById(`entity-item-${name}-${index}`);
    if (!itemEl) return;

    // Start fade-out-left animatie
    itemEl.classList.add("animate-[fadeOutLeft_0.6s_ease-in_forwards]");

    // Na animatie: item daadwerkelijk verwijderen
    itemEl.addEventListener(
        "animationend",
        () => {
            const hiddenInputsContainer = document.getElementById(
                `hidden-inputs-${name}`
            );
            const currentItems = Array.from(
                hiddenInputsContainer.querySelectorAll("input")
            ).map((input) => ({
                id: input.value,
                name: input.dataset.name || "Geen naam",
            }));

            currentItems.splice(index, 1);

            const config = window[`entityConfig_${name}`];
            const isMultiple = config ? config.multiple : false;

            renderSelectedItems(name, currentItems);
            renderHiddenInputs(name, currentItems, isMultiple);

            // Refresh resultaten als dropdown open is
            const resultsContainer = document.getElementById(`lookup-results-${name}`);
            if (resultsContainer && !resultsContainer.classList.contains("hidden")) {
                const input = document.getElementById(`lookup-input-${name}`);
                if (input && input.value.trim().length >= 2) {
                    input.dispatchEvent(new Event("input"));
                }
            }
        },
        { once: true }
    );
};
