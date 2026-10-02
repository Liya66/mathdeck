/**
 * Two charts, drawn as inline SVG.
 *
 * Form follows the job. "Where they go wrong" is a magnitude comparison across a
 * handful of long-named categories, so it is a horizontal bar in a single hue —
 * the reason codes are not series to tell apart, they are quantities to rank, and
 * a categorical palette here would imply a distinction that is not in the data.
 *
 * "How long an attempt takes" is a range per outcome, so it is a dumbbell: median
 * and 90th percentile as two dots on a connector. An average would hide the thing
 * a teacher needs, which is that fast-and-wrong and slow-and-right are different
 * problems.
 *
 * Colors come from CSS custom properties defined once on .viz-root, validated with
 * the palette checker (ordinal, both modes). Text never wears a data colour.
 */
const BAND = 34;
const BAR_THICKNESS = 22;
const CORNER = 4;
const VALUE_GUTTER = 54;

/** Long reason labels need room, but never more than a third of the chart. */
function labelWidth(width) {
  return Math.round(Math.min(190, Math.max(96, width * 0.34)));
}

export function barChart(rows, { width = 560, valueOf, labelOf, tipOf }) {
  if (rows.length === 0) {
    return emptyState('Nothing to show yet.');
  }

  const height = rows.length * BAND + 8;
  const LABEL_WIDTH = labelWidth(width);
  const plotWidth = Math.max(40, width - LABEL_WIDTH - VALUE_GUTTER);
  const max = Math.max(...rows.map(valueOf), 1);

  const marks = rows
    .map((row, index) => {
      const y = index * BAND + (BAND - BAR_THICKNESS) / 2;
      const length = Math.max(2, (valueOf(row) / max) * plotWidth);

      return `
        <g class="viz-mark" data-tip="${escape(tipOf(row))}">
          <rect x="0" y="${index * BAND}" width="${width}" height="${BAND}" fill="transparent"></rect>
          <text x="${LABEL_WIDTH - 10}" y="${y + BAR_THICKNESS / 2}" text-anchor="end"
                dominant-baseline="central" class="viz-cat-label">${escape(labelOf(row))}</text>
          <path d="${roundedRightBar(LABEL_WIDTH, y, length, BAR_THICKNESS)}" class="viz-bar"></path>
          <text x="${LABEL_WIDTH + length + 8}" y="${y + BAR_THICKNESS / 2}"
                dominant-baseline="central" class="viz-value-label">${escape(valueOf(row))}</text>
        </g>`;
    })
    .join('');

  return `
    <svg viewBox="0 0 ${width} ${height}" width="${width}" height="${height}" role="img">
      <line x1="${LABEL_WIDTH}" y1="0" x2="${LABEL_WIDTH}" y2="${rows.length * BAND}" class="viz-axis"></line>
      ${marks}
    </svg>`;
}

export function dumbbellChart(rows, { width = 560 }) {
  if (rows.length === 0) {
    return emptyState('No attempts in this selection.');
  }

  const height = rows.length * BAND + 30;
  const LABEL_WIDTH = labelWidth(width);
  const plotWidth = Math.max(40, width - LABEL_WIDTH - VALUE_GUTTER);
  const max = Math.max(...rows.map((row) => row.p90LatencyMs), 1);
  const x = (value) => LABEL_WIDTH + (value / max) * plotWidth;

  const ticks = niceTicks(max)
    .map(
      (tick) => `
      <g>
        <line x1="${x(tick)}" y1="0" x2="${x(tick)}" y2="${rows.length * BAND}" class="viz-grid"></line>
        <text x="${x(tick)}" y="${rows.length * BAND + 16}" text-anchor="middle" class="viz-axis-label">
          ${formatMs(tick)}
        </text>
      </g>`,
    )
    .join('');

  const marks = rows
    .map((row, index) => {
      const y = index * BAND + BAND / 2;

      return `
        <g class="viz-mark" data-tip="${escape(
          `${label(row.bucket)} · ${row.count} attempt${row.count === 1 ? '' : 's'} · median ${formatMs(
            row.medianLatencyMs,
          )}, 90th ${formatMs(row.p90LatencyMs)}`,
        )}">
          <rect x="0" y="${index * BAND}" width="${width}" height="${BAND}" fill="transparent"></rect>
          <text x="${LABEL_WIDTH - 10}" y="${y}" text-anchor="end" dominant-baseline="central"
                class="viz-cat-label">${escape(label(row.bucket))}</text>
          <line x1="${x(row.medianLatencyMs)}" y1="${y}" x2="${x(row.p90LatencyMs)}" y2="${y}"
                class="viz-connector"></line>
          <circle cx="${x(row.p90LatencyMs)}" cy="${y}" r="5" class="viz-dot-p90"></circle>
          <circle cx="${x(row.medianLatencyMs)}" cy="${y}" r="5" class="viz-dot-median"></circle>
        </g>`;
    })
    .join('');

  return `
    <svg viewBox="0 0 ${width} ${height}" width="${width}" height="${height}" role="img">
      ${ticks}
      <line x1="${LABEL_WIDTH}" y1="0" x2="${LABEL_WIDTH}" y2="${rows.length * BAND}" class="viz-axis"></line>
      ${marks}
    </svg>`;
}

