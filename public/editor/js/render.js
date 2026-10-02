/**
 * The editor's markup.
 *
 * Unlike the game, this page does **not** re-render on every keystroke: replacing
 * the form while someone is typing in it destroys focus and selection. The shell is
 * drawn when the deck changes; the lint panel updates on its own.
 */
const OPERATORS = [
  { symbol: '+', label: '+' },
  { symbol: '-', label: '−' },
  { symbol: '*', label: '×' },
  { symbol: '/', label: '÷' },
];

export function renderShell({ authorId, decks, current, fields, canEdit }) {
  return `
    ${toolbar({ authorId, decks, current, canEdit })}
    <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_24rem]">
      ${form(fields, canEdit)}
      <aside id="lint" class="space-y-4"></aside>
    </div>`;
}

function toolbar({ authorId, decks, current, canEdit }) {
  const options = decks
    .map(
      (deck) => `
        <option value="${escape(deck.deckVersionId)}" ${deck.deckVersionId === current?.deckVersionId ? 'selected' : ''}>
          ${escape(deck.name)} · v${deck.version} · ${escape(deck.status)}
        </option>`,
    )
    .join('');

  return `
    <header class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-sm ring-1 ring-slate-200 dark:ring-slate-700">
      <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-lg font-semibold tracking-tight">Deck editor</h1>

        <span class="ml-auto flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
          Signed in as <strong class="font-medium text-slate-900 dark:text-slate-100">${escape(authorId)}</strong>
          <button id="sign-out" class="text-xs text-sky-700 dark:text-sky-400 underline">sign out</button>
        </span>

        <select id="deck-picker"
                class="rounded-lg border border-slate-300 dark:border-slate-600 px-2 py-1 text-sm outline-none focus:border-sky-500">
          <option value="">— choose a deck —</option>
          ${options}
        </select>
      </div>

      <div class="mt-3 flex flex-wrap items-center gap-2">
        <button id="new" class="rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700 dark:hover:bg-slate-600">
          New deck
        </button>
        <button id="fork" ${current ? '' : 'disabled'}
                class="rounded-lg bg-white dark:bg-slate-900 px-3 py-1.5 text-sm ring-1 ring-slate-300 dark:ring-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:text-slate-300 dark:disabled:text-slate-600">
          Fork
        </button>
        <button id="save" ${canEdit ? '' : 'disabled'}
                class="rounded-lg bg-white dark:bg-slate-900 px-3 py-1.5 text-sm ring-1 ring-slate-300 dark:ring-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:text-slate-300 dark:disabled:text-slate-600">
          Save draft
        </button>
        <button id="publish" ${canEdit ? '' : 'disabled'}
                class="rounded-lg bg-sky-600 dark:bg-sky-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-sky-500 dark:hover:bg-sky-400 disabled:bg-slate-200 dark:disabled:bg-slate-800 disabled:text-slate-400 dark:disabled:text-slate-600">
          Publish
        </button>

        ${current ? statusBadge(current) : ''}
        <span id="message" class="text-sm text-slate-500 dark:text-slate-400"></span>
      </div>

      ${current && !canEdit
        ? `<p class="mt-3 rounded-lg bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs text-slate-500 dark:text-slate-400 ring-1 ring-slate-200 dark:ring-slate-700">
             This version is published, so it cannot be changed — every match played with it
             recorded that exact deck. Fork it to make a new version.
           </p>`
        : ''}
    </header>`;
}

function statusBadge(deck) {
  const published = deck.status === 'published';

  return `
    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium ring-1
                 ${published ? 'bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 ring-emerald-200 dark:ring-emerald-900' : 'bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-300 ring-amber-200 dark:ring-amber-900'}">
      ${escape(deck.deckVersionId)} · ${escape(deck.status)}
    </span>`;
}

