/**
 * Exact rational arithmetic, mirroring the engine's Rational.
 *
 * The client previews the result of a play before sending it, and a preview that
 * disagrees with the server is worse than no preview at all: the learner is told
 * they are right, then told they are wrong. `1 ÷ 3 × 7 × 3` is exactly 7 and comes
 * out as 6.999999999999999 in doubles, which is the kind of disagreement this
 * avoids.
 *
 * Card values are small, so integer numerators stay far inside the exact range of a
 * double. Nothing here is a substitute for the server's answer — it is UX only.
 */
export class Rational {
  constructor(numerator, denominator = 1) {
    if (denominator === 0) {
      throw new RangeError('Division by zero.');
    }

    if (denominator < 0) {
      numerator = -numerator;
      denominator = -denominator;
    }

    const divisor = gcd(Math.abs(numerator), denominator);

    this.numerator = numerator / divisor;
    this.denominator = denominator / divisor;
    Object.freeze(this);
  }

  static of(value) {
    return new Rational(value);
  }

  add(other) {
    return new Rational(
      this.numerator * other.denominator + other.numerator * this.denominator,
      this.denominator * other.denominator,
    );
  }

  subtract(other) {
    return this.add(other.negate());
  }

  multiply(other) {
    return new Rational(this.numerator * other.numerator, this.denominator * other.denominator);
  }

  divide(other) {
    if (other.isZero()) {
      throw new RangeError('Division by zero.');
    }

    return new Rational(this.numerator * other.denominator, this.denominator * other.numerator);
  }

  negate() {
    return new Rational(-this.numerator, this.denominator);
  }

  isZero() {
    return this.numerator === 0;
  }

  isInteger() {
    return this.denominator === 1;
  }

  equalsInt(value) {
    return this.denominator === 1 && this.numerator === value;
  }

  toString() {
    return this.denominator === 1 ? String(this.numerator) : `${this.numerator}/${this.denominator}`;
  }
}

function gcd(a, b) {
  while (b !== 0) {
    [a, b] = [b, a % b];
  }

  return a === 0 ? 1 : a;
}
