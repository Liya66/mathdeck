import { ReportApi } from '../../app/js/report-api.js';
import { Session } from '../../app/js/session.js';
import { signInCard } from '../../app/js/signin.js';
import { ApiError } from '../../app/js/api.js';
import { renderDashboard } from './render.js';
import { barChart, dumbbellChart, label } from './charts.js';

const state = {
  api: null,
  session: null,
  signInError: null,
  teacherId: '',
  decks: [],
  filter: { deckVersionId: '', from: '', to: '' },
  overview: { attempts: 0, solved: 0, accuracy: 0, students: 0, matches: 0, totalScore: 0, medianLatencyMs: null },
  errors: [],
  latency: [],
  progression: [],
  message: '',
};

const root = document.getElementById('dashboard');
const tooltip = document.getElementById('tooltip');

state.session = Session.load();

if (state.session === null) {
  drawSignIn();
} else {
  await start(state.session);
}

function drawSignIn() {
  root.innerHTML = signInCard({
    title: 'Class dashboard',
    subtitle: 'Sign in to see how a class is getting on.',
    error: state.signInError,
    hint: 'Teacher accounts only. Run `make seed-demo` for local accounts.',
  });
}

async function start(session) {
  state.session = session;
  state.teacherId = session.playerId;
  state.api = new ReportApi({ token: session.token });

  try {
    state.decks = (await state.api.decks()).decks.filter((deck) => deck.status === 'published');
  } catch {
    state.decks = [];
  }

  await load();
}

async function load() {
  const filter = {
    deckVersionId: state.filter.deckVersionId || undefined,
    from: state.filter.from ? `${state.filter.from} 00:00:00` : undefined,
    to: state.filter.to ? `${state.filter.to} 23:59:59` : undefined,
  };

  try {
    const [overview, errors, latency, progression] = await Promise.all([
      state.api.overview(filter),
      state.api.errors(filter),
      state.api.latency(filter),
      state.api.progression(filter),
    ]);

    state.overview = overview.data;
    state.errors = errors.data;
    state.latency = latency.data;
    state.progression = progression.data;
    state.message = '';
  } catch (failure) {
    state.message = failure instanceof ApiError ? failure.problem?.detail ?? failure.message : 'Could not load reports.';
    state.overview = { attempts: 0, solved: 0, accuracy: 0, students: 0, matches: 0, totalScore: 0, medianLatencyMs: null };
    state.errors = [];
    state.latency = [];
    state.progression = [];
  }

  draw();
}

function draw() {
  root.innerHTML = renderDashboard(state);
  drawCharts();
}

/**
 * Charts are drawn at the container's real pixel width rather than scaled into it.
 * A viewBox squeezed to fit shrinks the type with it — 12px labels rendered at 9px
 * is how a readable chart becomes an unreadable one on a narrow screen.
 */
function drawCharts() {
  const errors = document.getElementById('chart-errors');
  const latency = document.getElementById('chart-latency');

  if (errors === null || latency === null) {
    return;
  }

  errors.innerHTML = barChart(state.errors, {
    width: errors.clientWidth,
    valueOf: (row) => row.count,
    labelOf: (row) => label(row.reason),
    tipOf: (row) =>
      `${label(row.reason)} · ${row.count} of the mistakes (${Math.round(row.share * 100)}%) · ` +
      `${row.students} learner${row.students === 1 ? '' : 's'}`,
  });

  latency.innerHTML = dumbbellChart(state.latency, { width: latency.clientWidth });
}

let resizeTimer = null;

window.addEventListener('resize', () => {
  clearTimeout(resizeTimer);
  resizeTimer = setTimeout(drawCharts, 150);
});

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
    state.signInError = failure?.problem?.detail ?? 'Could not sign in.';
    drawSignIn();
  }
});

root.addEventListener('click', (event) => {
  if (event.target.id === 'sign-out') {
    Session.clear();
    state.session = null;
    drawSignIn();
  }
});

root.addEventListener('change', async (event) => {
  if (['deck', 'from', 'to'].includes(event.target.id)) {
    state.filter = {
      deckVersionId: document.getElementById('deck').value,
      from: document.getElementById('from').value,
      to: document.getElementById('to').value,
    };

    await load();
  }
});

// Hover layer. The hit target is the whole band, not the mark — a 10px dot is far
// too small to ask anyone to aim at.
root.addEventListener('mouseover', (event) => {
  const mark = event.target.closest('.viz-mark');

  if (mark === null) {
    return;
  }

  tooltip.textContent = mark.dataset.tip;
  tooltip.classList.add('visible');
});

root.addEventListener('mousemove', (event) => {
  if (!tooltip.classList.contains('visible')) {
    return;
  }

  tooltip.style.left = `${Math.min(event.clientX + 14, window.innerWidth - tooltip.offsetWidth - 8)}px`;
  tooltip.style.top = `${event.clientY + 16}px`;
});

root.addEventListener('mouseout', (event) => {
  if (event.target.closest('.viz-mark') !== null) {
    tooltip.classList.remove('visible');
  }
});
