// Same shape as App\Support\Format on the server: "Rp22.000", "-Rp500", "Rp0,25".
export function rupiah(value, decimals = 0) {
    const factor = 10 ** decimals;
    const rounded = Math.round(value * factor) / factor;
    const text = Math.abs(rounded).toLocaleString('id-ID', { maximumFractionDigits: decimals });
    return (rounded < 0 ? '-' : '') + 'Rp' + text;
}

// 1500 → "1.500", 0.5 → "0,5"
export function qty(value) {
    return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 3 });
}
