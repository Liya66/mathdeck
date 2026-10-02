import { formatMs } from './charts.js';

export function renderDashboard({ teacherId, decks, filter, overview, progression, message }) {
  return `
    ${controls({ teacherId, decks, filter, message })}
    ${headline(overview)}

    <section class="mt-4 grid gap-4 lg:grid-cols-2">
      ${panel(
        'Where they go wrong',
        'Mistakes only, grouped by what went wrong. Most common first.',
        '<div id="chart-errors"></div>',
      )}

      ${panel(
        'How long an attempt takes',
        'Median and 90th percentile, measured from the moment the turn opened.',
        `${latencyLegend()}<div id="chart-latency"></div>`,
      )}
    </section>

    ${progressionTable(progression)}`;
}

function controls({ teacherId, decks, filter, message }) {
  const options = decks
    .map(
      (deck) =>
        `<option value="${escape(deck.deckVersionId)}" ${deck.deckVersionId === filter.deckVersionId ? 'selected' : ''}>
           ${escape(deck.name)} · v${deck.version}
         </option>`,
    )
    .join('');

  return `
    <header class="panel rounded-2xl p-4 shadow-sm ring-1 ring-black/5">
      <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-lg font-semibold tracking-tight">Class dashboard</h1>

        <span class="ml-auto flex items-center gap-2 text-sm" style="color: var(--text-secondary)">
          Signed in as
          <strong class="font-medium" style="color: var(--text-primary)">${escape(teacherId)}</strong>
          <button id="sign-out" class="text-xs underline" style="color: var(--series-strong)">sign out</button>
        </span>
      </div>

      <!-- Filters in one row above the charts. -->
      <div class="mt-3 flex flex-wrap items-end gap-3 text-sm">
        <label class="flex flex-col gap-1">
          <span class="text-xs" style="color: var(--text-muted)">Deck</span>
          <select id="deck" class="rounded-lg border px-2 py-1 text-sm outline-none"
                  style="border-color: var(--axis); background: var(--surface-1); color: var(--text-primary)">
            <option value="">All decks</option>
            ${options}
          </select>
        </label>

        <label class="flex flex-col gap-1">
          <span class="text-xs" style="color: var(--text-muted)">From</span>
          <input id="from" type="date" value="${escape(filter.from ?? '')}"
                 class="rounded-lg border px-2 py-1 text-sm outline-none"
                 style="border-color: var(--axis); background: var(--surface-1); color: var(--text-primary)">
        </label>

        <label class="flex flex-col gap-1">
          <span class="text-xs" style="color: var(--text-muted)">To</span>
          <input id="to" type="date" value="${escape(filter.to ?? '')}"
                 class="rounded-lg border px-2 py-1 text-sm outline-none"
                 style="border-color: var(--axis); background: var(--surface-1); color: var(--text-primary)">
        </label>

        <span class="text-xs" style="color: var(--text-muted)">${escape(message ?? '')}</span>
      </div>
    </header>`;
}

/**
 * One hero figure per view. Accuracy is the number a teacher leads with; the tiles
 * beside it carry effort, reach and pace — attempts rather than successes, because
 * a mark book already counts successes.
 */
function headline(overview) {
  return `
    <section class="panel mt-4 rounded-2xl p-5 shadow-sm ring-1 ring-black/5">
      <div class="flex flex-wrap items-end gap-8">
        <div>
          <p class="text-xs uppercase tracking-wide" style="color: var(--text-muted)">Accuracy</p>
          <p class="text-5xl font-semibold" style="color: var(--text-primary)">
            ${overview.attempts === 0 ? '—' : `${Math.round(overview.accuracy * 100)}%`}
          </p>
          <p class="text-xs" style="color: var(--text-muted)">
            ${overview.solved} solved of ${overview.attempts} tries
          </p>
        </div>
        ${tile('Attempts', overview.attempts)}
        ${tile('Learners', overview.students)}
        ${tile('Matches', overview.matches)}
        ${tile('Median time', overview.medianLatencyMs === null ? '—' : formatMs(overview.medianLatencyMs))}
      </div>
    </section>`;
}

function tile(label, value) {
  return `
    <div>
      <p class="text-xs uppercase tracking-wide" style="color: var(--text-muted)">${escape(label)}</p>
      <p class="text-2xl font-semibold" style="color: var(--text-primary)">${escape(value)}</p>
    </div>`;
}

/** Two marks means a legend, always. */
function latencyLegend() {
  return `
    <p class="mb-2 flex items-center gap-4 text-xs" style="color: var(--text-secondary)">
      <span class="flex items-center gap-1.5">
        <svg width="10" height="10"><circle cx="5" cy="5" r="5" class="viz-dot-median"></circle></svg> median
      </span>
      <span class="flex items-center gap-1.5">
        <svg width="10" height="10"><circle cx="5" cy="5" r="5" class="viz-dot-p90"></circle></svg> slowest 10% start here
      </span>
    </p>`;
}

/**
 * A table, not a chart: several measures per learner, and identity matters more
 * than shape. The accuracy meter is a same-ramp track so the state reads across
 * the whole bar.
 */
function progressionTable(rows) {
  const body = rows.length
    ? rows
        .map(
          (row) => `
        <tr class="border-t" style="border-color: var(--grid)">
          <td class="py-2 pr-3 font-medium">${escape(row.displayName ?? row.studentKey)}</td>
          <td class="py-2 pr-3 text-right tabular-nums">${row.attempts}</td>
          <td class="py-2 pr-3 text-right tabular-nums">${row.solved}</td>
          <td class="py-2 pr-3">
            <div class="flex items-center gap-2">
              <span class="h-2 w-24 overflow-hidden rounded-full" style="background: var(--track)">
                <span class="block h-full rounded-full"
                      style="width: ${Math.round(row.accuracy * 100)}%; background: var(--series-strong)"></span>
              </span>
              <span class="tabular-nums text-xs" style="color: var(--text-secondary)">
                ${Math.round(row.accuracy * 100)}%
              </span>
            </div>
          </td>
          <td class="py-2 text-right tabular-nums">
            ${row.medianLatencyMs === null ? '—' : formatMs(row.medianLatencyMs)}
          </td>
        </tr>`,
        )
        .join('')
    : `<tr><td colspan="5" class="py-8 text-center text-sm" style="color: var(--text-muted)">
         No attempts in this selection yet.
       </td></tr>`;

  return `
    <section class="panel mt-4 rounded-2xl p-5 shadow-sm ring-1 ring-black/5">
      <h2 class="text-sm font-semibold">Each learner</h2>
      <table class="mt-3 w-full text-sm">
        <thead>
          <tr class="text-xs uppercase tracking-wide" style="color: var(--text-muted)">
            <th class="pb-2 text-left font-medium">Learner</th>
            <th class="pb-2 pr-3 text-right font-medium">Tries</th>
            <th class="pb-2 pr-3 text-right font-medium">Solved</th>
            <th class="pb-2 text-left font-medium">Accuracy</th>
            <th class="pb-2 text-right font-medium">Median time</th>
          </tr>
        </thead>
        <tbody>${body}</tbody>
      </table>
    </section>`;
}

function panel(title, blurb, chart) {
  return `
    <section class="panel rounded-2xl p-5 shadow-sm ring-1 ring-black/5">
      <h2 class="text-sm font-semibold">${escape(title)}</h2>
      <p class="mt-0.5 text-xs" style="color: var(--text-muted)">${escape(blurb)}</p>
      <div class="mt-3">${chart}</div>
    </section>`;
}

function escape(value) {
  return String(value ?? '').replace(
    /[&<>"']/g,
    (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character],
  );
}
