import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.css';
import { Country } from 'country-state-city';

/**
 * Turns every <select.ts-country> on the wallet-rules page into a searchable
 * country picker (type the name, pick from the list) instead of making the
 * admin remember ISO codes. The blank option = "Any country (default)" and
 * submits an empty country_code, which the rate resolver treats as the global
 * default. data-value preselects the row's saved country.
 */
export default function () {
    const selects = document.querySelectorAll('select.ts-country');
    if (!selects.length) return;

    // Build the option list once (name → ISO-2), sorted by name.
    const countryOptions = Country.getAllCountries()
        .map((c) => ({ value: c.isoCode, text: c.name }))
        .sort((a, b) => a.text.localeCompare(b.text));

    selects.forEach((sel) => {
        const current = (sel.dataset.value || '').toUpperCase();
        new TomSelect(sel, {
            options: [{ value: '', text: 'Any country (default)' }, ...countryOptions],
            items: [current],            // preselect the saved country (or '' = default)
            maxItems: 1,
            create: false,
            allowEmptyOption: true,
            placeholder: 'Search country…',
            sortField: { field: 'text', direction: 'asc' },
        });
    });
}
