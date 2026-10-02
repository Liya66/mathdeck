import { test } from 'node:test';
import assert from 'node:assert/strict';
import { preview, structureOf, evaluate, render } from '../../public/app/js/expression.js';

const RULES = { minimumCards: 3, maximumCards: 7, requireIntegerResult: true };

const operand = (value, id = `n${value}`) => ({ id, kind: 'operand', value, operator: null });
const operator = (symbol, id = `o${symbol}`) => ({ id, kind: 'operator', value: null, operator: symbol });

const parse = (spec) =>
  spec.split(' ').map((token, index) =>
    /^-?\d+$/.test(token) ? operand(Number(token), `c${index}`) : operator(token, `c${index}`));

test('precedence is honoured, matching the engine', () => {
  assert.equal(evaluate(parse('2 + 3 * 4')).toString(), '14');
  assert.equal(evaluate(parse('10 - 6 / 2')).toString(), '7');
  assert.equal(evaluate(parse('10 - 3 - 2')).toString(), '5');
  assert.equal(evaluate(parse('1 / 3 * 3')).toString(), '1');
});

test('structure is checked before arithmetic', () => {
  assert.deepEqual(structureOf(parse('3 4 +'), RULES), { ok: false, reason: 'MALFORMED' });
  assert.deepEqual(structureOf(parse('3 +'), RULES), { ok: false, reason: 'TOO_FEW_CARDS' });
  assert.deepEqual(structureOf(parse('3 + 4'), RULES), { ok: true });
  assert.deepEqual(structureOf(parse('1 + 1 + 1 + 1 + 1'), { minimumCards: 3, maximumCards: 3 }), {
    ok: false,
    reason: 'TOO_MANY_CARDS',
  });
});

test('a solving arrangement is recognised', () => {
  const result = preview(parse('2 + 3 * 4'), 14, RULES);

  assert.equal(result.status, 'solves');
  assert.equal(result.text, '2 + 3 × 4 = 14');
});

test('ignoring precedence gets named, not just marked wrong', () => {
  const result = preview(parse('2 + 3 * 4'), 20, RULES);

  assert.equal(result.status, 'misses');
  assert.match(result.hint, /× and ÷ happen before/);
});

test('a wrong answer where precedence does not apply is not blamed on precedence', () => {
  // Both readings of 2 × 3 + 4 agree, so this is an ordinary miss.
  const result = preview(parse('2 * 3 + 4'), 99, RULES);

  assert.equal(result.status, 'misses');
  assert.match(result.hint, /does not reach the target/);
});

test('off by one is called out', () => {
  assert.match(preview(parse('3 + 4'), 8, RULES).hint, /one away/);
});

test('division by zero and fractions are explained', () => {
  assert.equal(preview(parse('6 / 0'), 5, RULES).reason, 'DIVISION_BY_ZERO');
  assert.equal(preview(parse('7 / 2'), 3, RULES).reason, 'NON_INTEGER_RESULT');
});

test('an empty or partial arrangement is not an error', () => {
  assert.equal(preview([], 7, RULES).status, 'empty');
  assert.equal(preview(parse('3 +'), 7, RULES).status, 'incomplete');
});

test('operators render as maths, not as code', () => {
  assert.equal(render(parse('6 / 2 * 3')), '6 ÷ 2 × 3');
  assert.equal(render(parse('5 - 1')), '5 − 1');
});
