// Condition-node regression tests.
//
//   node node/test/flow-condition.mjs
//
// Locks down the 4DGE Connect bug report: a Condition on a Google Sheets Read
// result ("Variable: customer.Customer_ID, Operator: Is set") returned FALSE
// even though the same {{customer.Customer_ID}} interpolated correctly in a
// Message node.
//
// Root cause was NOT the resolver the report blamed — the Sheets node already
// flattens each cell to a `customer.Customer_ID` key, so the flat lookup found
// it. The builder emits operator `exists`, and the runtime switch only knew
// equals/contains/greater_than/... so `exists` (plus `gt` and `lt`) fell to
// `default: return false` and could never be true.
//
// No network, no DB, no socket — pure evaluator.

import { evaluateFlowConditions, _flowResolveVar, _flowSubst } from '../services/flowService.js';

let pass = 0, fail = 0;
const ok = (name, got, want) => {
  const good = got === want;
  good ? pass++ : fail++;
  console.log(`${good ? 'PASS' : 'FAIL'}  ${name}${good ? '' : `  → got ${JSON.stringify(got)}, want ${JSON.stringify(want)}`}`);
};

// Mirrors what executeGoogleSheetsNode actually writes: the raw row object
// under `saveAs` AND one-level-flattened dotted keys.
const sheetsSession = () => ({
  userVariables: {
    phone: '27797400465',
    user_message: 'hi',
    customer: { Customer_ID: 'CUST-NLA-0007', Mobile_Number: '27797400465', Notes: '' },
    'customer.Customer_ID': 'CUST-NLA-0007',
    'customer.Mobile_Number': '27797400465',
    'customer.Notes': '',
  },
});

// A save that produced an object but no flattened keys (deeper paths, and any
// node that stashes a raw object) — only the nested walk can resolve these.
const nestedSession = () => ({
  userVariables: {
    user_message: 'hi',
    customer: { Customer_ID: 'CUST-NLA-0007', address: { city: 'Cape Town', zip: '' } },
  },
});

const cond = (variable, operator, value) => [{ variable, operator, value }];
const evalC = (session, variable, operator, value) =>
  evaluateFlowConditions(session, cond(variable, operator, value), [], 'test');

console.log('\n── The reported reproduction ──');
ok('customer.Customer_ID + exists → TRUE', evalC(sheetsSession(), 'customer.Customer_ID', 'exists'), true);
ok('customer + exists → TRUE',             evalC(sheetsSession(), 'customer', 'exists'), true);

console.log('\n── Flat variables ──');
ok('phone + exists → TRUE',            evalC(sheetsSession(), 'phone', 'exists'), true);
ok('phone + equals match → TRUE',      evalC(sheetsSession(), 'phone', 'equals', '27797400465'), true);
ok('phone + equals mismatch → FALSE',  evalC(sheetsSession(), 'phone', 'equals', '111'), false);
ok('missing + exists → FALSE',         evalC(sheetsSession(), 'nope', 'exists'), false);
ok('empty cell + exists → FALSE',      evalC(sheetsSession(), 'customer.Notes', 'exists'), false);
ok('empty cell + is_not_set → TRUE',   evalC(sheetsSession(), 'customer.Notes', 'is_not_set'), true);

console.log('\n── Nested Google Sheets result objects (no flattened keys) ──');
ok('nested customer.Customer_ID + exists → TRUE',   evalC(nestedSession(), 'customer.Customer_ID', 'exists'), true);
ok('nested customer.Customer_ID + equals → TRUE',   evalC(nestedSession(), 'customer.Customer_ID', 'equals', 'CUST-NLA-0007'), true);
ok('deep customer.address.city + exists → TRUE',    evalC(nestedSession(), 'customer.address.city', 'exists'), true);
ok('deep customer.address.zip empty → FALSE',       evalC(nestedSession(), 'customer.address.zip', 'exists'), false);
ok('deep missing branch → FALSE',                   evalC(nestedSession(), 'customer.address.country', 'exists'), false);
ok('walk through non-object → FALSE',               evalC(nestedSession(), 'customer.Customer_ID.nope', 'exists'), false);

console.log('\n── "Is set" must not answer about user_message ──');
// The comparison path falls back to user_message when the variable is blank.
// Presence tests must NOT, or an unset variable reads as "set" whenever the
// customer happens to have typed something. (sheetsSession has user_message.)
ok('missing + is_set with user_message present → FALSE', evalC(sheetsSession(), 'missing_var', 'is_set'), false);
ok('missing + is_not_set → TRUE',                        evalC(sheetsSession(), 'missing_var', 'is_not_set'), true);

