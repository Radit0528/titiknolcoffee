// Cart part of the POS screen. Prices shown here are a preview only;
// the server reprices everything at checkout.
let nextKey = 0;

export default function cart(catalog, draft) {
    const byId = new Map(catalog.map((p) => [p.id, p]));
    const line = (product, quantity = 1, note = '') => ({
        key: nextKey++,
        product_id: product.id,
        name: product.name,
        price: product.price,
        quantity,
        note: note ?? '',
    });

    return {
        lines: (draft?.items ?? []).filter((i) => byId.has(i.product_id)).map((i) => line(byId.get(i.product_id), i.quantity, i.note)),
        orderType: draft?.order_type ?? 'take_away',
        customerName: draft?.customer_name ?? '',
        draftId: draft?.id ?? null,
        noteFor: null,
        notice: '',

        quantityOf(productId) {
            return this.lines.filter((l) => l.product_id === productId).reduce((n, l) => n + l.quantity, 0);
        },
        // Servings left according to current stock (null = no recipe, no limit).
        remaining(product) {
            return product.servings === null ? Infinity : product.servings - this.quantityOf(product.id);
        },
        canAdd(product) {
            return product.available && this.remaining(product) > 0;
        },
        add(product) {
            if (!product.available) return;
            if (this.remaining(product) <= 0) {
                this.flash(`Stok bahan untuk ${product.name} tidak cukup.`);
                return;
            }
            const existing = this.lines.find((l) => l.product_id === product.id && !l.note);
            if (existing) existing.quantity++;
            else this.lines.push(line(product));
            this.cartChanged();
        },
        increment(l) {
            const product = byId.get(l.product_id);
            if (this.remaining(product) <= 0) {
                this.flash(`Stok bahan untuk ${l.name} tidak cukup.`);
                return;
            }
            l.quantity++;
            this.cartChanged();
        },
        decrement(l) {
            if (l.quantity > 1) l.quantity--;
            else this.remove(l);
            this.cartChanged();
        },
        remove(l) {
            this.lines = this.lines.filter((other) => other !== l);
            this.cartChanged();
        },
        clearCart() {
            this.lines = [];
            this.customerName = '';
            this.draftId = null;
            this.removeVoucher();
        },
        toggleNote(l) {
            this.noteFor = this.noteFor === l.key ? null : l.key;
        },
        flash(message) {
            this.notice = message;
            clearTimeout(this._noticeTimer);
            this._noticeTimer = setTimeout(() => (this.notice = ''), 3000);
        },
        get itemCount() {
            return this.lines.reduce((n, l) => n + l.quantity, 0);
        },
        get subtotal() {
            return this.lines.reduce((sum, l) => sum + l.price * l.quantity, 0);
        },
        payload() {
            return {
                items: this.lines.map((l) => ({ product_id: l.product_id, quantity: l.quantity, note: l.note || null })),
                order_type: this.orderType,
                customer_name: this.customerName || null,
                voucher_code: this.voucher?.code ?? null,
            };
        },
    };
}
