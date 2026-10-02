import { test } from 'node:test';
import assert from 'node:assert/strict';
import { Session } from '../../public/app/js/session.js';

function fakeStorage() {
  const map = new Map();

  return {
    getItem: (k) => map.get(k) ?? null,
    setItem: (k, v) => map.set(k, v),
    removeItem: (k) => map.delete(k),
    size: () => map.size,
  };
}

const tomorrow = () => new Date(Date.now() + 3600_000).toISOString();

function stubFetch(payload, ok = true, status = 200) {
  const calls = [];

  return {
    calls,
    fetchImpl: async (url, options) => {
      calls.push({ url, options });

      return { ok, status, json: async () => payload };
    },
  };
}

test('signing in posts the credentials and keeps the token', async () => {
  const storage = fakeStorage();
  const { fetchImpl, calls } = stubFetch({
    token: 'v1.a.b', playerId: 'ada', role: 'student', expiresAt: tomorrow(),
  });

  const session = await Session.signIn('ada', 'play-1234', { fetchImpl, storage });

  assert.equal(calls[0].url, '/v1/tokens');
  assert.deepEqual(JSON.parse(calls[0].options.body), { playerId: 'ada', passcode: 'play-1234' });
  assert.equal(session.token, 'v1.a.b');
  assert.equal(Session.load(storage).playerId, 'ada');
});

test('a passcode is never stored, only the token it bought', async () => {
  const storage = fakeStorage();
  const { fetchImpl } = stubFetch({ token: 'v1.a.b', playerId: 'ada', role: 'student', expiresAt: tomorrow() });

  await Session.signIn('ada', 'play-1234', { fetchImpl, storage });

  assert.equal(storage.getItem(Session.STORAGE_KEY).includes('play-1234'), false);
});

test('a failed sign-in stores nothing', async () => {
  const storage = fakeStorage();
  const { fetchImpl } = stubFetch({ detail: 'nope' }, false, 401);

  await assert.rejects(() => Session.signIn('ada', 'wrong', { fetchImpl, storage }));
  assert.equal(storage.size(), 0);
});

test('an expired token is treated as no session', () => {
  const storage = fakeStorage();
  storage.setItem(Session.STORAGE_KEY, JSON.stringify({
    token: 'v1.a.b', playerId: 'ada', role: 'student',
    expiresAt: new Date(Date.now() - 1000).toISOString(),
  }));

  assert.equal(Session.load(storage), null);
});

test('corrupt storage is treated as no session, not a crash', () => {
  const storage = fakeStorage();
  storage.setItem(Session.STORAGE_KEY, 'not json');

  assert.equal(Session.load(storage), null);
});

test('signing out removes the token', async () => {
  const storage = fakeStorage();
  const { fetchImpl } = stubFetch({ token: 'v1.a.b', playerId: 'ada', role: 'student', expiresAt: tomorrow() });

  await Session.signIn('ada', 'play-1234', { fetchImpl, storage });
  Session.clear(storage);

  assert.equal(Session.load(storage), null);
});

test('the role decides what the interface offers', () => {
  const teacher = new Session({ token: 't', playerId: 'miss-lee', role: 'teacher', expiresAt: tomorrow() });
  const student = new Session({ token: 't', playerId: 'ada', role: 'student', expiresAt: tomorrow() });

  assert.equal(teacher.isTeacher(), true);
  assert.equal(student.isTeacher(), false);
});
