// Stock out / adjustment forms: show current stock of the chosen ingredient
// and preview the result. The server re-checks everything on submit.
import { qty } from '../components/format';

export default function stockForm(ingredients, selected = null, mode = 'out', oldAmount = null) {
    return {
        ingredients,
        ingredientId: selected ? String(selected) : '',
        amount: oldAmount ?? '',
        submitting: false,

        get current() {
            return this.ingredients.find((i) => String(i.id) === this.ingredientId) ?? null;
        },
        get unit() {
            return this.current?.unit ?? '';
        },
        get value() {
            return parseFloat(this.amount);
        },
        // Stock out: remaining stock. Adjustment: signed difference.
        get preview() {
            if (!this.current || Number.isNaN(this.value)) return null;
            return mode === 'out' ? this.current.stock - this.value : this.value - this.current.stock;
        },
        get exceeds() {
            return mode === 'out' && this.preview !== null && this.preview < 0;
        },
        format(value) {
            return qty(value);
        },
        signed(value) {
            return (value > 0 ? '+' : '') + qty(value);
        },
        submit(event) {
            if (this.submitting || this.exceeds) {
                event.preventDefault();
                return;
            }
            this.submitting = true;
        },
    };
}
