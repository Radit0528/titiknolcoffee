// JSON POST with Laravel's CSRF token. Never throws on HTTP errors:
// returns { ok, status, data } so callers can show the server's message.
export async function postJson(url, payload) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });
        const data = await response.json().catch(() => ({}));
        return { ok: response.ok, status: response.status, data };
    } catch {
        return { ok: false, status: 0, data: { message: 'Koneksi ke server terputus. Coba lagi.' } };
    }
}

// First human-readable error from a Laravel JSON error response.
export function errorMessage(data, fallback = 'Terjadi kesalahan. Coba lagi.') {
    if (data?.errors) {
        const first = Object.values(data.errors)[0];
        if (Array.isArray(first) && first.length) return first[0];
    }
    return data?.message || fallback;
}
