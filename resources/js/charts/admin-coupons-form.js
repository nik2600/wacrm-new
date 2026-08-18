import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.css';

export default function init() {
    const plans = document.getElementById('co-plans');
    if (!plans) return;

    new TomSelect(plans, {
        plugins: ['remove_button', 'clear_button'],
        placeholder: 'Pick one or more plans (empty = any plan)…',
        maxOptions: null,
        hidePlaceholder: false,
        searchField: ['text'],
    });
}
