// Condition-node ADVERSARIAL tests — "the client has no idea what they're doing".
//
//   node node/test/flow-condition-messy.mjs
//
// flow-condition.mjs proves the happy path. This one proves the runtime does
// something SANE (and never throws) when a real, confused human fills the
// Condition panel in: braces pasted into the variable box, wrong casing, stray
// spaces, column headers with dots and spaces, junk operators, broken chains.
//
// A crash here takes the whole flow session down mid-conversation, so "never
// throws" matters as much as "returns the right answer".

import { evaluateFlowConditions, _flowResolveVar, _flowSubst } from '../services/flowService.js';

let pass = 0, fail = 0, crash = 0;
const ok = (name, got, want) => {
  const good = got === want;
  good ? pass++ : fail++;
  console.log(`${good ? 'PASS' : 'FAIL'}  ${name}${good ? '' : `  → got ${JSON.stringify(got)}, want ${JSON.stringify(want)}`}`);
};
// For inputs where any answer is defensible — we only require "no crash".
const noCrash = (name, fn) => {
  try { const r = fn(); pass++; console.log(`PASS  ${name} (no crash → ${JSON.stringify(r)})`); }
  catch (e) { crash++; fail++; console.log(`CRASH ${name} → ${e.message}`); }
};

// A realistic Sheets row from a messy real-world sheet: spaces in headers,
// a dot in a header, mixed casing, a blank cell.
const messyRow = {
  'Customer_ID': 'CUST-NLA-0007',
  'Mobile_Number': '27797400465',
  'First Name': 'Barrath',        // space in header
  'Cust.Ref': 'R-99',             // dot INSIDE the header
  'Notes': '',                    // blank cell
  'Balance': '0',                 // string zero
};
const session = () => {
  const sv = {
    phone: '27797400465',
    user_message: 'hello there',
    customer: messyRow,
  };
  for (const [k, v] of Object.entries(messyRow)) sv[`customer.${k}`] = String(v ?? '');
  return { userVariables: sv };
};

const ev = (variable, operator, value) =>
  evaluateFlowConditions(session(), [{ variable, operator, value }], [], 'messy');

console.log('\n── Client pastes the {{braces}} into the Variable box ──');
// They reference it as {{customer.Customer_ID}} in Message nodes, so of course
// they type the same thing here. Must not silently be FALSE forever.
ok('{{customer.Customer_ID}} + exists',    ev('{{customer.Customer_ID}}', 'exists'), true);
ok('{{ customer.Customer_ID }} + exists',  ev('{{ customer.Customer_ID }}', 'exists'), true);
ok('{{customer}} + exists',                ev('{{customer}}', 'exists'), true);
ok('{{phone}} + equals',                   ev('{{phone}}', 'equals', '27797400465'), true);
ok('{{missing}} + exists → FALSE',         ev('{{missing}}', 'exists'), false);

console.log('\n── Stray whitespace ──');
ok('leading/trailing spaces',        ev('  customer.Customer_ID  ', 'exists'), true);
ok('spaces around the dot',          ev('customer . Customer_ID', 'exists'), true);
ok('tab/newline in name',            ev('\tcustomer.Customer_ID\n', 'exists'), true);

console.log('\n── Wrong casing (client retypes the header from memory) ──');
ok('all lower',   ev('customer.customer_id', 'exists'), true);
ok('all upper',   ev('CUSTOMER.CUSTOMER_ID', 'exists'), true);
ok('mixed',       ev('Customer.CuStOmEr_Id', 'exists'), true);
ok('operator upper-cased',  ev('customer.Customer_ID', 'EXISTS'), true);
ok('operator "Is Set"',     ev('customer.Customer_ID', 'Is Set'), true);
ok('operator "IS_SET"',     ev('customer.Customer_ID', 'IS_SET'), true);

console.log('\n── Sheet headers humans actually use ──');
ok('space in header  customer.First Name', ev('customer.First Name', 'exists'), true);
ok('DOT inside header customer.Cust.Ref',  ev('customer.Cust.Ref', 'exists'), true);
ok('blank cell customer.Notes → FALSE',    ev('customer.Notes', 'exists'), false);
ok('string "0" is set (not falsy!)',       ev('customer.Balance', 'exists'), true);

console.log('\n── Junk / empty operator + variable ──');
ok('empty variable + exists → FALSE',   ev('', 'exists'), false);
ok('null variable + exists → FALSE',    ev(null, 'exists'), false);
ok('undefined variable + exists',       ev(undefined, 'exists'), false);
ok('unknown operator → FALSE',          ev('phone', 'totally_bogus', 'x'), false);
ok('empty operator → equals default',   ev('phone', '', '27797400465'), true);
ok('null operator → equals default',    ev('phone', null, '27797400465'), true);