/** A path with a rounded data-end and a square baseline, as bars should be. */
export function roundedRightBar(x, y, length, thickness) {
  const radius = Math.min(CORNER, length / 2, thickness / 2);
  const end = x + length;

  return [
    `M ${x} ${y}`,
    `H ${end - radius}`,
    `A ${radius} ${radius} 0 0 1 ${end} ${y + radius}`,
    `V ${y + thickness - radius}`,
    `A ${radius} ${radius} 0 0 1 ${end - radius} ${y + thickness}`,
    `H ${x}`,
    'Z',
  ].join(' ');
}

/**
 * Round axis stops: a 1, 2 or 5 times a power of ten, chosen so the number of
 * intervals lands closest to `count`.
 *
 * Picking the first step above max/count instead — the obvious version — leaves a
 * max of 9 with an axis that stops at 5, so the longest bar runs off the end of its
 * own scale.
 */
export function niceTicks(max, count = 4) {
  if (max <= 0) {
    return [0];
  }

  const magnitude = 10 ** Math.floor(Math.log10(max / count));
  const candidates = [1, 2, 5, 10].flatMap((multiple) => [multiple * magnitude, multiple * magnitude * 10]);

  let step = candidates[0];
  let best = Infinity;

  for (const candidate of candidates) {
    const distance = Math.abs(max / candidate - count);

    // `<=` prefers the larger step on a tie: fewer, further-apart labels.
    if (distance <= best) {
      best = distance;
      step = candidate;
    }
  }

  const ticks = [];

  for (let tick = 0; tick <= max + 1e-9; tick += step) {
    ticks.push(Math.round(tick * 1000) / 1000);
  }

  return ticks;
}

export function formatMs(milliseconds) {
  if (milliseconds < 1000) {
    return `${Math.round(milliseconds)}ms`;
  }

  const seconds = milliseconds / 1000;

  return seconds < 10 ? `${seconds.toFixed(1)}s` : `${Math.round(seconds)}s`;
}

/** Reason codes are written for machines; teachers are not machines. */
export function label(code) {
  return (
    {
      solved: 'Solved',
      MALFORMED: 'Not a valid equation',
      TOO_FEW_CARDS: 'Too few cards',
      TOO_MANY_CARDS: 'Too many cards',
      DIVISION_BY_ZERO: 'Divided by zero',
      NON_INTEGER_RESULT: 'Answer was a fraction',
      WRONG_TARGET: 'Missed the target',
      OFF_BY_ONE: 'Off by one',
      OPERATOR_PRECEDENCE_IGNORED: 'Ignored × ÷ before + −',
    }[code] ?? code
  );
}

function emptyState(message) {
  return `<p class="py-10 text-center text-sm" style="color: var(--text-muted)">${escape(message)}</p>`;
}

function escape(value) {
  return String(value ?? '').replace(
    /[&<>"']/g,
    (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character],
  );
}
