import Alpine from 'alpinejs';

import { renderIcons } from './components/icons';
import toast from './components/toast';
import confirmDialog from './components/modal';
import sidebar from './components/sidebar';
import imagePicker from './owner/products';
import recipeEditor from './owner/recipes';
import purchaseForm from './owner/purchases';
import stockForm from './owner/stock';
import pos from './cashier/pos';
import openShift from './cashier/shift';

Alpine.store('sidebar', sidebar());

Alpine.data('toast', toast);
Alpine.data('confirmDialog', confirmDialog);
Alpine.data('imagePicker', imagePicker);
Alpine.data('recipeEditor', recipeEditor);
Alpine.data('purchaseForm', purchaseForm);
Alpine.data('stockForm', stockForm);
Alpine.data('pos', pos);
Alpine.data('openShift', openShift);

window.Alpine = Alpine;

// Icons first, so Alpine clones templates that already contain SVGs.
renderIcons();
Alpine.start();

// Chart.js is only downloaded on pages that have a chart (owner dashboard).
const chart = document.querySelector('[data-chart]');
if (chart) {
    import('./owner/dashboard').then(({ renderSalesChart }) => renderSalesChart(chart));
}