console.log('\n── Legacy is_empty/is_not_empty keep the old fallback (deliberately unchanged) ──');
// Not offered by the builder, so only imported/template flows can carry them.
// They stay bug-compatible: an unset variable reports on user_message instead.
// Locked down here so nobody "fixes" it without weighing that blast radius.
ok('is_empty on unset var still reads user_message',     evalC(sheetsSession(), 'missing_var', 'is_empty'), false);
ok('is_not_empty on unset var still reads user_message', evalC(sheetsSession(), 'missing_var', 'is_not_empty'), true);

console.log('\n── Builder operator vocabulary (opChoices) all reach the runtime ──');
const nums = { userVariables: { total: '150', user_message: '' } };
ok('gt → TRUE',           evalC(nums, 'total', 'gt', '100'), true);
ok('gt → FALSE',          evalC(nums, 'total', 'gt', '200'), false);
ok('lt → TRUE',           evalC(nums, 'total', 'lt', '200'), true);
ok('greater_than alias',  evalC(nums, 'total', 'greater_than', '100'), true);
ok('less_than alias',     evalC(nums, 'total', 'less_than', '200'), true);
ok('not_contains → TRUE', evalC(sheetsSession(), 'phone', 'not_contains', 'zzz'), true);
ok('starts_with → TRUE',  evalC(sheetsSession(), 'phone', 'starts_with', '277'), true);
ok('ends_with → TRUE',    evalC(sheetsSession(), 'phone', 'ends_with', '465'), true);
ok('"Is set" spaced form', evalC(sheetsSession(), 'phone', 'Is set'), true);
ok('unknown operator → FALSE', evalC(sheetsSession(), 'phone', 'bogus_op', 'x'), false);

console.log('\n── Condition resolver === message-template resolver ──');
const s = sheetsSession();
ok('subst resolves flat dotted key', _flowSubst(s, '{{customer.Customer_ID}}'), 'CUST-NLA-0007');
ok('subst resolves nested path',     _flowSubst(nestedSession(), '{{customer.address.city}}'), 'Cape Town');
ok('subst unknown → empty string',   _flowSubst(s, '{{nope}}'), '');
ok('subst renders row as JSON, not [object Object]',
  _flowSubst(nestedSession(), '{{customer}}').startsWith('{"Customer_ID"'), true);
// Anything a Message node can print must also be "set" for a Condition.
ok('resolver agrees with condition (flat)',
  _flowResolveVar(s, 'customer.Customer_ID') === 'CUST-NLA-0007' && evalC(s, 'customer.Customer_ID', 'exists'), true);
ok('resolver agrees with condition (nested)',
  _flowResolveVar(nestedSession(), 'customer.address.city') === 'Cape Town' && evalC(nestedSession(), 'customer.address.city', 'exists'), true);

console.log('\n── Comparison fallback to user_message (unchanged behaviour) ──');
const typed = { userVariables: { user_message: 'YES' } };
ok('blank variable falls back to user_message', evaluateFlowConditions(typed, cond('', 'equals', 'yes'), [], 't'), true);
ok('unset variable falls back to user_message', evaluateFlowConditions(typed, cond('answer', 'contains', 'ye'), [], 't'), true);

console.log('\n── AND / OR chains ──');
const two = (a, b) => [a, b];
const cSet   = { variable: 'customer.Customer_ID', operator: 'exists' };
const cMatch = { variable: 'phone', operator: 'equals', value: '27797400465' };
const cNope  = { variable: 'phone', operator: 'equals', value: 'wrong' };
ok('TRUE  AND TRUE  → TRUE',  evaluateFlowConditions(sheetsSession(), two(cSet, cMatch), ['AND'], 't'), true);
ok('TRUE  AND FALSE → FALSE', evaluateFlowConditions(sheetsSession(), two(cSet, cNope),  ['AND'], 't'), false);
ok('TRUE  OR  FALSE → TRUE',  evaluateFlowConditions(sheetsSession(), two(cSet, cNope),  ['OR'],  't'), true);
ok('FALSE OR  FALSE → FALSE', evaluateFlowConditions(sheetsSession(), two(cNope, cNope), ['OR'],  't'), false);
ok('default joiner is AND',   evaluateFlowConditions(sheetsSession(), two(cSet, cNope),  [],      't'), false);

console.log('\n── Degenerate input ──');
ok('no conditions → FALSE',   evaluateFlowConditions(sheetsSession(), [], [], 't'), false);
ok('empty session → FALSE',   evaluateFlowConditions({ userVariables: {} }, cond('customer', 'exists'), [], 't'), false);
ok('null-ish session → FALSE', evaluateFlowConditions({}, cond('customer', 'exists'), [], 't'), false);

console.log(`\n${fail === 0 ? 'ALL PASS' : 'FAILURES'} — ${pass} passed, ${fail} failed\n`);
process.exit(fail === 0 ? 0 : 1);
