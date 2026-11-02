// POS screen = catalogue + cart + checkout, composed into one Alpine component.
import { compose } from '../components/compose';
import { rupiah } from '../components/format';
import cart from './cart';
import checkout from './checkout';

export default function pos(catalog, config, draft = null) {
    return compose(
        {
            catalog,
            search: '',
            category: 'all',
            cartOpen: false, // phone/tablet: cart slides over the catalogue

            get visibleProducts() {
                const term = this.search.trim().toLowerCase();
                return this.catalog.filter(
                    (p) => (this.category === 'all' || this.category === String(p.category_id)) && (!term || p.name.toLowerCase().includes(term)),
                );
            },
            money(value) {
                return rupiah(value);
            },
            stockLabel(product) {
                if (!product.available) return 'Habis';
                const left = this.remaining(product);
                if (left <= 0) return 'Stok bahan habis';
                if (left <= 5) return `Sisa ${left}`;
                return '';
            },
            // Keyboard: "/" focuses search, Esc closes the payment dialog.
            onKey(event) {
                if (event.key === '/' && document.activeElement?.tagName !== 'INPUT' && document.activeElement?.tagName !== 'TEXTAREA') {
                    event.preventDefault();
                    this.$refs.search.focus();
                }
                if (event.key === 'Escape') this.closeCheckout();
            },
        },
        cart(catalog, draft),
        checkout(config),
    );
}
