---
paths:
  - 'tests/Browser/**'
---

# Browser

## Playwright is held at 1.61.1 until Pest 5
pestphp/pest-plugin-browser 4.x sends an action's timeout in `params`; Playwright reads it from `metadata` since 1.62, so on 1.62+ any click/keys that can't find a visible target hangs the run for ever instead of failing after the timeout. package.json pins `playwright` to exactly 1.61.1 — do not bump it (or loosen to ^) until the suite moves to Pest 5 / plugin 5.x, which sends both. tests/Browser/BrowserTimeoutTest.php guards this.
Also: click/keys by bare text (`click('إغلاق')`) take the FIRST match, which is often a hidden duplicate (closed modals). Target the visible element with a CSS selector, e.g. `click('.z-overlay button')`, `keys('.z-overlay', 'Escape')`.