function form(fields, canEdit) {
  const disabled = canEdit ? '' : 'disabled';
  const input = (name, value, type = 'text', extra = '') => `
    <input name="${name}" type="${type}" value="${escape(value)}" ${disabled} ${extra} autocomplete="off"
           class="mt-1 w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm outline-none
                  focus:border-sky-500 focus:ring-2 focus:ring-sky-200 dark:focus:ring-sky-900 disabled:bg-slate-50 dark:disabled:bg-slate-800 disabled:text-slate-400 dark:disabled:text-slate-600">`;

  const number = (name, value, min, max) => input(name, value, 'number', `min="${min}" max="${max}"`);

  return `
    <form id="deck-form" class="space-y-4">
      <section class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm ring-1 ring-slate-200 dark:ring-slate-700">
        <label class="block"><span class="text-sm font-medium">Deck name</span>${input('name', fields.name)}</label>
        <label class="mt-3 block">
          <span class="text-sm font-medium">Description</span>${input('description', fields.description)}
        </label>
      </section>

      <section class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm ring-1 ring-slate-200 dark:ring-slate-700">
        <h2 class="text-sm font-semibold">Cards</h2>

        <label class="mt-3 block">
          <span class="text-sm font-medium">Numbers</span>
          <span class="ml-1 text-xs text-slate-400 dark:text-slate-500">commas or ranges, e.g. 0-12, 20</span>
          ${input('operands', fields.operands)}
        </label>

        <label class="mt-3 block max-w-[10rem]">
          <span class="text-sm font-medium">Copies of each</span>${number('operandCopies', fields.operandCopies, 1, 20)}
        </label>

        <p class="mt-4 text-sm font-medium">Operations</p>
        <div class="mt-2 flex flex-wrap gap-2">
          ${OPERATORS.map(
            (operator) => `
            <label class="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-1.5 text-sm ring-1
                          ${fields.operators.includes(operator.symbol) ? 'bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-300 ring-amber-200 dark:ring-amber-900' : 'bg-white dark:bg-slate-900 ring-slate-200 dark:ring-slate-700'}">
              <input type="checkbox" name="operators" value="${operator.symbol}" ${disabled}
                     ${fields.operators.includes(operator.symbol) ? 'checked' : ''}
                     class="h-4 w-4 accent-amber-600">
              <span class="text-base font-semibold">${operator.label}</span>
            </label>`,
          ).join('')}
        </div>

        <label class="mt-4 block max-w-[10rem]">
          <span class="text-sm font-medium">Copies of each</span>${number('operatorCopies', fields.operatorCopies, 1, 40)}
        </label>
      </section>

      <section class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm ring-1 ring-slate-200 dark:ring-slate-700">
        <h2 class="text-sm font-semibold">Targets</h2>
        <label class="mt-3 block">
          <span class="text-xs text-slate-400 dark:text-slate-500">The numbers learners are asked to make.</span>
          ${input('targets', fields.targets)}
        </label>
      </section>

      <section class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm ring-1 ring-slate-200 dark:ring-slate-700">
        <h2 class="text-sm font-semibold">Play</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-3">
          <label class="block"><span class="text-sm">Hand size</span>${number('handSize', fields.handSize, 3, 12)}</label>
          <label class="block">
            <span class="text-sm">Fewest cards</span>
            <select name="minimumCards" ${disabled}
                    class="mt-1 w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm outline-none focus:border-sky-500 disabled:bg-slate-50 dark:disabled:bg-slate-800">
              ${[3, 5, 7, 9].map((n) => `<option ${Number(fields.minimumCards) === n ? 'selected' : ''}>${n}</option>`).join('')}
            </select>
          </label>
          <label class="block">
            <span class="text-sm">Most cards</span>
            <select name="maximumCards" ${disabled}
                    class="mt-1 w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm outline-none focus:border-sky-500 disabled:bg-slate-50 dark:disabled:bg-slate-800">
              ${[3, 5, 7, 9].map((n) => `<option ${Number(fields.maximumCards) === n ? 'selected' : ''}>${n}</option>`).join('')}
            </select>
          </label>
          <label class="block">
            <span class="text-sm">Targets per match</span>${number('targetsPerMatch', fields.targetsPerMatch, 1, 50)}
          </label>
          <label class="block"><span class="text-sm">Points per operation</span>${number('baseScore', fields.baseScore, 1, 1000)}</label>
          <label class="flex items-end gap-2 pb-2 text-sm">
            <input type="checkbox" name="requireIntegerResult" ${disabled}
                   ${fields.requireIntegerResult ? 'checked' : ''} class="h-4 w-4 accent-sky-600">
            <span>Whole-number answers only</span>
          </label>
        </div>
      </section>
    </form>`;
}

