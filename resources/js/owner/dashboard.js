// Owner dashboard: daily revenue chart. Only the Chart.js parts used are
// registered, which keeps the bundle small.
import { BarController, BarElement, CategoryScale, Chart, LinearScale, Tooltip } from 'chart.js';
import { rupiah } from '../components/format';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

export function renderSalesChart(canvas) {
    if (!canvas) return;
    const data = JSON.parse(canvas.dataset.chart);

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Omzet',
                    data: data.revenue,
                    backgroundColor: css('--color-coffee-700'),
                    hoverBackgroundColor: css('--color-accent'),
                    borderRadius: 6,
                    maxBarThickness: 36,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: (ctx) => ` ${rupiah(ctx.parsed.y)} · ${data.transactions[ctx.dataIndex]} transaksi`,
                    },
                },
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: css('--color-muted') } },
                y: {
                    beginAtZero: true,
                    border: { display: false },
                    grid: { color: css('--color-line') },
                    ticks: {
                        color: css('--color-muted'),
                        callback: (value) => (value >= 1000 ? `${value / 1000}rb` : value),
                    },
                },
            },
        },
    });
}
