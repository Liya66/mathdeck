import { test } from 'node:test';
import assert from 'node:assert/strict';
import { parseNumberList, definitionFrom, fieldsFrom } from '../../public/editor/js/form.js';

test('commas, spaces and ranges all work', () => {
  assert.deepEqual(parseNumberList('1, 2, 3'), [1, 2, 3]);
  assert.deepEqual(parseNumberList('1 2 3'), [1, 2, 3]);
  assert.deepEqual(parseNumberList('1-5'), [1, 2, 3, 4, 5]);
  assert.deepEqual(parseNumberList('1-3, 10, 20-22'), [1, 2, 3, 10, 20, 21, 22]);
});

test('ranges may run backwards, because people type them that way', () => {
  assert.deepEqual(parseNumberList('5-1'), [5, 4, 3, 2, 1]);
});

test('negatives survive', () => {
  assert.deepEqual(parseNumberList('-3, 0, 4'), [-3, 0, 4]);
});

test('duplicates collapse, since the schema demands unique values', () => {
  assert.deepEqual(parseNumberList('2, 2, 3, 2-3'), [2, 3]);
});

test('junk is dropped rather than guessed at', () => {
  assert.deepEqual(parseNumberList('1, banana, 3'), [1, 3]);
  assert.deepEqual(parseNumberList(''), []);
  assert.deepEqual(parseNumberList(null), []);
});

test('a runaway range cannot hang the editor', () => {
  assert.equal(parseNumberList('1-999999').length, 200);
});

test('the form round-trips a definition without losing anything', () => {
  const original = {
    schemaVersion: 1,
    name: 'Halving',
    description: 'Twos and fours',
    operands: { values: [2, 4, 6], copies: 5 },
    operators: { symbols: ['+', '/'], copies: 9 },
    targets: [1, 2, 3],
    play: {
      handSize: 8,
      minimumCards: 3,
      maximumCards: 5,
      targetsPerMatch: 6,
      requireIntegerResult: false,
      baseScore: 20,
    },
  };

  assert.deepEqual(definitionFrom(fieldsFrom(original)), original);
});

test('an empty name does not produce an unnamed deck', () => {
  assert.equal(definitionFrom({ name: '   ' }).name, 'Untitled deck');
});

test('description is omitted rather than sent empty', () => {
  assert.equal('description' in definitionFrom({ name: 'x', description: '  ' }), false);
});
