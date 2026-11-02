// Voucher, totals and cash payment. Mirrors CheckoutService::price() so the
// cashier sees the right numbers; the server recomputes and has the final say.
import { postJson, errorMessage } from '../components/http';

export default function checkout(config) {
    return {
        voucherInput: '',
        voucher: null,          // { code, name } once the server accepted it
        voucherDiscount: 0,
        voucherError: '',
        checkoutOpen: false,
        cashReceived: '',
        busy: false,
        error: '',

        get discount() {
            return Math.min(this.voucherDiscount, this.subtotal);
        },
        get serviceCharge() {
            return Math.round(((this.subtotal - this.discount) * config.serviceRate) / 100);
        },
        get tax() {
            return Math.round(((this.subtotal - this.discount + this.serviceCharge) * config.taxRate) / 100);
        },
        get total() {
            return this.subtotal - this.discount + this.serviceCharge + this.tax;
        },
        get rounding() {
            const unit = config.cashRounding;
            return unit > 0 ? Math.round(this.total / unit) * unit - this.total : 0;
        },
        get cashTotal() {
            return this.total + this.rounding;
        },
        get cashValue() {
            return parseInt(this.cashReceived, 10) || 0;
        },
        get change() {
            return this.cashValue - this.cashTotal;
        },
        get canPay() {
            return this.lines.length > 0 && this.cashValue >= this.cashTotal && !this.busy;
        },
        // Quick cash buttons: exact amount plus common notes above the total.
        get quickCash() {
            const options = [this.cashTotal];
            for (const note of [20000, 50000, 100000]) {
                const amount = Math.ceil(this.cashTotal / note) * note;
                if (amount > this.cashTotal && !options.includes(amount)) options.push(amount);
            }
            return options.slice(0, 4);
        },

        async applyVoucher() {
            const code = this.voucherInput.trim().toUpperCase();
            if (!code || !this.lines.length) return;
            this.voucherError = '';
            const { ok, data } = await postJson(config.urls.quote, { ...this.payload(), voucher_code: code });
            if (!ok) {
                this.voucherError = errorMessage(data, 'Voucher tidak dapat dipakai.');
                return;
            }
            this.voucher = data.voucher;
            this.voucherDiscount = data.discount;
            this.voucherInput = '';
        },
        removeVoucher() {
            this.voucher = null;
            this.voucherDiscount = 0;
            this.voucherError = '';
        },
        // Re-check the voucher when the cart changes (min. purchase, % discount).
        cartChanged() {
            if (!this.voucher) return;
            clearTimeout(this._quoteTimer);
            this._quoteTimer = setTimeout(async () => {
                if (!this.lines.length) return this.removeVoucher();
                const { ok, data } = await postJson(config.urls.quote, this.payload());
                if (ok) {
                    this.voucherDiscount = data.discount;
                } else {
                    this.removeVoucher();
                    this.voucherError = errorMessage(data);
                }
            }, 300);
        },

        openCheckout() {
            if (!this.lines.length) return;
            this.error = '';
            this.cashReceived = '';
            this.checkoutOpen = true;
            this.$nextTick(() => this.$refs.cash?.focus());
        },
        closeCheckout() {
            if (!this.busy) this.checkoutOpen = false;
        },
        setCash(amount) {
            this.cashReceived = String(amount);
        },

        async pay() {
            if (!this.canPay) return;
            this.busy = true;
            this.error = '';
            const { ok, data } = await postJson(config.urls.checkout, {
                ...this.payload(),
                client_token: config.clientToken,
                cash_received: this.cashValue,
                draft_id: this.draftId,
            });
            if (ok && data.redirect) {
                window.location.assign(data.redirect);
                return; // keep the button disabled while the receipt loads
            }
            this.busy = false;
            this.error = errorMessage(data, 'Pembayaran gagal diproses.');
        },

        async saveDraft() {
            if (!this.lines.length || this.busy) return;
            this.busy = true;
            const { ok, data } = await postJson(config.urls.draft, { ...this.payload(), draft_id: this.draftId });
            if (ok && data.redirect) {
                window.location.assign(data.redirect);
                return;
            }
            this.busy = false;
            this.flash(errorMessage(data, 'Draft gagal disimpan.'));
        },
    };
}
