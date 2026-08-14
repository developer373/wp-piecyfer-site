# PieCyfer — read this before touching anything

This is a WordPress site being rebuilt off nulled software. It was compromised: four malicious
plugins, a hidden administrator, and a blockchain-hosted script loader serving arbitrary JS to
every anonymous visitor. The malware is gone. The *reason* it got in — a nulled theme and eight
unlicensed plugins receiving no security updates — is what the current work removes.

**The goal is not "the site works". It is that no nulled code remains, and the site is pixel-identical
throughout.**

## Start here

1. `_project/STATUS.md` — current state, decisions taken, and a "Resume here" section at the end.
   Read the whole thing; it is kept honest, including where earlier claims turned out wrong.
2. `_project/00-AUDIT-REPORT.md` — what was found and why any of this is happening.
3. `_project/01-REBUILD-PLAN.md` — the phase plan.
4. `_project/PHASES.md` — what remains, in order, with entry and exit criteria.

Specs for individual pieces: `02-WIDGET-REBUILD-SPEC.md`, `04-THEME-BUILDER-SPEC.md`,
`05-THEME-SPEC.md`, `06-FORM-SPEC.md`, `07-POSTS-SPEC.md`.

## The method, in one paragraph

Elementor's widget registry is a plain array keyed on widget name, so **the last registration
wins**. `wp-content/plugins/piecyfer-core` registers at priority 150 — after Pro's 10 and after
VamTam's 100 — and takes widgets over **one at a time while Pro is still installed**. Implement
one, capture, compare against the reference; zero diff keeps it, any diff and deleting one file
puts Pro's version back. The site stays working throughout and every widget is proven equivalent
before anything depends on it. Pro comes out only at the end.

## The lesson this project keeps re-learning

**A green result is worthless until you have proved the test could go red.** It has happened five
times here, each time a different mechanism, each time producing a confident pass for something
that never ran:

| # | What faked the pass |
|---|---|
| 1 | Elementor's `e_element_cache` served cached HTML, so edited widgets never executed |
| 2 | `--only /blogs` matched zero URLs (Git Bash rewrote the path); the run "succeeded" empty |
| 3 | `compare.js` reported IDENTICAL for two empty snapshots |
| 4 | Registering at priority 20 let VamTam displace our widget — the page still looked perfect |
| 5 | Elementor keys control stacks by widget name, so probing two implementations in one process made the second inherit the first's stack |

All five are fixed or gated. Assume there is a sixth. Before believing any pass, ask what would
have to be true for it to be meaningless, and check that.

## Gates — run these, do not skip them

```bash
# 1. Takeover gate: is OUR class actually serving each widget and skin? Exits non-zero if not.
C:\xampp\php\php.exe _project/scripts/which-implementation.php

# 2. Pixel + markup. Captures 43 URLs x 3 viewports, diffs two runs.
cd _project/pixel-tool
node capture.js <label> [--quick]        # --quick = 9-page subset
node compare.js ref2-a <label>

# 3. Behaviour — what a screenshot cannot see.
cd _project/behaviour-tool
node run.js <label> --compare pro-active
node run.js <label> --simulate-no-pro --compare pro-active   # falsification
```

`ref2-a` is the current reference: 43 pages, captured with Elementor's caches cleared, proven
stable against a second full run (117 screenshots, 0 differences).

`results/pro-active.json` in the behaviour tool records what the site *does* while Pro still
works — 25 tests, 168 checks. **That artefact is unrecoverable once Pro is removed.** Never
delete it.

## Rules that are not negotiable

- **Never edit site code while a capture is running.** It silently invalidates the run. Take an
  md5 of `Plugin.php` before and check it after.
- **Run captures on a quiet machine.** Under load, Chromium composites full-page screenshots
  before images decode and you get false failures. Agents are fine for *building*; keep them off
  the machine while *verifying*.
- **Never trust a subagent's report** — re-run its own verification script yourself. That costs a
  couple of thousand tokens; a fresh agent costs two hundred thousand.
- **Never send real mail while testing forms.** There are live addresses in them.
- The Theme Builder cutover is **atomic and cannot be done side by side**. Three Pro modules hook
  `elementor/theme/register_locations` with callbacks type-hinted on Pro's own `Locations_Manager`,
  so our manager firing that action while Pro is installed is a TypeError on every page. Pro out
  and our switch on must land in one commit.

## Environment

XAMPP on Windows. Site at `http://localhost/piecyfer/`, docroot `C:\xampp\htdocs\piecyfer`.
PHP `C:\xampp\php\php.exe`, MySQL `C:\xampp\mysql\bin\mysql.exe` (db `piecyfer`, user `root`, no
password). If pages hang but static files serve, check MariaDB — it has hung at the end of startup
after an unclean reboot before, accepting connections but never answering.

## Things that are still wrong and are known

See the task list in `_project/PHASES.md`. The ones that bite hardest:

- `Plugin::maybe_clear_elementor_cache()` runs on front-end `init` and deletes every `_elementor_css`
  meta, so the request it fires on renders with no header or footer stylesheet. On a live site that
  is the first visitor after a deploy.
- 32 real CVs under `wp-content/uploads/elementor/forms` fetch with HTTP 200 and no authentication.
- Nothing enqueues `post-7718.css` that we can find, yet it is in `<head>`. If it turns out to be
  Pro's, the popup loses its styling at cutover.
