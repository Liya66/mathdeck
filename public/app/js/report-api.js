import { ApiError } from './api.js';

export class ReportApi {
  constructor({ token, baseUrl = '', fetchImpl = globalThis.fetch?.bind(globalThis) } = {}) {
    this.token = token;
    this.baseUrl = baseUrl;
    this.fetchImpl = fetchImpl;
  }

  overview(filter) {
    return this.#get('overview', filter);
  }

  progression(filter) {
    return this.#get('progression', filter);
  }

  errors(filter) {
    return this.#get('errors', filter);
  }

  latency(filter) {
    return this.#get('latency', filter);
  }

  decks() {
    return this.#send('/v1/decks');
  }

  async #get(report, filter) {
    return this.#send(`/v1/reports/${report}${queryFrom(filter)}`);
  }

  async #send(path) {
    const response = await this.fetchImpl(`${this.baseUrl}${path}`, {
      headers: { Authorization: `Bearer ${this.token}` },
    });

    const payload = await response.json().catch(() => null);

    if (!response.ok) {
      throw new ApiError(response.status, payload);
    }

    return payload;
  }
}

export function queryFrom(filter = {}) {
  const parts = Object.entries(filter)
    .filter(([, value]) => value !== undefined && value !== null && value !== '')
    .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value)}`);

  return parts.length === 0 ? '' : `?${parts.join('&')}`;
}
