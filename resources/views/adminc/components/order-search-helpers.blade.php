{{-- Shared order search for entity-selector (mail linker, etc.) --}}
@pushOnce('scripts')
@verbatim
    <script type="module">
        window.adminc = window.adminc || {};

        if (!window.adminc.fetchOrders) {
            window.adminc.fetchOrders = async function (query, opts = {}) {
                const baseUrl = (opts.baseUrl || '/admin/orders/search').replace(/\/$/, '');
                const params = {
                    limit: opts.limit ?? 15,
                };

                const cleaned = String(query || '').trim();

                if (cleaned) {
                    params.search = `order_number:${cleaned};title:${cleaned};`;
                    params.searchFields = 'order_number:like;title:like;';
                    params.searchJoin = 'or';
                } else {
                    params.search = '';
                    params.searchFields = '';
                }

                const response = await axios.get(baseUrl, { params });
                const payload = response?.data?.data ?? response?.data ?? [];

                return Array.isArray(payload) ? payload : [];
            };
        }
    </script>
@endverbatim
@endPushOnce
