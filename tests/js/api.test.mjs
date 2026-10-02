import { test } from 'node:test';
import assert from 'node:assert/strict';
import { MatchApi, ApiError } from '../../public/app/js/api.js';

function stubFetch(handler) {
  const calls = [];

  const fetchImpl = async (url, options) => {
    calls.push({ url, options });

    return handler(calls.length, { url, options });
  };

  return { fetchImpl, calls };
}

const ok = (body) => ({ ok: true, status: 200, json: async () => body });

test('the player id is never sent; the token says who you are', async () => {
  const { fetchImpl, calls } = stubFetch(() => ok({}));

  await new MatchApi({ token: 'alice', fetchImpl }).playCards('m1', ['a', 'b', 'c']);

  const { options } = calls[0];
  assert.equal(options.headers.Authorization, 'Bearer alice');
  assert.equal(JSON.parse(options.body).playerId, undefined);
  assert.deepEqual(JSON.parse(options.body), { type: 'play_cards', cardIds: ['a', 'b', 'c'] });
});

test('no seed is ever sent when creating a match', async () => {
  const { fetchImpl, calls } = stubFetch(() => ok({}));

  await new MatchApi({ token: 'alice', fetchImpl }).createMatch('deck-v1', ['alice', 'bob']);

  assert.equal(JSON.parse(calls[0].options.body).seed, undefined);
});

test('creating a match carries an idempotency key too', async () => {
  // The server requires it, and a slow response to "new match" is exactly what
  // makes someone tap again.
  const { fetchImpl, calls } = stubFetch(() => ok({}));

  await new MatchApi({ token: 'alice', fetchImpl }).createMatch('deck-v1', ['alice', 'bob']);

  assert.match(calls[0].options.headers['Idempotency-Key'], /\S/);
});

test('a retried create reuses its key, so it cannot make two matches', async () => {
  const { fetchImpl, calls } = stubFetch((attempt) => {
    if (attempt === 1) {
      throw new TypeError('network down');
    }

    return ok({ matchId: 'm1' });
  });

  await new MatchApi({ token: 'alice', fetchImpl }).createMatch('deck-v1', ['alice', 'bob']);

  assert.equal(calls.length, 2);
  assert.equal(
    calls[0].options.headers['Idempotency-Key'],
    calls[1].options.headers['Idempotency-Key'],
  );
});

test('commands carry an idempotency key', async () => {
  const { fetchImpl, calls } = stubFetch(() => ok({}));

  await new MatchApi({ token: 'alice', fetchImpl }).playCards('m1', ['a']);

  assert.match(calls[0].options.headers['Idempotency-Key'], /\S/);
});

test('a retry after a transport failure reuses the same key', async () => {
  // The first attempt may well have reached the server. A fresh key would play the
  // hand twice.
  const { fetchImpl, calls } = stubFetch((attempt) => {
    if (attempt === 1) {
      throw new TypeError('network down');
    }

    return ok({ replayed: true });
  });

  const result = await new MatchApi({ token: 'alice', fetchImpl }).playCards('m1', ['a']);

  assert.equal(calls.length, 2);
  assert.equal(
    calls[0].options.headers['Idempotency-Key'],
    calls[1].options.headers['Idempotency-Key'],
  );
  assert.equal(result.replayed, true);
});

test('a refusal is surfaced with its reason code and not retried', async () => {
  const { fetchImpl, calls } = stubFetch(() => ({
    ok: false,
    status: 409,
    json: async () => ({ title: 'Command refused', detail: 'It is alice\'s turn.', reason: 'NOT_YOUR_TURN' }),
  }));

  await assert.rejects(
    () => new MatchApi({ token: 'bob', fetchImpl }).playCards('m1', ['a']),
    (error) => {
      assert.ok(error instanceof ApiError);
      assert.equal(error.status, 409);
      assert.equal(error.reason, 'NOT_YOUR_TURN');

      return true;
    },
  );

  assert.equal(calls.length, 1, 'a 409 is an answer, not a failure to retry');
});

test('the events cursor is passed through', async () => {
  const { fetchImpl, calls } = stubFetch(() => ok({ since: 4, events: [] }));

  await new MatchApi({ token: 'alice', fetchImpl }).getEvents('m1', 4);

  assert.match(calls[0].url, /\/v1\/matches\/m1\/events\?since=4$/);
});
