@pushOnce('scripts')
@verbatim
<script type="text/x-template" id="v-match-score-template">
    <div class="flex items-center gap-2 text-gray-600 group relative">
        <span class="text-xs">Match:</span>
        <div class="w-16 h-2 bg-gray-200 rounded-full overflow-hidden">
            <div class="h-full rounded-full transition-all duration-300" :class="barClass" :style="{ width: (percentage || 0) + '%' }"></div>
        </div>
        <span class="text-xs font-medium" :class="textClass">{{ Math.round(percentage || 0) }}%</span>
        <div class="absolute left-0 top-full mt-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg shadow-lg opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity duration-200 z-50 w-80">
            <div v-if="loaded">
                <div><strong>Naam & Geboortedatum (85%):</strong> {{ Math.round(breakdown.name.weighted) }}%</div>
                <div><strong>Email (5%):</strong> {{ Math.round(breakdown.email.weighted) }}% <span v-if="!breakdown.email.matched">(geen match)</span></div>
                <div><strong>Telefoon (5%):</strong> {{ Math.round(breakdown.phone.weighted) }}% <span v-if="!breakdown.phone.matched">(geen match)</span></div>
                <div><strong>Adres (5%):</strong> {{ Math.round(breakdown.address.weighted) }}% <span v-if="!breakdown.address.matched">(geen match)</span></div>
                <div class="mt-1 text-blue-300"><strong>Totaal:</strong> {{ Math.round(breakdown.final.score) }}%</div>
            </div>
            <div v-else>Laden...</div>
        </div>
    </div>
</script>
@endverbatim
<script type="module">
app.component('v-match-score', {
    template: '#v-match-score-template',
    props: {
        personId: { type: [String, Number], required: true },
        leadId: { type: [String, Number], required: true },
    },
    data() {
        return {
            percentage: 0,
            breakdown: { name: { weighted: 0 }, email: { weighted: 0, matched: false }, phone: { weighted: 0, matched: false }, address: { weighted: 0, matched: false }, final: { score: 0 } },
            loaded: false,
        };
    },
    computed: {
        barClass() {
            const p = this.percentage || 0;
            return p >= 80 ? 'bg-succes' : (p >= 50 ? 'bg-status-on_hold-text' : 'bg-red-500');
        },
        textClass() {
            const p = this.percentage || 0;
            return p >= 80 ? 'text-status-active-text' : (p >= 50 ? 'text-yellow-600' : 'text-status-expired-text');
        },
    },
    mounted() {
        this.fetchScore();
    },
    methods: {
        async fetchScore() {
            try {
                const base = `{{ route('admin.contacts.persons.searchbylead_single') }}`;
                const url = `${base}?lead_id=${encodeURIComponent(this.leadId)}&person_id=${encodeURIComponent(this.personId)}`;
                const resp = await fetch(url);
                const data = await resp.json();
                this.percentage = data?.person?.match_score_percentage || 0;
                this.breakdown = data?.breakdown || this.breakdown;
                this.loaded = true;
            } catch (_) {
                this.loaded = true;
            }
        },
    },
});
</script>
@endPushOnce


