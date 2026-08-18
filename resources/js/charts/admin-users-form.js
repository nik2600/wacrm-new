import { Country, State, City } from 'country-state-city';
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.css';

export default function init() {
    const countrySel = document.getElementById('country');
    const stateSel   = document.getElementById('state');
    const citySel    = document.getElementById('city');
    if (!countrySel || !stateSel || !citySel) return;

    // Preselected values (server-rendered) — preserved from edit form / old('country').
    const initialCountry = countrySel.dataset.value || '';
    const initialState   = stateSel.dataset.value   || '';
    const initialCity    = citySel.dataset.value    || '';

    // Shared Tom Select options. virtualScroll keeps the city dropdown snappy
    // even when a state has thousands of cities (e.g. Maharashtra has ~5k).
    const tsOpts = {
        allowEmptyOption: true,
        maxOptions: null,        // show all matches, not just the first 50
        searchField: ['text'],
        placeholder: '— search —',
        plugins: ['clear_button'],
    };

    // Build {value, text} option arrays so Tom Select can re-render fast.
    const countryOptions = Country.getAllCountries()
        .sort((a, b) => a.name.localeCompare(b.name))
        .map((c) => ({ value: c.isoCode, text: `${c.flag ? c.flag + '  ' : ''}${c.name}` }));

    // Native <select>s must hold the current option BEFORE Tom Select takes
    // over — otherwise the preselected value disappears. Seed minimally.
    if (initialCountry) {
        const o = document.createElement('option');
        o.value = initialCountry;
        o.textContent = initialCountry;
        o.selected = true;
        countrySel.appendChild(o);
    }
    if (initialState) {
        const o = document.createElement('option');
        o.value = initialState;
        o.textContent = initialState;
        o.selected = true;
        stateSel.appendChild(o);
    }
    if (initialCity) {
        const o = document.createElement('option');
        o.value = initialCity;
        o.textContent = initialCity;
        o.selected = true;
        citySel.appendChild(o);
    }

    const tsCountry = new TomSelect(countrySel, { ...tsOpts, options: countryOptions, placeholder: 'Search country…' });
    const tsState   = new TomSelect(stateSel,   { ...tsOpts, placeholder: 'Search state…' });
    const tsCity    = new TomSelect(citySel,    { ...tsOpts, placeholder: 'Search city…' });

    const loadStates = (countryCode, preselect = '') => {
        tsState.clearOptions();
        tsState.clear(true);
        if (!countryCode) {
            tsState.disable();
            tsCity.clearOptions(); tsCity.clear(true); tsCity.disable();
            return;
        }
        const rows = State.getStatesOfCountry(countryCode).sort((a, b) => a.name.localeCompare(b.name));
        tsState.addOptions(rows.map((s) => ({ value: s.isoCode, text: s.name })));
        tsState.enable();
        if (preselect) tsState.setValue(preselect, true);
    };

    const loadCities = (countryCode, stateCode, preselect = '') => {
        tsCity.clearOptions();
        tsCity.clear(true);
        if (!countryCode || !stateCode) {
            tsCity.disable();
            return;
        }
        const rows = City.getCitiesOfState(countryCode, stateCode).sort((a, b) => a.name.localeCompare(b.name));
        tsCity.addOptions(rows.map((c) => ({ value: c.name, text: c.name })));
        tsCity.enable();
        if (preselect) tsCity.setValue(preselect, true);
    };

    // If edit form had a country preset, set it now so Tom Select shows the label.
    if (initialCountry) {
        tsCountry.setValue(initialCountry, true);
        loadStates(initialCountry, initialState);
        if (initialState) loadCities(initialCountry, initialState, initialCity);
    } else {
        tsState.disable();
        tsCity.disable();
    }

    tsCountry.on('change', (value) => loadStates(value));
    tsState.on('change',   (value) => loadCities(tsCountry.getValue(), value));
}
