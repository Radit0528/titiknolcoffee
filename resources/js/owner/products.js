// Product image field: local preview before upload and a "remove" option.
export default function imagePicker(currentUrl = null) {
    return {
        preview: currentUrl,
        remove: false,
        error: '',
        maxBytes: 2 * 1024 * 1024,
        pick(event) {
            const file = event.target.files[0];
            this.error = '';
            if (!file) return;
            if (file.size > this.maxBytes) {
                this.error = 'Ukuran gambar maksimal 2 MB.';
                event.target.value = '';
                return;
            }
            this.remove = false;
            this.preview = URL.createObjectURL(file);
        },
        clear() {
            this.preview = null;
            this.remove = true;
            this.$refs.file.value = '';
        },
    };
}
