<x-admin::layouts>
    <x-slot:title>Betalingen</x-slot>

    <div id="v-betalingen-root"
         data-orders="{{ json_encode($orders) }}"
    >
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <div class="text-xl font-bold dark:text-white">Klant betalingen</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Overzicht en beheer van alle klantbetalingen per order.</div>
                </div>
                <button
                    id="v-btn-add"
                    class="rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
                >
                    + Betaling toevoegen
                </button>
            </div>

            {{-- Form panel (hidden by default, Vue toggles visibility) --}}
            <div id="v-form-panel" class="hidden rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            </div>

            {{-- Payments table --}}
            <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div id="v-table-container">
                    <div class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">Betalingen laden...</div>
                </div>
            </div>
        </div>
    </div>

    @pushOnce('scripts')
    <script>
    (function () {
        const root     = document.getElementById('v-betalingen-root');
        const orders   = JSON.parse(root.dataset.orders || '[]');
        const btnAdd   = document.getElementById('v-btn-add');
        const formPanel = document.getElementById('v-form-panel');
        const tableContainer = document.getElementById('v-table-container');

        const typeLabels   = { advance: 'Aanbetaling', clinic: 'Kliniek', refund: 'Terugbetaling' };
        const methodLabels = { pin: 'Pin', cash: 'Contant', creditcard: 'Creditcard' };
        const currencies   = ['EUR', 'USD', 'GBP', 'CHF', 'DKK', 'NOK', 'SEK'];

        let payments   = [];
        let editingId  = null;
        let editOrderId = null;

        // ── Helpers ──────────────────────────────────────────────────────────

        function csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        }

        function formatAmount(payment) {
            const amount = parseFloat(payment.amount);
            const formatted = new Intl.NumberFormat('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount);
            const symbols = { EUR: '€', USD: '$', GBP: '£', CHF: 'CHF', DKK: 'kr', NOK: 'kr', SEK: 'kr' };
            const sym = symbols[payment.currency] || payment.currency || '€';
            return ['EUR', 'USD', 'GBP'].includes(payment.currency) ? sym + ' ' + formatted : formatted + ' ' + sym;
        }

        function orderUrl(orderId) {
            return `/admin/orders/view/${orderId}#afletteren`;
        }

        // ── API ───────────────────────────────────────────────────────────────

        async function fetchPayments() {
            const resp = await fetch('/admin/afletteren/payments', { headers: { 'Accept': 'application/json' } });
            payments = await resp.json();
            renderTable();
        }

        async function savePayment(data, orderId, paymentId) {
            const url    = paymentId
                ? `/admin/orders/${orderId}/payments/${paymentId}`
                : `/admin/orders/${orderId}/payments`;
            const method = paymentId ? 'PUT' : 'POST';
            return fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify(data),
            });
        }

        async function deletePayment(orderId, paymentId) {
            return fetch(`/admin/orders/${orderId}/payments/${paymentId}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
            });
        }

        // ── Render: table ─────────────────────────────────────────────────────

        function renderTable() {
            if (payments.length === 0) {
                tableContainer.innerHTML = '<div class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">Nog geen betalingen geregistreerd.</div>';
                return;
            }

            const rows = payments.map(p => `
                <tr class="border-b border-gray-100 dark:border-gray-800">
                    <td class="px-2 py-2">
                        <a href="${orderUrl(p.order_id)}" class="text-blue-600 hover:underline dark:text-blue-400">
                            ${escHtml(p.order_number || '#' + p.order_id)}
                        </a>
                    </td>
                    <td class="px-2 py-2 text-gray-700 dark:text-gray-300">${escHtml(p.patient_name || '—')}</td>
                    <td class="px-2 py-2 font-medium text-gray-900 dark:text-white">${escHtml(formatAmount(p))}</td>
                    <td class="px-2 py-2 text-gray-700 dark:text-gray-300">${escHtml(typeLabels[p.type] || p.type)}</td>
                    <td class="px-2 py-2 text-gray-700 dark:text-gray-300">${escHtml(methodLabels[p.method] || p.method)}</td>
                    <td class="px-2 py-2 text-gray-600 dark:text-gray-400">${escHtml(p.paid_at || '—')}</td>
                    <td class="px-2 py-2 whitespace-nowrap">
                        <button class="mr-3 text-xs text-blue-600 hover:underline dark:text-blue-400" data-edit="${p.id}">Bewerk</button>
                        <button class="text-xs text-red-600 hover:underline dark:text-red-400" data-delete="${p.id}" data-order="${p.order_id}">Verwijder</button>
                    </td>
                </tr>
            `).join('');

            tableContainer.innerHTML = `
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left dark:border-gray-700">
                                <th class="px-2 py-2 font-medium text-gray-500 dark:text-gray-400">Order</th>
                                <th class="px-2 py-2 font-medium text-gray-500 dark:text-gray-400">Patiënt</th>
                                <th class="px-2 py-2 font-medium text-gray-500 dark:text-gray-400">Bedrag</th>
                                <th class="px-2 py-2 font-medium text-gray-500 dark:text-gray-400">Type</th>
                                <th class="px-2 py-2 font-medium text-gray-500 dark:text-gray-400">Methode</th>
                                <th class="px-2 py-2 font-medium text-gray-500 dark:text-gray-400">Datum</th>
                                <th class="px-2 py-2 font-medium text-gray-500 dark:text-gray-400">Acties</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `;

            // Bind row action buttons
            tableContainer.querySelectorAll('[data-edit]').forEach(btn => {
                btn.addEventListener('click', () => openEdit(parseInt(btn.dataset.edit)));
            });
            tableContainer.querySelectorAll('[data-delete]').forEach(btn => {
                btn.addEventListener('click', () => onDelete(parseInt(btn.dataset.delete), parseInt(btn.dataset.order)));
            });
        }

        // ── Render: form ──────────────────────────────────────────────────────

        function buildOrderOptions(selectedId) {
            return orders.map(o =>
                `<option value="${o.id}" ${o.id == selectedId ? 'selected' : ''}>${escHtml(o.label)}</option>`
            ).join('');
        }

        function buildSelect(name, options, selected, cls) {
            return `<select name="${name}" class="${cls}">` +
                options.map(([val, lbl]) =>
                    `<option value="${val}" ${val === selected ? 'selected' : ''}>${lbl}</option>`
                ).join('') +
                `</select>`;
        }

        function renderForm(payment) {
            const cls = 'w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white';
            const isEdit = !!payment;
            const p = payment || { order_id: '', amount: '', type: 'advance', method: 'pin', paid_at: '', currency: 'EUR' };

            formPanel.innerHTML = `
                <h4 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">
                    ${isEdit ? 'Betaling bewerken' : 'Nieuwe betaling'}
                </h4>
                <div id="v-form-error" class="hidden mb-3 rounded bg-red-50 p-2 text-xs text-red-700 dark:bg-red-900/30 dark:text-red-300"></div>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                    <div class="col-span-2 md:col-span-3">
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Order *</label>
                        <select name="order_id" ${isEdit ? 'disabled' : ''} class="${cls}">
                            <option value="">— Selecteer order —</option>
                            ${buildOrderOptions(p.order_id)}
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Bedrag *</label>
                        <input name="amount" type="number" step="0.01" min="0" value="${escAttr(p.amount)}" class="${cls}" placeholder="0.00" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Type *</label>
                        ${buildSelect('type', [['advance','Aanbetaling'],['clinic','Kliniek'],['refund','Terugbetaling']], p.type, cls)}
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Methode *</label>
                        ${buildSelect('method', [['pin','Pin'],['cash','Contant'],['creditcard','Creditcard']], p.method, cls)}
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Datum</label>
                        <input name="paid_at" type="date" value="${escAttr(p.paid_at || '')}" class="${cls}" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Valuta</label>
                        ${buildSelect('currency', currencies.map(c => [c, c]), p.currency, cls)}
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <button id="v-btn-save" class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Opslaan</button>
                    <button id="v-btn-cancel" class="rounded border border-gray-300 px-4 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">Annuleren</button>
                </div>
            `;

            formPanel.classList.remove('hidden');

            document.getElementById('v-btn-cancel').addEventListener('click', closeForm);
            document.getElementById('v-btn-save').addEventListener('click', onSave);
        }

        // ── Actions ───────────────────────────────────────────────────────────

        function openAdd() {
            editingId = null;
            editOrderId = null;
            renderForm(null);
            formPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function openEdit(id) {
            const p = payments.find(x => x.id === id);
            if (!p) return;
            editingId = p.id;
            editOrderId = p.order_id;
            renderForm(p);
            formPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function closeForm() {
            formPanel.classList.add('hidden');
            formPanel.innerHTML = '';
            editingId = null;
            editOrderId = null;
        }

        async function onSave() {
            const saveBtn = document.getElementById('v-btn-save');
            const errEl   = document.getElementById('v-form-error');

            const orderId = editOrderId || parseInt(formPanel.querySelector('[name=order_id]')?.value);
            if (!orderId) {
                errEl.textContent = 'Selecteer een order.';
                errEl.classList.remove('hidden');
                return;
            }

            const data = {
                amount:   formPanel.querySelector('[name=amount]').value,
                type:     formPanel.querySelector('[name=type]').value,
                method:   formPanel.querySelector('[name=method]').value,
                paid_at:  formPanel.querySelector('[name=paid_at]').value || null,
                currency: formPanel.querySelector('[name=currency]').value,
            };

            saveBtn.textContent = 'Opslaan...';
            saveBtn.disabled = true;
            errEl.classList.add('hidden');

            try {
                const resp = await savePayment(data, orderId, editingId);
                if (!resp.ok) {
                    const body = await resp.json().catch(() => ({}));
                    errEl.textContent = body.message || `Fout: ${resp.status}`;
                    errEl.classList.remove('hidden');
                    return;
                }
                closeForm();
                await fetchPayments();
            } catch (e) {
                errEl.textContent = 'Netwerkfout. Probeer opnieuw.';
                errEl.classList.remove('hidden');
            } finally {
                if (saveBtn) {
                    saveBtn.textContent = 'Opslaan';
                    saveBtn.disabled = false;
                }
            }
        }

        async function onDelete(paymentId, orderId) {
            if (!confirm('Betaling verwijderen?')) return;
            await deletePayment(orderId, paymentId);
            await fetchPayments();
        }

        // ── Helpers ───────────────────────────────────────────────────────────

        function escHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function escAttr(str) {
            return String(str ?? '').replace(/"/g, '&quot;');
        }

        // ── Bootstrap ─────────────────────────────────────────────────────────

        btnAdd.addEventListener('click', openAdd);
        fetchPayments();
    })();
    </script>
    @endPushOnce

</x-admin::layouts>
