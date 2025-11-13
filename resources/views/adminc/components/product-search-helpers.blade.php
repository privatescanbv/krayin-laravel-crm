{{--shared product search functionality --}}
@pushOnce('scripts')
@verbatim
    <script type="module">
        window.adminc = window.adminc || {};

        if (!window.adminc.fetchProducts) {
            window.adminc.fetchProducts = async function(query, opts = {}) {
                let params = {};

                const cleaned = String(query || '').trim();

                // Products support name search
                if (cleaned) {
                    params.query = cleaned;
                }

                const response = await axios.get('/admin/products/search', { params });
                return (response && response.data && (response.data.data || response.data)) || [];
            };
        }
    </script>
@endverbatim
@endPushOnce

