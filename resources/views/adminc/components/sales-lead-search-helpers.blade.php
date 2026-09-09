{{--shared sales lead search functionality --}}
@pushOnce('scripts')
@verbatim
    <script type="module">
        window.adminc = window.adminc || {};

        if (!window.adminc.fetchSalesLeads) {
            window.adminc.fetchSalesLeads = async function(query, opts = {}) {
                let params = {};

                const cleaned = String(query || '').trim();

                // Sales leads only support name search
                // Convert query to name search format
                if (cleaned) {
                    params.search = `name:${cleaned};`;
                    params.searchFields = `name:like;`;
                    params.searchJoin = 'or';
                } else {
                    params.search = '';
                    params.searchFields = '';
                }

                if (opts.salesLeadId) {
                    params.sales_lead_id = opts.salesLeadId;
                }

                const response = await axios.get('/admin/sales-leads/search', { params });
                const results = (response && response.data && (response.data.data || response.data)) || [];

                // Active/ongoing sales leads first, most recently created first — helps
                // distinguish between multiple similarly named sales leads.
                return [...results].sort((a, b) => {
                    const aClosed = a.stage?.is_won || a.stage?.is_lost ? 1 : 0;
                    const bClosed = b.stage?.is_won || b.stage?.is_lost ? 1 : 0;

                    if (aClosed !== bClosed) {
                        return aClosed - bClosed;
                    }

                    return new Date(b.created_at) - new Date(a.created_at);
                });
            };
        }
    </script>
@endverbatim
@endPushOnce

