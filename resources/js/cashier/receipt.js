// Thermal receipt page: open the print dialog as soon as it has rendered.
window.addEventListener('load', () => {
    document.querySelector('[data-print]')?.addEventListener('click', () => window.print());

    if (new URLSearchParams(window.location.search).get('autoprint') !== '0') {
        window.print();
    }
});
