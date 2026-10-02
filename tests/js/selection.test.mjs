import { test } from 'node:test';
import assert from 'node:assert/strict';
import { Selection } from '../../public/app/js/selection.js';

const hand = [
  { id: 'a', kind: 'operand', value: 3 },
  { id: 'b', kind: 'operator', operator: '+' },
  { id: 'c', kind: 'operand', value: 3 },
];

test('selection keeps the order cards were laid out in', () => {
  const selection = new Selection().add('c').add('b').add('a');

  assert.deepEqual(selection.cardsFrom(hand).map((card) => card.id), ['c', 'b', 'a']);
});

test('two cards of the same value are distinct', () => {
  // 'a' and 'c' are both 3. Removing one must remove that one.
  const selection = new Selection(['a', 'b', 'c']).remove('a');

  assert.deepEqual(selection.cardIds, ['b', 'c']);
});

test('toggling adds then removes', () => {
  const once = new Selection().toggle('a');
  const twice = once.toggle('a');

  assert.equal(once.size, 1);
  assert.equal(twice.size, 0);
});

test('cards no longer in hand drop out of the selection', () => {
  const afterPlay = new Selection(['a', 'b', 'c']).prunedTo([{ id: 'b' }]);

  assert.deepEqual(afterPlay.cardIds, ['b']);
});

test('resolving ignores ids the hand does not hold', () => {
  assert.deepEqual(new Selection(['a', 'ghost']).cardsFrom(hand).map((c) => c.id), ['a']);
});
