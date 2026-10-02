/**
 * The sign-in card, shared by the editor and the dashboard.
 *
 * The game has its own join screen because signing in and starting a match are one
 * step there; these two are tools you open and use.
 */
export function signInCard({ title, subtitle, error, hint }) {
  return `
    <section class="mx-auto mt-10 max-w-md rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-700">
      <h1 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">${escape(title)}</h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">${escape(subtitle)}</p>

      ${error
        ? `<p class="mt-4 rounded-lg bg-rose-50 dark:bg-rose-950 px-4 py-3 text-sm text-rose-700 dark:text-rose-300 ring-1 ring-rose-200 dark:ring-rose-900">
             ${escape(error)}
           </p>`
        : ''}

      <form id="signin-form" class="mt-5 space-y-3">
        <label class="block">
          <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Who are you?</span>
          <input name="playerId" required autocomplete="username"
                 class="mt-1 w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none
                        focus:border-sky-500 focus:ring-2 focus:ring-sky-200 dark:focus:ring-sky-900">
        </label>

        <label class="block">
          <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Passcode</span>
          <input name="passcode" type="password" required autocomplete="current-password"
                 class="mt-1 w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none
                        focus:border-sky-500 focus:ring-2 focus:ring-sky-200 dark:focus:ring-sky-900">
        </label>

        <button type="submit"
                class="w-full rounded-lg bg-slate-900 dark:bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 dark:hover:bg-slate-600">
          Sign in
        </button>
      </form>

      ${hint ? `<p class="mt-4 text-xs text-slate-400 dark:text-slate-500">${escape(hint)}</p>` : ''}
    </section>`;
}

function escape(value) {
  return String(value ?? '').replace(
    /[&<>"']/g,
    (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character],
  );
}
