// Off-canvas sidebar state for tablet/phone widths.
export default function sidebar() {
    return {
        open: false,
        toggle() {
            this.open = !this.open;
        },
        close() {
            this.open = false;
        },
    };
}
