import { DeckApi } from '../../app/js/deck-api.js';
import { Session } from '../../app/js/session.js';
import { signInCard } from '../../app/js/signin.js';
import { ApiError } from '../../app/js/api.js';
import { definitionFrom, fieldsFrom } from './form.js';
import { renderShell, renderLint } from './render.js';

const LINT_DEBOUNCE_MS = 400;

const state = {
  api: null,
  session: null,
  signInError: null,
  authorId: '',
  decks: [],
  current: null,
  fields: fieldsFrom(null),
  report: null,
  pending: false,
};

const root = document.getElementById('editor');
let lintTimer = null;

state.session = Session.load();

if (state.session === null) {
  drawSignIn();
} else {
  await start(state.session);
}

async function start(session) {
  state.session = session;
  state.authorId = session.playerId;
  state.api = new DeckApi({ token: session.token });

  await refreshDeckList();
  drawShell();
}

function drawSignIn() {
  root.innerHTML = signInCard({
    title: 'Deck editor',
    subtitle: 'Sign in to author and publish decks.',
    error: state.signInError,
    hint: 'Run `make seed-demo` for local accounts.',
  });
}

async function refreshDeckList() {
  try {
    state.decks = (await state.api.list()).decks;
  } catch (failure) {
    state.decks = [];
    say(messageFor(failure));
  }
}

function canEdit() {
  return state.current !== null
    && state.current.status === 'draft'
    && state.current.authorId === state.authorId;
}

function drawShell() {
  root.innerHTML = renderShell({
    authorId: state.authorId,
    decks: state.decks,
    current: state.current,
    fields: state.fields,
    canEdit: canEdit(),
  });

  drawLint();
}

/**
 * Only this part redraws while someone is typing. Replacing the form under a
 * cursor loses focus, selection and half-typed numbers.
 */
function drawLint() {
  const panel = document.getElementById('lint');

  if (panel !== null) {
    panel.innerHTML = renderLint(state.report, { pending: state.pending });
  }
}

function say(message) {
  const element = document.getElementById('message');

  if (element !== null) {
    element.textContent = message ?? '';
  }
}

function readFields() {
  const form = document.getElementById('deck-form');

  if (form === null) {
    return state.fields;
  }

  const data = new FormData(form);

  return {
    name: String(data.get('name') ?? ''),
    description: String(data.get('description') ?? ''),
    operands: String(data.get('operands') ?? ''),
    operandCopies: data.get('operandCopies'),
    operators: data.getAll('operators').map(String),
    operatorCopies: data.get('operatorCopies'),
    targets: String(data.get('targets') ?? ''),
    handSize: data.get('handSize'),
    minimumCards: data.get('minimumCards'),
    maximumCards: data.get('maximumCards'),
    targetsPerMatch: data.get('targetsPerMatch'),
    requireIntegerResult: data.get('requireIntegerResult') !== null,
    baseScore: data.get('baseScore'),
  };
}

/**
 * The check runs on the server, against the same schema and the same linter that
 * publishing uses. A second implementation in the browser would eventually disagree
 * with the one that decides.
 */
function scheduleLint() {
  state.pending = true;
  drawLint();

  clearTimeout(lintTimer);
  lintTimer = setTimeout(async () => {
    state.fields = readFields();

    try {
      state.report = await state.api.lint(definitionFrom(state.fields));
    } catch (failure) {
      say(messageFor(failure));
    } finally {
      state.pending = false;
      drawLint();
    }
  }, LINT_DEBOUNCE_MS);
}

async function load(deckVersionId) {
  if (deckVersionId === '') {
    state.current = null;
    state.report = null;
    drawShell();

    return;
  }

  try {
    state.current = await state.api.get(deckVersionId);
    state.fields = fieldsFrom(state.current.definition);
    state.report = await state.api.lint(state.current.definition);
    say('');
  } catch (failure) {
    say(messageFor(failure));
  }

  drawShell();
}

root.addEventListener('submit', async (event) => {
  if (event.target.id !== 'signin-form') {
    return;
  }

  event.preventDefault();
  const form = new FormData(event.target);

  try {
    state.signInError = null;
    await start(await Session.signIn(String(form.get('playerId')).trim(), String(form.get('passcode'))));
  } catch (failure) {
    state.signInError = messageFor(failure);
    drawSignIn();
  }
});

root.addEventListener('input', (event) => {
  if (event.target.closest('#deck-form')) {
    scheduleLint();
  }
});

root.addEventListener('change', async (event) => {
  if (event.target.id === 'deck-picker') {
    await load(event.target.value);
  }
});

root.addEventListener('click', async (event) => {
  const action = event.target.closest('button')?.id;

  if (action === undefined) {
    return;
  }

  event.preventDefault();

  try {
    if (action === 'new') {
      const created = await state.api.create(definitionFrom(fieldsFrom(null)));
      await refreshDeckList();
      await load(created.deckVersionId);
      say('New draft created.');
    } else if (action === 'save') {
      state.fields = readFields();
      state.current = await state.api.update(state.current.deckVersionId, definitionFrom(state.fields));
      await refreshDeckList();
      drawShell();
      say('Saved.');
    } else if (action === 'publish') {
      state.fields = readFields();
      await state.api.update(state.current.deckVersionId, definitionFrom(state.fields));
      state.current = await state.api.publish(state.current.deckVersionId);
      await refreshDeckList();
      drawShell();
      say('Published. This version is now fixed.');
    } else if (action === 'sign-out') {
      Session.clear();
      state.session = null;
      drawSignIn();

      return;
    } else if (action === 'fork') {
      const fork = await state.api.fork(state.current.deckVersionId);
      await refreshDeckList();
      await load(fork.deckVersionId);
      say(`Forked to ${fork.deckVersionId}.`);
    }
  } catch (failure) {
    // A refused publish carries the full report; show it rather than a bare error.
    if (failure instanceof ApiError && failure.problem?.report) {
      state.report = failure.problem.report;
      drawLint();
    }

    say(messageFor(failure));
  }
});

function messageFor(failure) {
  if (failure instanceof ApiError) {
    return failure.problem?.detail ?? failure.message;
  }

  return 'Could not reach the server.';
}
