@pushOnce('scripts')
    <script>
        // Shared helper to build confirmation message with open activity titles
        window.buildOpenActivitiesConfirmMessage = async function(axiosInstance, entityId, openCount, entityType='lead', options = {}) {
            const type = (options && options.type) ? options.type : entityType;

            const endpointMap = {
                sale:  { tmpl: "{{ route('admin.sales-leads.activities.index', 0) }}", params: { is_done: 0, hierarchy: false } },
                order: { tmpl: "{{ route('admin.orders.activities.index', 0) }}",      params: { is_done: 0 } },
                lead:  { tmpl: "{{ route('admin.activities.by_lead_open', 0) }}",      params: {} },
            };
            const suffixMap = { sale: ' op deze sales', order: ' op deze order' };

            let titlesList = '';
            try {
                const config = endpointMap[type];
                if (!config) throw new Error('Unknown entity type for fetching activities');

                const endpoint = config.tmpl.replace('/0/', `/${entityId}/`);
                const res = await axiosInstance.get(endpoint, { params: config.params });
                const activities = Array.isArray(res?.data?.data) ? res.data.data : [];

                const titles = activities
                    .map(a => (a.title || '').trim())
                    .filter(t => t.length > 0)
                    .slice(0, 10);
                if (titles.length > 0) {
                    titlesList = '\n\nActies:\n- ' + titles.join('\n- ');
                    if (activities.length > titles.length) {
                        titlesList += `\n- (+${activities.length - titles.length} meer)`;
                    }
                }
            } catch (e) {
                // Ignore fetch errors; present basic message without titles
            }

            const suffix = suffixMap[type] ?? ' op deze lead';
            return `Er staan nog ${openCount} open activiteit(en)${suffix}. Wil je deze afronden en de status wijzigen?${titlesList}`;
        }
    </script>
@endPushOnce
