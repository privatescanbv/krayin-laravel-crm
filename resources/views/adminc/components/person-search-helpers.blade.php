{{--shared person logic search functionality --}}
@pushOnce('scripts')
@verbatim
    <script type="module">
        window.adminc = window.adminc || {};

        if (!window.adminc.fetchPersons) {
            window.adminc.fetchPersons = async function(query, opts = {}) {
                let params = {};

                const digitsOnly = String(query || '').replace(/\D+/g, '');
                if (digitsOnly.length >= 6) {
                    params.search = `phone:${digitsOnly};`;
                } else {
                    params.search = query;
                }

                if (opts.leadId) {
                    params.lead_id = opts.leadId;
                }

                const response = await axios.get('/admin/contacts/persons/search', { params });
                return (response && response.data && (response.data.data || response.data)) || [];
            };
        }
    </script>
@endverbatim
@endPushOnce


