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
                return (response && response.data && (response.data.data || response.data)) || [];
            };
        }
    </script>
@endverbatim
@endPushOnce

