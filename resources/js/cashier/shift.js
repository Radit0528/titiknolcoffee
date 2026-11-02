// Opening-cash field: quick amounts and live rupiah formatting.
import { rupiah } from '../components/format';

export default function openShift(initial = '') {
    return {
        amount: initial,
        set(value) {
            this.amount = String(value);
        },
        get formatted() {
            return rupiah(parseInt(this.amount, 10) || 0);
        },
    };
}
