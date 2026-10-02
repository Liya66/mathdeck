import { ApiError } from './api.js';

/**
 * Client for the authoring endpoints. Shared between the editor and anything else
 * that needs decks, which is why it lives beside the match client rather than
 * inside the editor.
 */
export class DeckApi {
  constructor({ token, baseUrl = '', fetchImpl = globalThis.fetch?.bind(globalThis) } = {}) {
    this.token = token;
    this.baseUrl = baseUrl;
    this.fetchImpl = fetchImpl;
  }

  list() {
    return this.#send('GET', '/v1/decks');
  }

  get(deckVersionId) {
    return this.#send('GET', `/v1/decks/${encodeURIComponent(deckVersionId)}`);
  }

  create(definition) {
    return this.#send('POST', '/v1/decks', { definition });
  }

  update(deckVersionId, definition) {
    return this.#send('PUT', `/v1/decks/${encodeURIComponent(deckVersionId)}`, { definition });
  }

  /** Checks a definition that has not been saved, so the editor can warn while typing. */
  lint(definition) {
    return this.#send('POST', '/v1/decks/lint', { definition });
  }

  publish(deckVersionId) {
    return this.#send('POST', `/v1/decks/${encodeURIComponent(deckVersionId)}/publish`);
  }

  fork(deckVersionId) {
    return this.#send('POST', `/v1/decks/${encodeURIComponent(deckVersionId)}/fork`);
  }

  schema() {
    return this.#send('GET', '/v1/deck-schema');
  }

  async #send(method, path, body) {
    const headers = { Authorization: `Bearer ${this.token}` };

    if (body !== undefined) {
      headers['Content-Type'] = 'application/json';
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
