import { test } from 'node:test';
import assert from 'node:assert/strict';
import { Rational } from '../../public/app/js/rational.js';

test('an expression that float arithmetic gets wrong', () => {
  // 1 / 3 * 7 * 3 is exactly 7. In doubles it is 6.999999999999999, so the preview
  // would tell a learner their correct answer misses the target.
  const result = Rational.of(1).divide(Rational.of(3)).multiply(Rational.of(7)).multiply(Rational.of(3));

  assert.ok(result.equalsInt(7), `expected exactly 7, got ${result}`);
  assert.notEqual((1 / 3) * 7 * 3, 7, 'if this ever passes, doubles got better and this test is stale');
});

test('values are kept in lowest terms', () => {
  assert.equal(new Rational(6, 8).toString(), '3/4');
  assert.equal(new Rational(3, -4).toString(), '-3/4');
  assert.equal(new Rational(12, 4).toString(), '3');
  assert.equal(new Rational(0, 7).toString(), '0');
});

test('arithmetic', () => {
  assert.equal(new Rational(1, 2).add(new Rational(1, 3)).toString(), '5/6');
  assert.equal(new Rational(1, 4).subtract(new Rational(1, 2)).toString(), '-1/4');
  assert.equal(new Rational(2, 3).multiply(new Rational(3, 4)).toString(), '1/2');
  assert.equal(new Rational(1, 2).divide(new Rational(2, 3)).toString(), '3/4');
});

test('dividing by zero is refused', () => {
  assert.throws(() => Rational.of(6).divide(Rational.of(0)), RangeError);
  assert.throws(() => new Rational(1, 0), RangeError);
});