export function renderLint(report, { pending }) {
  if (report === null) {
    return `<section class="rounded-2xl bg-white dark:bg-slate-900 p-5 text-sm text-slate-400 dark:text-slate-500 shadow-sm ring-1 ring-slate-200 dark:ring-slate-700">
              Choose or create a deck to check it.
            </section>`;
  }

  const problems = [
    ...report.errors.map((problem) => ({ ...problem, tone: 'rose' })),
    ...report.warnings.map((problem) => ({ ...problem, tone: 'amber' })),
  ];

  return `
    <section class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm ring-1 ring-slate-200 dark:ring-slate-700">
      <div class="flex items-center justify-between">
        <h2 class="text-sm font-semibold">Check</h2>
        <span class="text-xs ${pending ? 'text-slate-400 dark:text-slate-500' : 'text-transparent'}">checking…</span>
      </div>

      <p class="mt-2 rounded-lg px-3 py-2 text-sm ring-1
                ${report.publishable ? 'bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 ring-emerald-200 dark:ring-emerald-900' : 'bg-rose-50 dark:bg-rose-950 text-rose-700 dark:text-rose-300 ring-rose-200 dark:ring-rose-900'}">
        ${report.publishable ? 'Ready to publish.' : 'Not publishable yet.'}
      </p>

      ${problems.length
        ? `<ul class="mt-3 space-y-2 text-sm">
             ${problems
               .map(
                 (problem) => `
                 <li class="rounded-lg px-3 py-2 ring-1
                            ${problem.tone === 'rose' ? 'bg-rose-50 dark:bg-rose-950 text-rose-700 dark:text-rose-300 ring-rose-200 dark:ring-rose-900' : 'bg-amber-50 dark:bg-amber-950 text-amber-800 dark:text-amber-200 ring-amber-200 dark:ring-amber-900'}">
                   ${escape(problem.detail)}
                   <span class="mt-0.5 block font-mono text-[10px] opacity-60">${escape(problem.path)}</span>
                 </li>`,
               )
               .join('')}
           </ul>`
        : '<p class="mt-3 text-sm text-slate-400 dark:text-slate-500">No problems found.</p>'}
    </section>

    ${targetGrid(report.targets)}`;
}

/**
 * The part a teacher actually reads: every target, and whether their cards can
 * make it. Hovering a chip shows a worked example the server verified.
 */
function targetGrid(targets) {
  if (targets.length === 0) {
    return '';
  }

  const chips = targets
    .map((target) => {
      const tone =
        target.reachable === false
          ? 'bg-rose-100 dark:bg-rose-900 text-rose-800 dark:text-rose-200 ring-rose-300 dark:ring-rose-800'
          : target.reachable === null
            ? 'bg-slate-100 dark:bg-slate-950 text-slate-500 dark:text-slate-400 ring-slate-300 dark:ring-slate-600'
            : target.simpleSolutions === 0
              ? 'bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200 ring-amber-300 dark:ring-amber-800'
              : 'bg-emerald-50 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-200 ring-emerald-200 dark:ring-emerald-900';

      const title =
        target.reachable === false
          ? 'No arrangement of these cards makes this number.'
          : target.reachable === null
            ? 'Could not check this one within the search budget.'
            : `${target.example} (${target.shortestCards} cards, ${target.simpleSolutions} simple ways)`;

      return `<span title="${escape(title)}"
                    class="rounded-md px-2 py-1 text-xs font-medium tabular-nums ring-1 ${tone}">${target.target}</span>`;
    })
    .join('');

  return `
    <section class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm ring-1 ring-slate-200 dark:ring-slate-700">
      <h2 class="text-sm font-semibold">Targets</h2>
      <div class="mt-3 flex flex-wrap gap-1.5">${chips}</div>
      <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">
        <span class="text-emerald-700 dark:text-emerald-300">green</span> easy ·
        <span class="text-amber-700 dark:text-amber-300">amber</span> needs more cards ·
        <span class="text-rose-700 dark:text-rose-300">red</span> impossible ·
        grey unchecked. Hover for a worked example.
      </p>
    </section>`;
}

function escape(value) {
  return String(value ?? '').replace(
    /[&<>"']/g,
    (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character],
  );
}
