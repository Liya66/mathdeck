/**
 * Translation between the form a teacher fills in and the deck document the server
 * validates.
 *
 * Nothing here decides whether a deck is valid — that judgement belongs to the
 * server, which holds the schema and the balance linter. This file only has to turn
 * "1-24, 30" into numbers without losing any.
 */
const MAX_VALUES = 200;

/**
 * Accepts commas, spaces and ranges, because a teacher entering twenty-four targets
 * should not have to type twenty-four numbers.
 */
export function parseNumberList(text) {
  const values = [];
  const seen = new Set();

  for (const chunk of String(text ?? '').split(/[,\s]+/).filter(Boolean)) {
    const range = /^(-?\d+)\s*-\s*(-?\d+)$/.exec(chunk);

    if (range) {
      const [from, to] = [Number(range[1]), Number(range[2])];
      const step = from <= to ? 1 : -1;

      for (let value = from; step > 0 ? value <= to : value >= to; value += step) {
        push(values, seen, value);

        if (values.length >= MAX_VALUES) {
          return values;
        }
      }

      continue;
    }

    if (!/^-?\d+$/.test(chunk)) {
      continue; // Junk is dropped rather than guessed at; the field shows what stuck.
    }

    push(values, seen, Number(chunk));

    if (values.length >= MAX_VALUES) {
      return values;
    }
  }

  return values;
}

export function formatNumberList(values) {
  return (values ?? []).join(', ');
}

/** @returns {object} a deck document as the schema describes it */
export function definitionFrom(fields) {
  return {
    schemaVersion: 1,
    name: fields.name?.trim() || 'Untitled deck',
    ...(fields.description?.trim() ? { description: fields.description.trim() } : {}),
    operands: {
      values: parseNumberList(fields.operands),
      copies: toInt(fields.operandCopies, 3),
    },
    operators: {
      symbols: fields.operators ?? [],
      copies: toInt(fields.operatorCopies, 8),
    },
    targets: parseNumberList(fields.targets),
    play: {
      handSize: toInt(fields.handSize, 7),
      minimumCards: toInt(fields.minimumCards, 3),
      maximumCards: toInt(fields.maximumCards, 7),
      targetsPerMatch: toInt(fields.targetsPerMatch, 10),
      requireIntegerResult: Boolean(fields.requireIntegerResult),
      baseScore: toInt(fields.baseScore, 10),
    },
  };
}

export function fieldsFrom(definition) {
  const play = definition?.play ?? {};

  return {
    name: definition?.name ?? '',
    description: definition?.description ?? '',
    operands: formatNumberList(definition?.operands?.values),
    operandCopies: definition?.operands?.copies ?? 3,
    operators: definition?.operators?.symbols ?? [],
    operatorCopies: definition?.operators?.copies ?? 8,
    targets: formatNumberList(definition?.targets),
    handSize: play.handSize ?? 7,
    minimumCards: play.minimumCards ?? 3,
    maximumCards: play.maximumCards ?? 7,
    targetsPerMatch: play.targetsPerMatch ?? 10,
    requireIntegerResult: play.requireIntegerResult ?? true,
    baseScore: play.baseScore ?? 10,
  };
}

function push(values, seen, value) {
  if (!seen.has(value)) {
    seen.add(value);
    values.push(value);
  }
}

function toInt(value, fallback) {
  const parsed = Number.parseInt(String(value), 10);

  return Number.isFinite(parsed) ? parsed : fallback;
}
