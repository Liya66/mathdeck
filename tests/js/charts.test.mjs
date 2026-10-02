import { test } from 'node:test';
import assert from 'node:assert/strict';
import { niceTicks, formatMs, label, roundedRightBar } from '../../public/dashboard/js/charts.js';

test('axis stops are round numbers a person would choose', () => {
  assert.deepEqual(niceTicks(1000), [0, 200, 400, 600, 800, 1000]);
  assert.deepEqual(niceTicks(7000), [0, 2000, 4000, 6000]);
  assert.deepEqual(niceTicks(0), [0]);
});

test('the axis covers most of the range, with at least three stops', () => {
  // The obvious implementation — first step above max/count — leaves a max of 9
  // with an axis stopping at 5, so the longest bar runs off the end of its own
  // scale. This is a coverage heuristic rather than an exact property: the last
  // round stop lands short of the maximum by design, never far short.
  for (const max of [3, 9, 17, 240, 1350, 86_000]) {
    const ticks = niceTicks(max);

    assert.ok(ticks.at(-1) <= max, `axis for ${max} overshoots to ${ticks.at(-1)}`);
    assert.ok(ticks.at(-1) >= max * (2 / 3), `axis for ${max} stops short at ${ticks.at(-1)}`);
    assert.ok(ticks.length >= 3, `axis for ${max} has only ${ticks.length} stops`);
  }
});

test('durations read as a person would say them', () => {
  assert.equal(formatMs(450), '450ms');
  assert.equal(formatMs(1500), '1.5s');
  assert.equal(formatMs(42000), '42s');
});

test('reason codes become sentences a teacher can act on', () => {
  assert.equal(label('OPERATOR_PRECEDENCE_IGNORED'), 'Ignored × ÷ before + −');
  assert.equal(label('solved'), 'Solved');
  assert.equal(label('SOMETHING_NEW'), 'SOMETHING_NEW', 'An unmapped code shows itself rather than vanishing');
});

test('bars are rounded at the data end and square at the baseline', () => {
  const path = roundedRightBar(0, 0, 100, 22);

  assert.match(path, /^M 0 0 H 96 A 4 4/, 'square start, rounded end');
  assert.match(path, /H 0 Z$/);
});

test('a tiny bar does not produce a corner radius bigger than the bar', () => {
  // A 3px bar with a 4px radius would invert the path and render as a smear.
  assert.match(roundedRightBar(0, 0, 3, 22), /A 1\.5 1\.5/);
});
