# RTL direction and the always-LTR header

- Maintained reference, reviewed 2026-11-01.
- Covers locale-driven document direction, the application header, and the scoped-style mirroring defect that made Dari/Pashto text read backwards on 2026-11-01.
- Validation: verified in the running SPA with Playwright in Dari (`fa-AF`) and English, plus source inspection. Direction has no automated test; the checks below are the regression procedure.

## Behavior that must be preserved

- The selected language controls the document direction: `en` → `ltr`, `fa-AF` / `ps-AF` → `rtl`.
- A language switch updates the whole direction contract in one pass: `html[lang]`, `html[dir]`, the `locale-persian-script` font class, `configStore.isAppRTL`, and Vuetify's `VLocaleProvider :rtl`.
- The application header is **always physically LTR**. `.layout-navbar .navbar-content-container` forces `direction: ltr` in every locale, so it never inherits RTL from the document.
- Inside that LTR header, text keeps its own writing direction: under `[dir='rtl']`, `input`, `textarea` and `.v-btn__content` are switched back to `rtl`. Control order stays LTR; Persian labels still read correctly.
- Direction is expressed only with `dir`, `direction`, and logical properties (`inset-inline-*`, `margin-inline-*`). Nothing may mirror the page with a transform.

## The defect: a scoped `:global()` selector mirrors the whole application

Symptom (2026-11-01, Dari/Pashto selected): the entire application appeared mirrored. Latin text read backwards ("metsyS lanoitacudE"), Persian glyphs were flipped, and the vertical navigation stayed on the left.

Cause: four selectors were written as `:global([dir='rtl']) .descendant`:

```css
/* resources/ts/components/TableScrollAffordance.vue */
:global([dir='rtl']) .table-scroll-affordance { flex-direction: row-reverse; }
:global([dir='rtl']) .table-scroll-affordance__control .v-icon { transform: scaleX(-1); }

/* resources/ts/components/responsive-data-table/TablePaginationFooter.vue */
:global([dir='rtl']) .table-pagination-footer__controls { flex-direction: row-reverse; }
:global([dir='rtl']) .table-pagination-footer__controls .v-icon { transform: scaleX(-1); }
```

Vue's scoped-style compiler drops everything after a `:global(...)` block. Each rule therefore compiled to a bare `[dir="rtl"]` selector, and because `<html>` itself matches `[dir="rtl"]`, the browser applied `transform: scaleX(-1)` and `flex-direction: row-reverse` to the document root. `<html>` computed `matrix(-1, 0, 0, 1, 0, 0)`. The rule lives in a lazily loaded component stylesheet, so the mirroring appeared only after a page containing a data table or scroll affordance had been visited, and it persisted for the rest of the session.

Fix: keep the **entire** selector inside `:global(...)`.

```css
:global([dir='rtl'] .table-scroll-affordance) { flex-direction: row-reverse; }
:global([dir='rtl'] .table-scroll-affordance__control .v-icon) { transform: scaleX(-1); }
```

This is a recurring failure mode: it reappeared in newer components, so treat it as a live risk rather than history. The theme-class variant of the same defect (a bare `:global(.v-theme--dark)` painting full-viewport overlay roots) is recorded in [dark-theme-overlay-covers-page.md](../known-issues/dark-theme-overlay-covers-page.md).

## Diagnose a mirrored UI in under five minutes

1. Open the app, switch the language that reproduces it, and confirm the intended document state:

   ```js
   document.documentElement.dir            // "rtl" for Dari/Pashto
   getComputedStyle(document.body).direction
   ```

2. Check whether the document root itself is transformed. A mirrored page always has a non-`none` transform here:

   ```js
   getComputedStyle(document.documentElement).transform  // must be "none"
   ```

