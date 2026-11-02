// Confirmation dialog in front of destructive forms (delete).
// The form only submits after the user confirms, and the submit button is
// disabled afterwards so a double click cannot send it twice.
export default function confirmDialog() {
    return {
        open: false,
        submitting: false,
        show() {
            this.open = true;
            this.$nextTick(() => this.$refs.cancel?.focus());
        },
        hide() {
            if (!this.submitting) this.open = false;
        },
        confirm() {
            if (this.submitting) return;
            this.submitting = true;
            this.$refs.form.submit();
        },
    };
}
