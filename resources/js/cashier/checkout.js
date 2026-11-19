// Voucher, totals, cash payment and DOKU (QRIS/Payment Gateway) payment.
// Mirrors CheckoutService::price() so the cashier sees the right numbers;
// the server recomputes and has the final say.
import { postJson, getJson, errorMessage } from '../components/http';
import QRCode from 'qrcode';

export default function checkout(config) {
    return {
        voucherInput: '',
        voucher: null,          // { code, name } once the server accepted it
        voucherDiscount: 0,
        voucherError: '',
        checkoutOpen: false,
        paymentMethod: 'cash',  // 'cash' | 'qris' (= DOKU) | 'qris_kedai' (shop QRIS, manual confirm)
        qrisKedaiEnabled: !!config.qrisKedai,
        cashReceived: '',
        pendingOrder: null,     // { order, payment_url, status_url, cancel_url }
        checkingStatus: false,
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
            if (this.paymentMethod !== 'cash') return 0;
            const unit = config.cashRounding;
            return unit > 0 ? Math.round(this.total / unit) * unit - this.total : 0;
        },
        get cashTotal() {
            return this.total + this.rounding;
        },
        get payableTotal() {
            return this.paymentMethod === 'cash' ? this.cashTotal : this.total;
        },
        get cashValue() {
            return parseInt(this.cashReceived, 10) || 0;
        },
        get change() {
            return this.cashValue - this.cashTotal;
        },
        get canPay() {
            if (this.lines.length === 0 || this.busy) return false;
            if (this.paymentMethod !== 'cash') return !this.pendingOrder;
            return this.cashValue >= this.cashTotal;
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

        setMethod(method) {
            if (this.busy || this.pendingOrder) return;
            this.paymentMethod = method;
            this.error = '';
            if (method === 'cash') {
                this.$nextTick(() => this.$refs.cash?.focus());
            }
        },
        openCheckout() {
            if (!this.lines.length) return;
            this.error = '';
            this.cashReceived = '';
            this.checkoutOpen = true;
            if (this.paymentMethod === 'cash') {
                this.$nextTick(() => this.$refs.cash?.focus());
            }
        },
        closeCheckout() {
            if (this.busy || this.pendingOrder) return;
            this.checkoutOpen = false;
        },
        setCash(amount) {
            this.cashReceived = String(amount);
        },

        async pay() {
            if (!this.canPay) return;
            this.busy = true;
            this.error = '';

            const body = {
                ...this.payload(),
                client_token: config.clientToken,
                method: this.paymentMethod,
                draft_id: this.draftId,
            };

            if (this.paymentMethod === 'cash') {
                body.cash_received = this.cashValue;
            }

            const { ok, data } = await postJson(config.urls.checkout, body);

            if (!ok) {
                this.busy = false;
                this.error = errorMessage(data, 'Pembayaran gagal diproses.');
                return;
            }

            if (data.redirect) {
                window.location.assign(data.redirect);
                return; // keep the button disabled while the receipt loads
            }

            if (this.paymentMethod === 'qris_kedai' && data.qr_payload) {
                this.busy = false;
                this.pendingOrder = {
                    kind: 'kedai',
                    order: data.order,
                    amount: data.amount,
                    confirm_url: data.confirm_url,
                    cancel_url: data.cancel_url,
                };
                this.$nextTick(() => this.drawQris(data.qr_payload));
                return;
            }

            if (this.paymentMethod === 'qris' && data.payment_url) {
                this.busy = false;
                this.pendingOrder = {
                    order: data.order,
                    payment_url: data.payment_url,
                    status_url: data.status_url,
                    cancel_url: data.cancel_url,
                };
                window.open(data.payment_url, '_blank', 'noopener');
                this.startStatusPolling();
                return;
            }

            this.busy = false;
            this.error = 'Respons pembayaran tidak valid.';
        },

        startStatusPolling() {
            this.stopStatusPolling();
            this._pollTimer = setInterval(() => {
                this.checkPaymentStatus(false);
            }, 4000);
        },

        stopStatusPolling() {
            if (this._pollTimer) {
                clearInterval(this._pollTimer);
                this._pollTimer = null;
            }
        },

        async checkPaymentStatus(manual = false) {
            if (!this.pendingOrder || this.checkingStatus) return;
            this.checkingStatus = true;
            if (manual) this.error = '';

            const { ok, data } = await getJson(this.pendingOrder.status_url);
            this.checkingStatus = false;

            if (!ok) {
                if (manual) this.error = errorMessage(data, 'Gagal memeriksa status pembayaran.');
                return;
            }

            if (data.paid && data.redirect) {
                this.stopStatusPolling();
                window.location.assign(data.redirect);
                return;
            }

            if (data.expired) {
                this.stopStatusPolling();
                if (data.new_client_token) config.clientToken = data.new_client_token;
                this.pendingOrder = null;
                this.error = 'Pembayaran DOKU telah kedaluwarsa atau gagal. Silakan coba lagi.';
                return;
            }

            if (manual) {
                this.error = 'Pembayaran masih menunggu diselesaikan oleh pelanggan.';
            }
        },

        async cancelPendingPayment() {
            if (!this.pendingOrder || this.busy) return;
            this.busy = true;
            this.error = '';
            this.stopStatusPolling();

            const { ok, data } = await postJson(this.pendingOrder.cancel_url);
            this.busy = false;

            if (!ok) {
                this.error = errorMessage(data, 'Gagal membatalkan pembayaran.');
                this.startStatusPolling();
                return;
            }

            if (data.new_client_token) {
                config.clientToken = data.new_client_token;
            }
            this.pendingOrder = null;
        },

        async drawQris(payload) {
            try {
                await QRCode.toCanvas(this.$refs.qrisCanvas, payload, { width: 280, margin: 2, errorCorrectionLevel: 'M' });
            } catch {
                this.error = 'QR gagal ditampilkan. Batalkan lalu coba lagi.';
            }
        },

        // QRIS Kedai has no payment notification: the cashier confirms after
        // seeing the money in the shop's merchant app.
        async confirmKedai() {
            if (!this.pendingOrder?.confirm_url || this.busy) return;
            this.busy = true;
            this.error = '';
            const { ok, data } = await postJson(this.pendingOrder.confirm_url);
            if (ok && data.redirect) {
                window.location.assign(data.redirect);
                return;
            }
            this.busy = false;
            this.error = errorMessage(data, 'Konfirmasi gagal. Coba lagi.');
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
