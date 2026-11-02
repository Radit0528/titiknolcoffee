// Purchase (stok masuk) form: rows of ingredient / quantity / price paid.
// Unit cost and total are shown for checking only; the server recomputes both.
import { rupiah } from '../components/format';

export default function purchaseForm(ingredients, initialItems) {
    let nextKey = 0;
    const row = (item = {}) => ({
        key: nextKey++,
        ingredient_id: item.ingredient_id ? String(item.ingredient_id) : '',
        quantity: item.quantity ?? '',
        subtotal: item.subtotal ?? '',
    });

    return {
        ingredients,
        rows: initialItems.length ? initialItems.map(row) : [row()],
        submitting: false,

        find(id) {
            return this.ingredients.find((i) => String(i.id) === String(id));
        },
        unit(id) {
            return this.find(id)?.unit ?? '';
        },
        isTaken(id, r) {
            return this.rows.some((other) => other !== r && String(other.ingredient_id) === String(id));
        },
        unitCost(r) {
            const qty = parseFloat(r.quantity);
            const subtotal = parseFloat(r.subtotal);
            if (!(qty > 0) || !(subtotal >= 0)) return '–';
            const cost = subtotal / qty;
            return `${rupiah(cost, cost < 100 ? 2 : 0)}/${this.unit(r.ingredient_id) || 'satuan'}`;
        },
        add() {
            this.rows.push(row());
        },
        removeRow(r) {
            this.rows = this.rows.filter((other) => other !== r);
        },
        get total() {
            return this.rows.reduce((sum, r) => sum + (parseFloat(r.subtotal) || 0), 0);
        },
        money(value) {
            return rupiah(value);
        },
        submit(event) {
            // Guard against double-click creating two purchases.
            if (this.submitting) {
                event.preventDefault();
                return;
            }
            this.submitting = true;
        },
    };
}
