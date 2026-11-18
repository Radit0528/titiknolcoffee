// Promo video upload: local preview, MP4 + size check, and a portrait hint.
export default function videoPicker(currentUrl = null, maxMb = 20) {
    return {
        preview: currentUrl,
        error: '',
        warning: '',
        pick(event) {
            const file = event.target.files[0];
            this.error = '';
            this.warning = '';
            if (!file) return;
            if (file.type !== 'video/mp4') {
                this.error = 'Video harus berformat MP4.';
                event.target.value = '';
                return;
            }
            if (file.size > maxMb * 1024 * 1024) {
                this.error = `Ukuran video maksimal ${maxMb} MB.`;
                event.target.value = '';
                return;
            }
            this.preview = URL.createObjectURL(file);
        },
        checkShape(event) {
            const video = event.target;
            this.warning = video.videoWidth > video.videoHeight
                ? 'Video ini landscape. Di halaman utama video ditampilkan potret (9:16), jadi sisi kiri-kanannya akan terpotong.'
                : '';
        },
    };
}
