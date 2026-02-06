<duplicates-toggle></duplicates-toggle>

@pushOnce('scripts')
    <script type="text/x-template" id="duplicates-toggle-template">
        <x-adminc::components.field
            type="switch"
            name="show_duplicates"
            value="1"
            :checked="false"
            label="Duplicaten"
        />
    </script>

    <script type="module">
        app.component('duplicates-toggle', {
            template: '#duplicates-toggle-template',

            data() {
                return {
                    enabled: false,
                };
            },

            mounted() {
                this.$nextTick(() => {
                    this.syncWithKanban();
                });
                const $input = [...document.querySelectorAll('input[name="show_duplicates"]')]
                    .find(el => el.type !== 'hidden');
                if ($input) {
                    $input.addEventListener('click', () => {
                        this.toggle();
                    });
                }
                this.$emitter.on('kanban-duplicates-updated', () => {
                    this.syncWithKanban();
                });
            },

            methods: {
                syncWithKanban() {
                    const kanban = this.$root.$refs.leadsKanban;

                    if (kanban) {
                        this.enabled = kanban.showDuplicates;
                        this.setCheckbox(this.enabled);
                    }
                },

                toggle() {
                    const kanban = this.$root.$refs.leadsKanban;

                    if (kanban?.toggleDuplicates) {
                        kanban.toggleDuplicates();
                    }
                },

                setCheckbox(state) {
                    const el = document.getElementById('show_duplicates');
                    if (el) el.checked = state;
                }
            }
        });
    </script>
@endPushOnce
