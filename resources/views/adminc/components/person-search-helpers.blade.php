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
                    // Regular text search
                    params.search = cleaned;
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


