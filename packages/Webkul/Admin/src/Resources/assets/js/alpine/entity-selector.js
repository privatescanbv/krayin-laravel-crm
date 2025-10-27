export default function registerEntitySelector(Alpine) {
    Alpine.data('entitySelector', (config) => ({
        name: config.name,
        label: config.label,
        placeholder: config.placeholder,
        searchRoute: config.searchRoute,
        canAddNew: config.canAddNew,
        multiple: config.multiple,
        style: config.style || 'default',
        eventName: config.eventName,
        items: config.items || [],

        init() {
            try {
                window.addEventListener(this.eventName, e => this.add(e.detail));
            } catch (e) {
                console.error('entitySelector init error', e);
            }
        },

        add(item) {
            if (!item || !item.id) return;
            if (this.items.some(i => i.id === item.id)) return;
            if (this.multiple) this.items.push(item);
            else this.items = [item];
        },

        remove(index) {
            this.items.splice(index, 1);
        },
    }));
}
