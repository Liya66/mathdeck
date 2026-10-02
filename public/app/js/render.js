import { preview, symbolOf, REASONS } from './expression.js';

/**
 * The client renders what the server sent. It never computes score, whose turn it
 * is, or whether a play was accepted — those arrive as state and events. The only
 * thing worked out locally is the preview under the tray, which is a hint and is
 * labelled as one.
 */
export function renderJoin({ error }) {
  return `
    <section class="mt-10 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-700">
      <h1 class="text-2xl font-semibold tracking-tight">MathDeck</h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        Build an equation from your cards that hits the target.
      </p>

      ${error ? banner(error) : ''}

      <form id="join-form" class="mt-6 grid gap-4 sm:grid-cols-2">
        <label class="block">
          <span class="text-sm font-medium">Play as</span>
          <input name="playerId" required value="ada" autocomplete="username"
                 class="mt-1 w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm shadow-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200 dark:focus:ring-sky-900">
        </label>

        <label class="block">
          <span class="text-sm font-medium">Passcode</span>
          <input name="passcode" type="password" required autocomplete="current-password"
                 class="mt-1 w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm shadow-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200 dark:focus:ring-sky-900">
        </label>

        <label class="block">
          <span class="text-sm font-medium">Opponent</span>
          <input name="opponentId" value="ben" autocomplete="off"
                 class="mt-1 w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm shadow-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200 dark:focus:ring-sky-900">
        </label>

        <label class="block">
          <span class="text-sm font-medium">Or join a match</span>
          <input name="matchId" placeholder="match id" autocomplete="off"
                 class="mt-1 w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm shadow-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200 dark:focus:ring-sky-900">
        </label>

        <div class="sm:col-span-2 flex gap-3">
          <button type="submit" name="intent" value="create"
                  class="rounded-lg bg-slate-900 dark:bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 dark:hover:bg-slate-600">
            New match
          </button>
          <button type="submit" name="intent" value="join"
                  class="rounded-lg bg-white dark:bg-slate-900 px-4 py-2 text-sm font-medium ring-1 ring-slate-300 dark:ring-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800">
            Join
          </button>
        </div>
      </form>

      <p class="mt-6 text-xs text-slate-400 dark:text-slate-500">
        Open a second tab, sign in as your opponent and join the same match id to play both
        sides. The token lives in this tab only, so the two do not overwrite each other.
      </p>
    </section>`;
}

export function renderGame(state) {
  const { view, selection, log, error, busy } = state;
  const cards = selection.cardsFrom(view.you.hand);
  const hint = preview(cards, view.target, view.rules);
  const over = view.phase === 'ended';

  return `
    ${header(view)}
    ${error ? banner(error) : ''}
    ${over ? outcome(view) : ''}

    <section class="mt-4 grid gap-4 lg:grid-cols-[1fr_20rem]">
      <div class="space-y-4">
        ${board(view)}
        ${tray(cards, hint, view, busy, over)}
        ${hand(view, selection, over)}
      </div>
      ${logPanel(log)}
    </section>`;
}

function header(view) {
  return `
    <header class="flex flex-wrap items-baseline justify-between gap-2">
      <h1 class="text-xl font-semibold tracking-tight">MathDeck</h1>
      <p class="text-xs text-slate-500 dark:text-slate-400">
        match <code class="rounded bg-slate-200 dark:bg-slate-800 px-1.5 py-0.5 font-mono">${escape(view.matchId)}</code>
        · deck ${escape(view.deckVersionId)}
        · ${view.targetsRemaining} target${view.targetsRemaining === 1 ? '' : 's'} left
      </p>
    </header>`;
}

