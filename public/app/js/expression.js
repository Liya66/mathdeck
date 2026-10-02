import { Rational } from './rational.js';

/**
 * Client-side mirror of the engine's expression rules.
 *
 * This exists to give a learner instant feedback while they arrange cards, and for
 * nothing else. The server re-derives every one of these judgements and its verdict
 * is the only one that counts — if the two ever disagree, this file is the one that
 * is wrong. Reason codes are kept identical to the server's so the interface can
 * explain a local preview and a server rejection with the same words.
 */
export const REASONS = {
  TOO_FEW_CARDS: 'An equation needs at least three cards.',
  MALFORMED: 'Numbers and operators have to alternate.',
  DIVISION_BY_ZERO: 'You cannot divide by zero.',
  NON_INTEGER_RESULT: 'This deck only accepts whole-number results.',
  WRONG_TARGET: 'That does not reach the target.',
  OFF_BY_ONE: 'So close — you are one away.',
  OPERATOR_PRECEDENCE_IGNORED: 'Remember that × and ÷ happen before + and −.',
  TOO_MANY_CARDS: 'That is more cards than this deck allows.',
  CARD_NOT_IN_HAND: 'That card is not in your hand.',
  NOT_YOUR_TURN: 'It is not your turn.',
  MATCH_NOT_IN_PLAY: 'This match is over.',
};

const PRECEDENCE = { '+': 1, '-': 1, '*': 2, '/': 2 };

export function structureOf(cards, rules = { minimumCards: 3, maximumCards: 7 }) {
  if (cards.length < rules.minimumCards) {
    return { ok: false, reason: 'TOO_FEW_CARDS' };
  }

  if (cards.length > rules.maximumCards) {
    return { ok: false, reason: 'TOO_MANY_CARDS' };
  }

  if (cards.length % 2 === 0) {
    return { ok: false, reason: 'MALFORMED' };
  }

  for (const [index, card] of cards.entries()) {
    const expectsOperand = index % 2 === 0;

    if (expectsOperand !== (card.kind === 'operand')) {
      return { ok: false, reason: 'MALFORMED' };
    }
  }

  return { ok: true };
}

export function evaluate(cards) {
  const operands = cards.filter((_, i) => i % 2 === 0).map((card) => Rational.of(card.value));
  const operators = cards.filter((_, i) => i % 2 === 1).map((card) => card.operator);

  return fold(operands, operators, true);
}

export function evaluateLeftToRight(cards) {
  const operands = cards.filter((_, i) => i % 2 === 0).map((card) => Rational.of(card.value));
  const operators = cards.filter((_, i) => i % 2 === 1).map((card) => card.operator);

  return fold(operands, operators, false);
}

/**
 * What to show under the cards the player has arranged so far.
 */
export function preview(cards, target, rules) {
  if (cards.length === 0) {
    return { status: 'empty', text: '', hint: 'Tap cards to build an equation.' };
  }

  const structure = structureOf(cards, rules);

  if (!structure.ok) {
    return {
      status: structure.reason === 'TOO_FEW_CARDS' ? 'incomplete' : 'invalid',
      text: render(cards),
      hint: REASONS[structure.reason],
      reason: structure.reason,
    };
  }

  let result;

  try {
    result = evaluate(cards);
  } catch {
    return { status: 'invalid', text: render(cards), hint: REASONS.DIVISION_BY_ZERO, reason: 'DIVISION_BY_ZERO' };
  }

  if (rules?.requireIntegerResult && !result.isInteger()) {
    return {
      status: 'invalid',
      text: `${render(cards)} = ${result}`,
      hint: REASONS.NON_INTEGER_RESULT,
      reason: 'NON_INTEGER_RESULT',
    };
  }

  if (result.equalsInt(target)) {
    return { status: 'solves', text: `${render(cards)} = ${result}`, hint: `That makes ${target}.` };
  }

  return {
    status: 'misses',
    text: `${render(cards)} = ${result}`,
    hint: hintFor(cards, result, target),
    reason: 'WRONG_TARGET',
  };
}

function hintFor(cards, result, target) {
  if (precedenceIsSignificant(cards)) {
    try {
      if (evaluateLeftToRight(cards).equalsInt(target)) {
        return REASONS.OPERATOR_PRECEDENCE_IGNORED;
      }
    } catch {
      // Left to right is not evaluable either; fall through.
    }
  }

  const difference = result.subtract(Rational.of(target));

  if (difference.isInteger() && Math.abs(difference.numerator) === 1) {
    return REASONS.OFF_BY_ONE;
  }

  return REASONS.WRONG_TARGET;
}

export function precedenceIsSignificant(cards) {
  let seenLow = false;

  for (const card of cards) {
    if (card.kind !== 'operator') {
      continue;
    }

    if (PRECEDENCE[card.operator] === 1) {
      seenLow = true;
    } else if (seenLow) {
      return true;
    }
  }

  return false;
}

export function render(cards) {
  return cards.map(symbolOf).join(' ');
}

export function symbolOf(card) {
  if (card.kind === 'operand') {
    return String(card.value);
  }

  return { '+': '+', '-': '−', '*': '×', '/': '÷' }[card.operator];
}

function fold(operands, operators, respectPrecedence) {
  if (respectPrecedence) {
    const reducedOperands = [operands[0]];
    const reducedOperators = [];

    operators.forEach((operator, index) => {
      const right = operands[index + 1];

      if (PRECEDENCE[operator] > 1) {
        reducedOperands.push(apply(operator, reducedOperands.pop(), right));

        return;
      }

      reducedOperators.push(operator);
      reducedOperands.push(right);
    });

    operands = reducedOperands;
    operators = reducedOperators;
  }

  return operators.reduce(
    (accumulator, operator, index) => apply(operator, accumulator, operands[index + 1]),
    operands[0],
  );
}

function apply(operator, left, right) {
  switch (operator) {
    case '+':
      return left.add(right);
    case '-':
      return left.subtract(right);
    case '*':
      return left.multiply(right);
    case '/':
      return left.divide(right);
    default:
      throw new Error(`Unknown operator ${operator}`);
  }
}