3. If it is not `none`, ask the browser which loaded rule applies to `<html>`:

   ```js
   (() => {
     const hits = []
     for (const sheet of document.styleSheets) {
       let rules
       try { rules = sheet.cssRules } catch { continue }
       const walk = list => {
         for (const rule of list) {
           if (rule.cssRules) walk(rule.cssRules)
           if (!rule.selectorText || !rule.style) continue
           if (!rule.style.transform && !rule.style.unicodeBidi) continue
           try {
             if (document.documentElement.matches(rule.selectorText))
               hits.push(rule.cssText.slice(0, 200))
           } catch {}
         }
       }
       walk(rules)
     }
     return hits
   })()
   ```

   A result like `[dir="rtl"] { transform: scaleX(-1); }` identifies the source pattern immediately. The stylesheet is inline in development (Vite HMR) and a hashed asset in production, so search the component sources for the declaration, not the bundle.

4. Fix every `:global(condition) descendant` occurrence by moving the descendant inside the parentheses, then reload and repeat steps 2–3. `<html>` must report `transform: none` and the rule scan must return `[]`.

## Prevention

Run this before shipping any scoped-style change that mentions `:global`. It balances nested parentheses, so correctly wrapped selectors such as `:global(body:is(.a, .b) .no-print)` are not reported:

```bash
node -e '
const fs = require("fs")
const { execSync } = require("child_process")
const files = execSync("grep -rl \\\":global(\\\" resources/ts --include=*.vue")
  .toString().trim().split("\n").filter(Boolean)
const bad = []
for (const file of files) {
  // Blank out comments (same length, so line numbers stay correct); prose such
  // as "keep the selector inside :global()" must not be reported.
  const text = fs.readFileSync(file, "utf8").replace(/\/\*[\s\S]*?\*\//g, c => " ".repeat(c.length))
  for (const m of text.matchAll(/:global\(/g)) {
    let depth = 1, i = m.index + m[0].length
    while (i < text.length && depth) depth += text[i] === "(" ? 1 : text[i] === ")" ? -1 : 0, i++
    const after = text.slice(i).match(/^\s*([,{])/)
    if (!after) bad.push(file + ":" + text.slice(0, m.index).split("\n").length + "  " + text.slice(m.index, i).replace(/\s+/g, " ") + " …")
  }
}
console.log(bad.length ? bad.join("\n") : "OK: every :global() wraps a complete selector")
'
```

A reported line ends with selector text after the closing parenthesis; that suffix is silently discarded by the compiler. Valid forms keep the whole selector inside the parentheses. A plain `grep -E ":global\\([^)]*\\)"` is not a safe guard here because it stops at the first `)` inside `:is(…)` and reports false positives.

Two related rules:

- Scoped style blocks without `lang="scss"` are plain CSS; use `/* … */` comments. A `//` comment inside them fails the Vite/PostCSS parse with "Unclosed string".
- Do not "fix" RTL with `transform`. Conditional horizontal mirroring belongs to icons only (`scaleX(-1)` on `.v-icon`), never to a container that can match `<html>`.

## Source map

| Responsibility | Implementation |
| --- | --- |
| Language switch updates `lang`, `dir`, font class and `isAppRTL` | [initCore.ts](../../resources/ts/@core/initCore.ts) |
| `dir` attribute and layout direction state | [@layouts/stores/config.ts](../../resources/ts/@layouts/stores/config.ts), [@layouts/index.ts](../../resources/ts/@layouts/index.ts) |
| Vuetify RTL provider | [App.vue](../../resources/ts/App.vue) |
| Always-LTR header and its text-direction overrides | [layouts/default.vue](../../resources/ts/layouts/default.vue) |
| Persian font, toast and Driver.js RTL treatment, stable-value classes | [styles.scss](../../resources/styles/styles.scss) |
| RTL icon/control adjustments that must stay scoped | [TableScrollAffordance.vue](../../resources/ts/components/TableScrollAffordance.vue), [TablePaginationFooter.vue](../../resources/ts/components/responsive-data-table/TablePaginationFooter.vue) |

## Known limits

- `public/build` is generated and committed. A source-only change does not reach a production deployment until `npm run build` runs, and an installed PWA may keep serving the previous cached stylesheet until the new release is activated.
- Bidi reordering of mixed values (dates, identifiers, amounts) is inherent to RTL. Use the `.text-email`, `.text-url`, `.text-identifier`, and `.text-currency-value` classes to keep those values LTR-isolated instead of relaxing the document direction.
