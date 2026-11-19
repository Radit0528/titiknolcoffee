// Owner → Pengaturan → QRIS Kedai: read the QR from an uploaded image in the
// browser (jsQR), so only the payload text is sent to the server.
export default function qrisUploader(initial = '') {
    return {
        payload: initial,
        merchant: '',
        error: '',
        async read(event) {
            const file = event.target.files[0];
            this.error = '';
            this.merchant = '';
            if (!file) return;
            try {
                const { default: jsQR } = await import('jsqr');
                const bitmap = await createImageBitmap(file);
                const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height));
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(bitmap.width * scale);
                canvas.height = Math.round(bitmap.height * scale);
                const ctx = canvas.getContext('2d');
                ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
                const image = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const code = jsQR(image.data, image.width, image.height);
                if (!code || !code.data.startsWith('000201')) {
                    this.error = 'QR tidak terbaca atau bukan QRIS. Coba gambar yang lebih jelas / tidak terpotong.';
                    return;
                }
                this.payload = code.data;
                const name = /59(\d{2})/.exec(code.data.slice(code.data.indexOf('5802ID') + 6));
                this.merchant = name ? code.data.substr(code.data.indexOf('5802ID') + 10, Number(name[1])) : 'QRIS';
            } catch {
                this.error = 'Gambar tidak bisa dibaca.';
            }
        },
    };
}