console.log('\n── Shapes the builder should never emit, but imports/hand-edits do ──');
noCrash('conditions = null',            () => evaluateFlowConditions(session(), null, [], 'm'));
noCrash('conditions = undefined',       () => evaluateFlowConditions(session(), undefined, [], 'm'));
noCrash('conditions = {} (not array)',  () => evaluateFlowConditions(session(), {}, [], 'm'));
noCrash('conditions = "string"',        () => evaluateFlowConditions(session(), 'nope', [], 'm'));
noCrash('conditions = [null]',          () => evaluateFlowConditions(session(), [null], [], 'm'));
noCrash('conditions = [undefined]',     () => evaluateFlowConditions(session(), [undefined], [], 'm'));
noCrash('conditions = [{}]',            () => evaluateFlowConditions(session(), [{}], [], 'm'));
noCrash('conditions = [1,2,3]',         () => evaluateFlowConditions(session(), [1, 2, 3], [], 'm'));
noCrash('session = null',               () => evaluateFlowConditions(null, [{ variable: 'x', operator: 'exists' }], [], 'm'));
noCrash('session = {}',                 () => evaluateFlowConditions({}, [{ variable: 'x', operator: 'exists' }], [], 'm'));
noCrash('userVariables = null',         () => evaluateFlowConditions({ userVariables: null }, [{ variable: 'x', operator: 'exists' }], [], 'm'));
noCrash('operators longer than conds',  () => evaluateFlowConditions(session(), [{ variable: 'phone', operator: 'exists' }], ['AND', 'OR', 'AND'], 'm'));
noCrash('operators = null',             () => evaluateFlowConditions(session(), [{ variable: 'phone', operator: 'exists' }, { variable: 'phone', operator: 'exists' }], null, 'm'));
noCrash('junk joiner "XOR"',            () => evaluateFlowConditions(session(), [{ variable: 'phone', operator: 'exists' }, { variable: 'nope', operator: 'exists' }], ['XOR'], 'm'));

console.log('\n── Hostile values in userVariables ──');
noCrash('circular object var', () => {
  const a = { name: 'loop' }; a.self = a;
  return evaluateFlowConditions({ userVariables: { a } }, [{ variable: 'a', operator: 'exists' }], [], 'm');
});
noCrash('circular object interpolated', () => {
  const a = { name: 'loop' }; a.self = a;
  return _flowSubst({ userVariables: { a } }, '{{a}}');
});
noCrash('variable holds null',      () => evaluateFlowConditions({ userVariables: { x: null } }, [{ variable: 'x', operator: 'exists' }], [], 'm'));
noCrash('variable holds NaN',       () => evaluateFlowConditions({ userVariables: { x: NaN } }, [{ variable: 'x', operator: 'gt', value: '1' }], [], 'm'));
noCrash('variable holds a function',() => evaluateFlowConditions({ userVariables: { x: () => 1 } }, [{ variable: 'x', operator: 'exists' }], [], 'm'));
noCrash('gt against non-numeric',   () => evaluateFlowConditions(session(), [{ variable: 'customer.First Name', operator: 'gt', value: 'abc' }], [], 'm'));
noCrash('deep path 10 levels',      () => evaluateFlowConditions(session(), [{ variable: 'customer.a.b.c.d.e.f.g.h.i', operator: 'exists' }], [], 'm'));
noCrash('__proto__ path',           () => evaluateFlowConditions(session(), [{ variable: '__proto__.polluted', operator: 'exists' }], [], 'm'));
noCrash('constructor path',         () => evaluateFlowConditions(session(), [{ variable: 'constructor.name', operator: 'exists' }], [], 'm'));
noCrash('huge string value',        () => evaluateFlowConditions({ userVariables: { x: 'y'.repeat(200000) } }, [{ variable: 'x', operator: 'contains', value: 'y' }], [], 'm'));

console.log('\n── Prototype pollution must NOT report "set" ──');
// A path walking into Object.prototype would make bogus conditions fire true.
ok('__proto__.polluted → FALSE',  ev('__proto__.polluted', 'exists'), false);
ok('customer.toString → FALSE',   ev('customer.toString', 'exists'), false);
ok('customer.constructor → FALSE', ev('customer.constructor', 'exists'), false);
ok('customer.hasOwnProperty → FALSE', ev('customer.hasOwnProperty', 'exists'), false);

console.log('\n── Multi-rule chains a confused client builds ──');
const C = (variable, operator, value) => ({ variable, operator, value });
ok('3 rules all AND, all true',
  evaluateFlowConditions(session(), [C('phone','exists'), C('customer.Customer_ID','exists'), C('customer.Mobile_Number','equals','27797400465')], ['AND','AND'], 'm'), true);
ok('3 rules AND with one false',
  evaluateFlowConditions(session(), [C('phone','exists'), C('customer.Notes','exists'), C('phone','exists')], ['AND','AND'], 'm'), false);
ok('3 rules OR rescues the false',
  evaluateFlowConditions(session(), [C('customer.Notes','exists'), C('nope','exists'), C('phone','exists')], ['OR','OR'], 'm'), true);
ok('mixed AND then OR (left-to-right)',
  evaluateFlowConditions(session(), [C('phone','exists'), C('nope','exists'), C('phone','exists')], ['AND','OR'], 'm'), true);
ok('lowercase "and" joiner still ANDs',
  evaluateFlowConditions(session(), [C('phone','exists'), C('nope','exists')], ['and'], 'm'), false);
ok('lowercase "or" joiner still ORs',
  evaluateFlowConditions(session(), [C('phone','exists'), C('nope','exists')], ['or'], 'm'), true);

console.log('\n── The client\'s exact broken setup, every way they might type it ──');
for (const v of ['customer.Customer_ID', '{{customer.Customer_ID}}', ' customer.Customer_ID ', 'CUSTOMER.CUSTOMER_ID', 'customer . Customer_ID'])
  ok(`"${v}" + Is set → TRUE`, ev(v, 'exists'), true);

console.log(`\n${fail === 0 ? 'ALL PASS' : 'FAILURES'} — ${pass} passed, ${fail} failed, ${crash} crashed\n`);
process.exit(fail === 0 ? 0 : 1);