function board(view) {
  const opponents = view.opponents
    .map(
      (opponent) => `
        <div class="text-right">
          <p class="text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">${escape(opponent.id)}</p>
          <p class="text-lg font-semibold tabular-nums">${opponent.score}</p>
          <p class="text-xs text-slate-400 dark:text-slate-500">${opponent.handCount} cards</p>
        </div>`,
    )
    .join('');

  return `
    <section class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm ring-1 ring-slate-200 dark:ring-slate-700">
      <div class="flex items-center justify-between gap-6">
        <div>
          <p class="text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">Target</p>
          <p class="text-5xl font-bold tabular-nums text-sky-600 dark:text-sky-400">${view.target}</p>
        </div>
        <div class="flex items-start gap-6">
          <div class="text-right">
            <p class="text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">You</p>
            <p class="text-lg font-semibold tabular-nums">${view.you.score}</p>
            <p class="text-xs text-slate-400 dark:text-slate-500">${view.you.hand.length} cards</p>
          </div>
          ${opponents}
        </div>
      </div>

      <p class="mt-4 text-sm ${view.yourTurn ? 'font-medium text-emerald-700 dark:text-emerald-300' : 'text-slate-400 dark:text-slate-500'}">
        ${view.phase === 'ended' ? 'Match over.' : view.yourTurn ? 'Your turn.' : 'Waiting for your opponent…'}
        <span class="text-slate-400 dark:text-slate-500">· ${view.drawPileCount} cards left in the pile</span>
      </p>
    </section>`;
}

function tray(cards, hint, view, busy, over) {
  const tone = {
    solves: 'text-emerald-700 dark:text-emerald-300',
    misses: 'text-amber-700 dark:text-amber-300',
    invalid: 'text-rose-700 dark:text-rose-300',
    incomplete: 'text-slate-400 dark:text-slate-500',
    empty: 'text-slate-400 dark:text-slate-500',
  }[hint.status];

  const laid = cards.length
    ? cards
        .map(
          (card) => `
          <button type="button" data-card-id="${escape(card.id)}"
                  class="rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-2 text-lg font-semibold text-white hover:bg-slate-700 dark:hover:bg-slate-600">
            ${escape(symbolOf(card))}
          </button>`,
        )
        .join('')
    : '<p class="rounded-lg border border-dashed border-slate-200 dark:border-slate-700 px-3 py-3 text-sm text-slate-300 dark:text-slate-600">Your equation appears here</p>';

  const canPlay = hint.status === 'solves' || hint.status === 'misses';

  return `
    <section class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm ring-1 ring-slate-200 dark:ring-slate-700">
      <div class="flex min-h-[3.5rem] flex-wrap items-center gap-2">${laid}</div>

      <p class="mt-3 text-sm ${tone}">
        ${escape(hint.text)} ${hint.text ? '—' : ''} ${escape(hint.hint)}
        <span class="ml-1 text-xs text-slate-300 dark:text-slate-600">(preview; the server decides)</span>
      </p>

      <div class="mt-4 flex flex-wrap gap-2">
        <button id="play" ${!view.yourTurn || over || busy || !canPlay ? 'disabled' : ''}
                class="rounded-lg bg-sky-600 dark:bg-sky-500 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500 dark:hover:bg-sky-400 disabled:bg-slate-200 dark:disabled:bg-slate-800 disabled:text-slate-400 dark:disabled:text-slate-600">
          ${busy ? 'Sending…' : 'Play'}
        </button>
        <button id="clear" ${cards.length === 0 ? 'disabled' : ''}
                class="rounded-lg bg-white dark:bg-slate-900 px-4 py-2 text-sm ring-1 ring-slate-300 dark:ring-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:text-slate-300 dark:disabled:text-slate-600">
          Clear
        </button>
        <button id="forfeit" ${over ? 'disabled' : ''}
                class="ml-auto rounded-lg px-4 py-2 text-sm text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950 disabled:text-slate-300 dark:disabled:text-slate-600">
          Forfeit
        </button>
      </div>
    </section>`;
}

function hand(view, selection, over) {
  const cards = view.you.hand
    .map((card) => {
      const chosen = selection.has(card.id);
      const operator = card.kind === 'operator';

      return `
        <button type="button" data-card-id="${escape(card.id)}" ${over ? 'disabled' : ''}
                class="h-16 w-14 rounded-xl text-xl font-semibold shadow-sm ring-1 transition
                       ${chosen ? 'bg-slate-900 dark:bg-slate-700 text-white ring-slate-900 dark:ring-slate-600' : operator
                         ? 'bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-300 ring-amber-200 dark:ring-amber-900 hover:bg-amber-100 dark:hover:bg-amber-900'
                         : 'bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 ring-slate-200 dark:ring-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800'}
                       disabled:opacity-40">
          ${escape(symbolOf(card))}
        </button>`;
    })
    .join('');

  return `
    <section class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm ring-1 ring-slate-200 dark:ring-slate-700">
      <p class="text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">Your hand</p>
      <div class="mt-3 flex flex-wrap gap-2">${cards}</div>
    </section>`;
}

