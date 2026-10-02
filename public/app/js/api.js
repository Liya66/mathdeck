/**
 * Client for the MathDeck API.
 *
 * Two things this deliberately never does: send a player id (the bearer token says
 * who you are) and send a seed. Both are decided on the server, and a client that
 * could influence either could see the deck.
 */
export class ApiError extends Error {
  constructor(status, problem) {
    super(problem?.detail ?? `Request failed with ${status}`);
    this.name = 'ApiError';
    this.status = status;
    this.problem = problem ?? null;
    this.reason = problem?.reason ?? null;
  }
}

export class MatchApi {
  constructor({ token, baseUrl = '', fetchImpl = globalThis.fetch?.bind(globalThis) } = {}) {
    this.token = token;
    this.baseUrl = baseUrl;
    this.fetchImpl = fetchImpl;
  }

  createMatch(deckVersionId, playerIds) {
    return this.#send('POST', '/v1/matches', { body: { deckVersionId, playerIds } });
  }

  getMatch(matchId) {
    return this.#send('GET', `/v1/matches/${encodeURIComponent(matchId)}`);
  }

  getEvents(matchId, since = 0) {
    return this.#send('GET', `/v1/matches/${encodeURIComponent(matchId)}/events?since=${since}`);
  }

  playCards(matchId, cardIds) {
    return this.#command(matchId, { type: 'play_cards', cardIds });
  }

  forfeit(matchId) {
    return this.#command(matchId, { type: 'forfeit' });
  }

  /**
   * One idempotency key per intent, generated once and reused by the retry.
   *
   * That is the whole point of the key: on a flaky connection the first attempt may
   * well have reached the server, and a retry that invented a fresh key would play
   * the hand a second time.
   */
  async #command(matchId, command) {
    const idempotencyKey = newKey();
    const path = `/v1/matches/${encodeURIComponent(matchId)}/commands`;

    try {
      return await this.#send('POST', path, { body: command, idempotencyKey });
    } catch (failure) {
      if (failure instanceof ApiError) {
        throw failure;
      }

      // A transport failure, so we cannot know whether the server saw it. Same key.
      return this.#send('POST', path, { body: command, idempotencyKey });
    }
  }

  async #send(method, path, { body, idempotencyKey } = {}) {
    const headers = { Authorization: `Bearer ${this.token}` };

    if (body !== undefined) {
      headers['Content-Type'] = 'application/json';
    }

    if (idempotencyKey !== undefined) {
      headers['Idempotency-Key'] = idempotencyKey;
    }

    const response = await this.fetchImpl(`${this.baseUrl}${path}`, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    });

    const payload = await response.json().catch(() => null);

    if (!response.ok) {
      throw new ApiError(response.status, payload);
    }

    return payload;
  }
}

function newKey() {
  if (globalThis.crypto?.randomUUID) {
    return globalThis.crypto.randomUUID();
  }

  return `k-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}
