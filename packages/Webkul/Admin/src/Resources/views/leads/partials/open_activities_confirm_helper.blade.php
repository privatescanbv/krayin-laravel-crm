@pushOnce('scripts')
    <script>
        // Shared helper to build confirmation message with open activity titles
        window.buildOpenActivitiesConfirmMessage = async function(axiosInstance, entityId, openCount, entityType='lead', options = {}) {
            let titlesList = '';
            try {
                console.log('tytpe = ' + entityType);
                let activities = [];
                if (options && options.type === 'sales') {
                    // Fetch only open activities for sales lead (server-side filter)
                    const tmpl = "{{ route('admin.sales-leads.activities.index', 0) }}"; // .../sales-leads/0/activities
                    const endpoint = tmpl.replace('/0/', `/${entityId}/`);
                    const res = await axiosInstance.get(endpoint, { params: { is_done: 0 } });
                    const all = res?.data?.data || [];
                    activities = Array.isArray(all) ? all : [];
                } else if (options && options.type === 'lead') {
                    // Default: fetch open activities for lead
                    const tmpl = "{{ route('admin.activities.by_lead_open', 0) }}"; // .../by-lead/0/open
                    const endpoint = tmpl.replace('/0/', `/${entityId}/`);
                    const res = await axiosInstance.get(endpoint);
                    activities = res?.data?.data || [];
                } else {
                    throw new Error('Unknown entity type for fetching activities');
                }

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

            const suffix = options && options.type === 'sales' ? ' op deze sales' : ' op deze lead';
            return `Er staan nog ${openCount} open activiteit(en)${suffix}. Wil je deze afronden en de status wijzigen?${titlesList}`;
        }
    </script>
@endPushOnce