function logPanel(log) {
  const lines = log.length
    ? log
        .slice()
        .reverse()
        .map(
          (line) => `
          <li class="border-b border-slate-100 dark:border-slate-800 py-2 last:border-0">
            <span class="font-mono text-[10px] text-slate-300 dark:text-slate-600">${line.seq}</span>
            <span class="${line.tone}">${escape(line.text)}</span>
          </li>`,
        )
        .join('')
    : '<li class="py-2 text-sm text-slate-400 dark:text-slate-500">Nothing has happened yet.</li>';

  return `
    <aside class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm ring-1 ring-slate-200 dark:ring-slate-700">
      <p class="text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">Match log</p>
      <ul class="mt-2 max-h-[28rem] overflow-y-auto text-sm">${lines}</ul>
    </aside>`;
}

function outcome(view) {
  const won = view.winnerId === view.you.id;
  const drawn = view.winnerId === null;

  return `
    <section class="mt-4 rounded-2xl p-5 ring-1 ${won ? 'bg-emerald-50 dark:bg-emerald-950 ring-emerald-200 dark:ring-emerald-900' : 'bg-slate-50 dark:bg-slate-800 ring-slate-200 dark:ring-slate-700'}">
      <p class="font-medium">
        ${drawn ? 'A draw.' : won ? 'You won.' : `${escape(view.winnerId ?? '')} won.`}
      </p>
      <button id="leave" class="mt-2 text-sm text-sky-700 dark:text-sky-400 underline">Start another match</button>
    </section>`;
}

/**
 * Turns an event into a sentence. This is where the engine's reason codes become
 * something a ten-year-old can act on, which is the entire point of having them be
 * a taxonomy rather than a free-text message.
 */
export function describeEvent(event, me) {
  const who = (id) => (id === me ? 'You' : id);
  const payload = event.payload;

  switch (event.type) {
    case 'cards_played':
      return { text: `${who(payload.playerId)} played ${payload.expression ?? 'some cards'}.`, tone: 'text-slate-600 dark:text-slate-400' };

    case 'equation_solved':
      return {
        text: `${payload.expression} = ${payload.target}. ${who(payload.playerId)} scored ${payload.score}.`,
        tone: 'text-emerald-700 dark:text-emerald-300 font-medium',
      };

    case 'equation_rejected':
      return {
        text: `Not quite: ${REASONS[payload.reason] ?? payload.reason}${
          payload.observedResult ? ` (that makes ${payload.observedResult})` : ''
        }`,
        tone: 'text-amber-700 dark:text-amber-300',
      };

    case 'cards_drawn':
      return {
        text: `${who(payload.playerId)} drew ${payload.cards ? payload.cards.length : payload.cardCount} cards.`,
        tone: 'text-slate-400 dark:text-slate-500',
      };

    case 'target_revealed':
      return { text: `New target: ${payload.target}.`, tone: 'text-sky-700 dark:text-sky-400' };

    case 'turn_ended':
      return { text: `${who(payload.playerId)} ended the turn.`, tone: 'text-slate-400 dark:text-slate-500' };

    case 'match_ended':
      return {
        text: payload.winnerId ? `Match over — ${who(payload.winnerId)} won (${payload.reason}).` : 'Match over — a draw.',
        tone: 'font-medium',
      };

    default:
      return { text: event.type, tone: 'text-slate-400 dark:text-slate-500' };
  }
}

function banner(message) {
  return `
    <p class="mt-4 rounded-lg bg-rose-50 dark:bg-rose-950 px-4 py-3 text-sm text-rose-700 dark:text-rose-300 ring-1 ring-rose-200 dark:ring-rose-900">
      ${escape(message)}
    </p>`;
}

function escape(value) {
  return String(value ?? '').replace(
    /[&<>"']/g,
    (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character],
  );
}
