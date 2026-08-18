// Google Sheets WRITE — column mapping regression tests.
//
//   node node/test/flow-sheets-write.mjs
//
// Locks down the 4DGE Connect report: a column whose mapping was blank/null
// wrote the literal text "[object Object]" into the cell (seen on Timestamp
// and Exception_Tag).
//
// Cause: the row builder did `_gSubst(session, c?.value ?? c)`. `??` only
// falls back on null/undefined, so a column {column:'Timestamp', value:null}
// passed the WHOLE COLUMN OBJECT into _gSubst, which did String(s || '') →
// "[object Object]". An empty-string mapping was fine ('' is not null), which
// is why only null/omitted mappings broke.
//
// The `?? c` was there for LEGACY columns saved as plain strings — those must
// keep working, so the fix narrows the fallback to non-object columns only.

import { buildSheetRowValues } from '../services/flowService.js';

let pass = 0, fail = 0;
const ok = (name, got, want) => {
  const good = JSON.stringify(got) === JSON.stringify(want);
  good ? pass++ : fail++;
  console.log(`${good ? 'PASS' : 'FAIL'}  ${name}${good ? '' : `\n        got  ${JSON.stringify(got)}\n        want ${JSON.stringify(want)}`}`);
};
const hasNoObjectObject = (name, got) => {
  const bad = got.some(v => String(v).includes('[object'));
  bad ? fail++ : pass++;
  console.log(`${bad ? 'FAIL' : 'PASS'}  ${name}${bad ? `  → leaked ${JSON.stringify(got)}` : ''}`);
};

const session = () => ({
  userVariables: {
    name: 'Barrath',
    phone: '27797400465',
    customer: { Customer_ID: 'CUST-NLA-0007' },
    'customer.Customer_ID': 'CUST-NLA-0007',
    blank_var: '',
  },
});

console.log('\n── The reported defect: blank/null mapping → EMPTY cell, never "[object Object]" ──');
ok('value: null      → ""',       buildSheetRowValues(session(), [{ column: 'Timestamp', value: null }]), ['']);
ok('value: undefined → ""',       buildSheetRowValues(session(), [{ column: 'Exception_Tag', value: undefined }]), ['']);
ok('value key omitted → ""',      buildSheetRowValues(session(), [{ column: 'Exception_Tag' }]), ['']);
ok('value: ""        → ""',       buildSheetRowValues(session(), [{ column: 'Notes', value: '' }]), ['']);
ok('value: "   "     → "   "',    buildSheetRowValues(session(), [{ column: 'Notes', value: '   ' }]), ['   ']);

console.log('\n── The client\'s exact row ──');
ok('Timestamp + Exception_Tag both null, real columns unaffected',
  buildSheetRowValues(session(), [
    { column: 'Name', value: '{{name}}' },
    { column: 'Timestamp', value: null },
    { column: 'Customer_ID', value: '{{customer.Customer_ID}}' },
    { column: 'Exception_Tag', value: null },
  ]),
  ['Barrath', '', 'CUST-NLA-0007', '']);

console.log('\n── System-generated execution timestamp ──');
const tsRow = buildSheetRowValues(session(), [{ column: 'Timestamp', value: '{{timestamp}}' }]);
ok('{{timestamp}} → YYYY-MM-DD HH:mm:ss', /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(tsRow[0]), true);
ok('{{now}} works too', /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(buildSheetRowValues(session(), [{ column: 'T', value: '{{now}}' }])[0]), true);
ok('{{execution_timestamp}} works too', /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(buildSheetRowValues(session(), [{ column: 'T', value: '{{execution_timestamp}}' }])[0]), true);
ok('{{date}} → YYYY-MM-DD', /^\d{4}-\d{2}-\d{2}$/.test(buildSheetRowValues(session(), [{ column: 'D', value: '{{date}}' }])[0]), true);
ok('{{time}} → HH:mm:ss', /^\d{2}:\d{2}:\d{2}$/.test(buildSheetRowValues(session(), [{ column: 'T', value: '{{time}}' }])[0]), true);
ok('{{iso_timestamp}} → ISO', /^\d{4}-\d{2}-\d{2}T.*Z$/.test(buildSheetRowValues(session(), [{ column: 'T', value: '{{iso_timestamp}}' }])[0]), true);
ok('{{TIMESTAMP}} case-insensitive', /^\d{4}-/.test(buildSheetRowValues(session(), [{ column: 'T', value: '{{TIMESTAMP}}' }])[0]), true);
ok('timestamp inside a sentence', /^Logged at \d{4}-\d{2}-\d{2} /.test(buildSheetRowValues(session(), [{ column: 'T', value: 'Logged at {{timestamp}}' }])[0]), true);
// An author's own variable must still win over the system tag.
ok("author's own {{timestamp}} var wins",
  buildSheetRowValues({ userVariables: { timestamp: 'MY-OWN-TS' } }, [{ column: 'T', value: '{{timestamp}}' }]), ['MY-OWN-TS']);

