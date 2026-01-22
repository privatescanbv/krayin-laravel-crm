<wonlost-toggle></wonlost-toggle>

@pushOnce('scripts')
    <script type="text/x-template" id="wonlost-toggle-template">
        <x-adminc::components.field
            type="switch"
            name="winlost"
            value="1"
            :checked="false"
            label="Win/Lost"
        />
    </script>

    <script type="module">
        app.component('wonlost-toggle', {
            template: '#wonlost-toggle-template',

            data() {
                return {
                    enabled: false,  // toggle state
                };
            },

            mounted() {
                // Use nextTick to ensure kanban component is fully mounted
                this.$nextTick(() => {
                    this.syncWithKanban();
                });
                // CLICK LISTENER toevoegen aan checkbox
                const $inputWinLost = [...document.querySelectorAll('input[name="winlost"]')]
                    .find(el => el.type !== 'hidden');
                if ($inputWinLost) {
                    $inputWinLost.addEventListener('click', () => {
                        this.toggle();
                    });
                } else {
                    console.error('Could not find winlost checkbox element.');
                }
                // listen for updates from kanban
                this.$emitter.on('kanban-wonlost-updated', () => {
                    this.syncWithKanban();
                });
            },

            methods: {
                syncWithKanban() {
                    const kanban = this.$root.$refs.leadsKanban;

                    if (kanban) {
                        this.enabled = !kanban.hideWonLost;
                        this.setCheckbox(this.enabled);
                    } else {
                        console.error('Could not find leadsKanban component to sync won/lost toggle.');
                    }
                },

                toggle() {
                    const kanban = this.$root.$refs.leadsKanban;

                    if (kanban?.toggleWonLost) {
                        kanban.toggleWonLost();
                        // Don't manually update state here - let the event handler do it
                        // The kanban component will emit 'kanban-wonlost-updated' which will sync our state
                    } else {
                        console.error('Could not find leadsKanban component to toggle won/lost.');
                    }
                },

                setCheckbox(state) {
                    const el = document.getElementById('winlost');
                    if (el) el.checked = state;
                }
            }
        });
    </script>
@endPushOnce
