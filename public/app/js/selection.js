/**
 * The cards the player has laid out, in order.
 *
 * Kept separate from rendering because ordering rules are the kind of thing that
 * looks obvious and then is not: the same card value can appear twice in a hand, so
 * selection is by card id, and removing one must remove that one.
 */
export class Selection {
  constructor(cardIds = []) {
    this.cardIds = [...cardIds];
  }

  toggle(cardId) {
    return this.has(cardId) ? this.remove(cardId) : this.add(cardId);
  }

  add(cardId) {
    return new Selection([...this.cardIds, cardId]);
  }

  remove(cardId) {
    const index = this.cardIds.indexOf(cardId);

    if (index === -1) {
      return this;
    }

    return new Selection([...this.cardIds.slice(0, index), ...this.cardIds.slice(index + 1)]);
  }

  has(cardId) {
    return this.cardIds.includes(cardId);
  }

  clear() {
    return new Selection();
  }

  get size() {
    return this.cardIds.length;
  }

  /** Resolves against a hand, dropping ids the hand no longer holds. */
  cardsFrom(hand) {
    const byId = new Map(hand.map((card) => [card.id, card]));

    return this.cardIds.map((id) => byId.get(id)).filter((card) => card !== undefined);
  }

  /** After a play, the hand changes; anything no longer held must not stay selected. */
  prunedTo(hand) {
    const held = new Set(hand.map((card) => card.id));

    return new Selection(this.cardIds.filter((id) => held.has(id)));
  }
}
