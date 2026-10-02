import { MatchApi, ApiError } from './api.js';
import { Session } from './session.js';
import { Selection } from './selection.js';
import { REASONS } from './expression.js';
import { renderJoin, renderGame, describeEvent } from './render.js';

const POLL_MS = 1500;

const state = {
  api: null,
  playerId: null,
  view: null,
  selection: new Selection(),
  log: [],
  cursor: 0,
  error: null,
  busy: false,
};

const root = document.getElementById('app');
let poller = null;

draw();

root.addEventListener('submit', async (event) => {
  if (event.target.id !== 'join-form') {
    return;
  }

  event.preventDefault();

  const form = new FormData(event.target);
  const intent = event.submitter?.value ?? 'create';

  state.error = null;

  try {
    // Sign in first: from here on the server knows who we are because it signed
    // the claim, not because we typed a name.
    const session = await Session.signIn(
      String(form.get('playerId')).trim(),
      String(form.get('passcode')),
    );

    state.playerId = session.playerId;
    state.api = new MatchApi({ token: session.token });
    const view =
      intent === 'join'
        ? await state.api.getMatch(String(form.get('matchId')).trim())
        : await state.api.createMatch('starter@1', [state.playerId, String(form.get('opponentId')).trim()]);

    adopt(view);
    await catchUp();
    startPolling();
  } catch (failure) {
    state.error = messageFor(failure);
  }

  draw();
});

root.addEventListener('click', async (event) => {
  const card = event.target.closest('[data-card-id]');

  if (card && !card.disabled) {
    state.selection = state.selection.toggle(card.dataset.cardId);
    draw();

    return;
  }

  const action = event.target.closest('button')?.id;

  if (action === 'clear') {
    state.selection = state.selection.clear();
    draw();
  } else if (action === 'play') {
    await send(() => state.api.playCards(state.view.matchId, state.selection.cardIds));
  } else if (action === 'forfeit') {
    await send(() => state.api.forfeit(state.view.matchId));
  } else if (action === 'leave') {
    stopPolling();
    state.view = null;
    state.log = [];
    state.cursor = 0;
    draw();
  }
});

async function send(request) {
  if (state.busy) {
    return;
  }

  state.busy = true;
  state.error = null;
  draw();

  try {
    const outcome = await request();

    adopt(outcome.state);
    record(outcome.events);
  } catch (failure) {
    state.error = messageFor(failure);

    // A refusal means our picture of the match was stale — the turn moved, or the
    // hand did. Re-read rather than leaving a board that lies.
    await refresh();
  } finally {
    state.busy = false;
    draw();
  }
}

/** Polls the log while it is not our turn. The cursor is what makes this cheap. */
function startPolling() {
  stopPolling();
  poller = setInterval(async () => {
    if (state.busy || !state.view || state.view.phase === 'ended') {
      return;
    }

    try {
      const before = state.cursor;
      await catchUp();

      if (state.cursor !== before) {
        await refresh();
        draw();
      }
    } catch {
      // A failed poll is not worth interrupting the player over; the next one will
      // either work or the next action will surface the problem.
    }
  }, POLL_MS);
}

function stopPolling() {
  if (poller !== null) {
    clearInterval(poller);
    poller = null;
  }
}

async function catchUp() {
  const { events } = await state.api.getEvents(state.view.matchId, state.cursor);

  record(events);
}

async function refresh() {
  if (!state.view) {
    return;
  }

  try {
    adopt(await state.api.getMatch(state.view.matchId));
  } catch (failure) {
    state.error = messageFor(failure);
  }
}

function adopt(view) {
  state.view = view;
  // Cards that were just played are gone from the hand; a selection pointing at
  // them would render as ghosts.
  state.selection = state.selection.prunedTo(view.you.hand);
}

function record(events) {
  for (const event of events) {
    if (event.seq <= state.cursor) {
      continue;
    }

    state.cursor = event.seq;
    state.log.push({ seq: event.seq, ...describeEvent(event, state.playerId) });
  }
}

function messageFor(failure) {
  if (failure instanceof ApiError) {
    return failure.reason ? (REASONS[failure.reason] ?? failure.message) : failure.message;
  }

  return 'Could not reach the server. Check the connection and try again.';
}

function draw() {
  root.innerHTML = state.view ? renderGame(state) : renderJoin(state);
}
