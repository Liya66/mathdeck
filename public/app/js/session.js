import { ApiError } from './api.js';

/**
 * The signed-in person, for however long this tab is open.
 *
 * Kept in sessionStorage rather than localStorage on purpose: the whole way you try
 * this thing out is two tabs playing each other, and localStorage is shared across
 * tabs of the same origin — one sign-in would silently become both players.
 *
 * A token is a bearer credential, so this is not a place to be clever. It expires,
 * it is never written to a cookie, and signing out removes it.
 */
export class Session {
  static STORAGE_KEY = 'mathdeck.session';

  constructor({ token, playerId, role, expiresAt }) {
    this.token = token;
    this.playerId = playerId;
    this.role = role;
    this.expiresAt = expiresAt;
  }

  static async signIn(playerId, passcode, { baseUrl = '', fetchImpl = globalThis.fetch?.bind(globalThis), storage } = {}) {
    const response = await fetchImpl(`${baseUrl}/v1/tokens`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ playerId, passcode }),
    });

    const payload = await response.json().catch(() => null);

    if (!response.ok) {
      throw new ApiError(response.status, payload);
    }

    const session = new Session(payload);
    session.save(storage);

    return session;
  }

  static load(storage = safeStorage()) {
    try {
      const raw = storage?.getItem(Session.STORAGE_KEY);

      if (!raw) {
        return null;
      }

      const session = new Session(JSON.parse(raw));

      return session.isExpired() ? null : session;
    } catch {
      return null;
    }
  }

  static clear(storage = safeStorage()) {
    try {
      storage?.removeItem(Session.STORAGE_KEY);
    } catch {
      // A browser with storage disabled still has a working session in memory.
    }
  }

  save(storage = safeStorage()) {
    try {
      storage?.setItem(Session.STORAGE_KEY, JSON.stringify(this));
    } catch {
      // Same: not being able to remember it is not a reason to fail the sign-in.
    }
  }

  isExpired() {
    return new Date(this.expiresAt).getTime() <= Date.now();
  }

  isTeacher() {
    return this.role === 'teacher';
  }
}

function safeStorage() {
  try {
    return globalThis.sessionStorage ?? null;
  } catch {
    return null;
  }
}
