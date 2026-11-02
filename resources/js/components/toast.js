// Flash messages from the session, auto-dismissed after a few seconds.
export default function toast(timeout = 4500) {
    return {
        visible: true,
        init() {
            setTimeout(() => (this.visible = false), timeout);
        },
        close() {
            this.visible = false;
        },
    };
}
