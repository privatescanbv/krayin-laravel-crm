{{--shared person logic search functionality --}}
@pushOnce('scripts')
@verbatim
    <script type="module">
        window.adminc = window.adminc || {};

        if (!window.adminc.fetchPersons) {
            window.adminc.fetchPersons = async function(query, opts = {}, dominantPhoneBehavior = true) {
                let params = {};

                const cleaned = String(query || '').trim();
                const digitsOnly = cleaned.replace(/\D+/g, '');

                // Check if query looks like an email address
                const isEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(cleaned);

                if (isEmail) {
                    // For email addresses, search only on email
                    params.search = `email:${cleaned};`;
                    params.searchFields = `emails:like;`;
                } else if (dominantPhoneBehavior && digitsOnly.length >= 4) {
                    // For phone numbers, use digits-only version
                    params.search = `phone:${digitsOnly};`;
                    params.searchFields = `phones:like;`;
                } else {
                    // Check if query contains colon but is not a valid fielded search pattern
                    // If it contains colon but doesn't match known patterns, treat as plain text
                    const hasColon = cleaned.includes(':');
                    const looksLikeFieldedSearch = /^(email|emails|phone|phones|name|first_name|last_name|married_name|organization\.name|user\.name):/.test(cleaned);

                    if (hasColon && !looksLikeFieldedSearch) {
                        // Contains colon but not a valid field pattern - treat as plain text query
                        // This prevents errors when users accidentally type colons (e.g., "desiree:" or "des:1")
                        params.query = cleaned;
                    } else {
                        // Regular text search or valid fielded search
                        params.search = cleaned;
                    }
                }

                if (opts.leadId) {
                    params.lead_id = opts.leadId;
                }

                const response = await axios.get('/admin/contacts/persons/search', { params });
                const result = response?.data?.data ?? response?.data ?? [];
                console.log('[fetchPersons] response.data:', response?.data, 'result:', result);
                return Array.isArray(result) ? result : [];
            };
        }
    </script>
@endverbatim
@endPushOnce