console.log('\n── Legacy plain-string columns must keep working (why `?? c` existed) ──');
ok('plain string column',          buildSheetRowValues(session(), ['{{name}}']), ['Barrath']);
ok('plain string literal',         buildSheetRowValues(session(), ['hello']), ['hello']);
ok('mixed legacy + object cols',   buildSheetRowValues(session(), ['{{name}}', { column: 'P', value: '{{phone}}' }]), ['Barrath', '27797400465']);
ok('plain empty string',           buildSheetRowValues(session(), ['']), ['']);

console.log('\n── Normal substitution still works ──');
ok('{{name}}',                     buildSheetRowValues(session(), [{ column: 'N', value: '{{name}}' }]), ['Barrath']);
ok('nested {{customer.Customer_ID}}', buildSheetRowValues(session(), [{ column: 'C', value: '{{customer.Customer_ID}}' }]), ['CUST-NLA-0007']);
ok('unknown var → ""',             buildSheetRowValues(session(), [{ column: 'X', value: '{{nope}}' }]), ['']);
ok('two tags in one cell',         buildSheetRowValues(session(), [{ column: 'X', value: '{{name}} / {{phone}}' }]), ['Barrath / 27797400465']);
ok('literal text passthrough',     buildSheetRowValues(session(), [{ column: 'X', value: 'static' }]), ['static']);
ok('var holding empty string',     buildSheetRowValues(session(), [{ column: 'X', value: '{{blank_var}}' }]), ['']);

console.log('\n── A whole object as the value must never be "[object Object]" ──');
hasNoObjectObject('object value → flattened', buildSheetRowValues(session(), [{ column: 'X', value: { title: 'Pick A' } }]));
ok('object with .title → "Pick A"', buildSheetRowValues(session(), [{ column: 'X', value: { title: 'Pick A' } }]), ['Pick A']);
hasNoObjectObject('{{customer}} whole row', buildSheetRowValues(session(), [{ column: 'X', value: '{{customer}}' }]));
hasNoObjectObject('array value',            buildSheetRowValues(session(), [{ column: 'X', value: ['a', 'b'] }]));

console.log('\n── Degenerate shapes must not crash or leak ──');
ok('cols = []',            buildSheetRowValues(session(), []), []);
ok('cols = null',          buildSheetRowValues(session(), null), []);
ok('cols = undefined',     buildSheetRowValues(session(), undefined), []);
ok('column = null',        buildSheetRowValues(session(), [null]), ['']);
ok('column = undefined',   buildSheetRowValues(session(), [undefined]), ['']);
ok('column = {}',          buildSheetRowValues(session(), [{}]), ['']);
ok('column = number',      buildSheetRowValues(session(), [42]), ['42']);
ok('value = 0 (not blank)', buildSheetRowValues(session(), [{ column: 'X', value: 0 }]), ['0']);
ok('value = false',        buildSheetRowValues(session(), [{ column: 'X', value: false }]), ['false']);
ok('session = {}',         buildSheetRowValues({}, [{ column: 'X', value: '{{name}}' }]), ['']);
ok('session = null',       buildSheetRowValues(null, [{ column: 'X', value: '{{name}}' }]), ['']);
hasNoObjectObject('every degenerate col at once',
  buildSheetRowValues(session(), [null, undefined, {}, { value: null }, { value: undefined }, [], { column: 'T', value: null }]));

console.log(`\n${fail === 0 ? 'ALL PASS' : 'FAILURES'} — ${pass} passed, ${fail} failed\n`);
process.exit(fail === 0 ? 0 : 1);
