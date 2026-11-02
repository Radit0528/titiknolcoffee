// Recipe (BOM) editor with a live HPP preview.
// The preview is only a convenience: the server recalculates HPP on save.
import { rupiah } from '../components/format';

export default function recipeEditor(ingredients, initialItems, price) {
    let nextKey = 0;
    const row = (item = {}) => ({
        key: nextKey++,
        ingredient_id: item.ingredient_id ? String(item.ingredient_id) : '',
        quantity: item.quantity ?? '',
    });

    return {
        ingredients,
        price: Number(price) || 0,
        rows: initialItems.length ? initialItems.map(row) : [row()],

        find(id) {
            return this.ingredients.find((i) => String(i.id) === String(id));
        },
        unit(id) {
            return this.find(id)?.unit ?? '';
        },
        lineCost(r) {
            const ingredient = this.find(r.ingredient_id);
            const qty = parseFloat(r.quantity);
            return ingredient && qty > 0 ? ingredient.cost * qty : 0;
        },
        isTaken(id, r) {
            return this.rows.some((other) => other !== r && String(other.ingredient_id) === String(id));
        },
        add() {
            this.rows.push(row());
        },
        removeRow(r) {
            this.rows = this.rows.filter((other) => other !== r);
        },
        get hpp() {
            return this.rows.reduce((sum, r) => sum + this.lineCost(r), 0);
        },
        get margin() {
            return this.price - this.hpp;
        },
        get marginPercent() {
            return this.price > 0 ? (this.margin / this.price) * 100 : 0;
        },
        get marginWidth() {
            return `${Math.max(0, Math.min(100, this.marginPercent))}%`;
        },
        money(value) {
            return rupiah(value);
        },
    };
}
