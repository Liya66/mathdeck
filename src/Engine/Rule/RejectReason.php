<?php

declare(strict_types=1);

namespace MathDeck\Engine\Rule;

/**
 * The vocabulary the analytics layer speaks.
 *
 * These codes are designed as pedagogy first and validation second: the point of
 * OffByOne and OperatorPrecedenceIgnored is that a teacher can act on them. Adding
 * a case here means adding a column to the error-distribution report, so add
 * deliberately and never reuse a retired code.
 *
 * What keeps the report clean is Severity, not this enum: a protocol fault throws
 * and never becomes an event, so nothing in the log is a client bug wearing a
 * misconception's clothes. (There was once an `isMisconception()` here saying which
 * codes "belong in the teacher-facing error clusters". It was never called, and it
 * excluded MALFORMED — which does appear in them. Mutation testing found it.)
 */
enum RejectReason: string
{
    // Structural: the client should have prevented these.
    case Malformed = 'MALFORMED';
    case TooFewCards = 'TOO_FEW_CARDS';
    case TooManyCards = 'TOO_MANY_CARDS';
    case CardNotInHand = 'CARD_NOT_IN_HAND';
    case NotYourTurn = 'NOT_YOUR_TURN';
    case MatchNotInPlay = 'MATCH_NOT_IN_PLAY';

    // Mathematical: the learner made a real attempt.
    case DivisionByZero = 'DIVISION_BY_ZERO';
    case NonIntegerResult = 'NON_INTEGER_RESULT';
    case WrongTarget = 'WRONG_TARGET';
    case OffByOne = 'OFF_BY_ONE';
    case OperatorPrecedenceIgnored = 'OPERATOR_PRECEDENCE_IGNORED';
}
