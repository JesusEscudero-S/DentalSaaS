/**
 * Select con buscador (componente Blade x-ui.combobox).
 *
 * items:    [{ value: 'uuid', label: 'NOMBRE (PAC-0001)' }, ...]
 * selected: valor elegido al cargar (por ejemplo old('patient_id') si la validación falló)
 *
 * Mientras escribes se filtra la lista; el valor que se envía (input hidden) solo cambia
 * cuando eliges una opción. Si escribes algo y no eliges nada, al salir se borra el texto.
 */
export default (items, selected = '') => ({
    items,
    value: selected,
    query: '',
    open: false,
    active: 0,

    init() {
        this.query = this.labelOf(this.value);
    },

    // Ignora mayúsculas y acentos: "lucia" encuentra "LUCÍA".
    normalize(text) {
        return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    },

    labelOf(value) {
        return this.items.find((item) => item.value === value)?.label ?? '';
    },

    get filtered() {
        // Con una opción ya elegida se muestran todas (para poder cambiarla).
        if (this.value && this.query === this.labelOf(this.value)) return this.items;

        const words = this.normalize(this.query).split(/\s+/).filter(Boolean);

        return this.items.filter((item) => {
            const label = this.normalize(item.label);
            return words.every((word) => label.includes(word));   // "ana pac-0003" también funciona
        });
    },

    search() {
        this.value = '';   // escribir invalida la opción anterior
        this.open = true;
        this.active = 0;
    },

    move(step) {
        this.open = true;
        const last = this.filtered.length - 1;
        this.active = Math.min(Math.max(this.active + step, 0), last);
        this.$nextTick(() => this.$refs.list.querySelector(`[data-index="${this.active}"]`)?.scrollIntoView({ block: 'nearest' }));
    },

    pick(item) {
        if (!item) return;
        this.value = item.value;
        this.query = item.label;
        this.open = false;
    },

    close() {
        this.open = false;
        if (!this.value) this.query = '';
    },
});
