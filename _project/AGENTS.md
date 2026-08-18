# AGENTS.md

Copy this to the project root, next to `CLAUDE.md`.

## Read these first, in this order

1. **`CLAUDE.md`** (same folder) — what this project is, the method, and the seven ways it has
   already produced a false pass. Written for Claude Code, but every word applies to any agent.
2. **`_project/PHASES.md`** — what remains, phases A to G, each with an entry and an exit condition.
   The exits are things you can *run*, not things you can assert.
3. **`_project/STATUS.md`** — current state and decisions taken, including where earlier claims
   turned out to be wrong.

`_project/RUNBOOK.md` is a mechanical script written for a weak local model. You do not need it,
but its "Expect" values are accurate and useful.

## What this project is, in three sentences

A WordPress site is being rebuilt off nulled software after a compromise. Elementor Pro's widgets
have been replaced one at a time by our own plugin, each proven pixel- and markup-identical before
anything depended on it. Elementor Pro is still active and still rendering the site; removing it is
Phase B, and it is atomic.

## Rules that are not negotiable

- **A green result means nothing until you have proved the test could go red.** Seven separate
  mechanisms have produced confident passes for things that never ran. Assume there is an eighth.
  The single check that catches most of them: read `Screenshots compared`. If it is `0`, nothing
  was tested.
- **Never edit site code while a capture is running.** Take an md5 of the file before, check it
  after.
- **Run captures on a quiet machine.** Under load, the browser composites full-page screenshots
  before images decode and you get false failures. Parallel agents are fine for *building* and
  wrong for *verifying*.
- **Never trust a subagent's report.** Re-run its own verification script yourself.
- **Never send real mail while testing forms.** There are live addresses in them. Hook `wp_mail`.
- **Never deactivate Elementor Pro** outside Phase B. Three Pro modules hook
  `elementor/theme/register_locations` with callbacks type-hinted on Pro's own class, so our
  locations manager firing that action while Pro is installed is a fatal error on every page.
  There is no side-by-side mode.
- **The site must stay pixel-identical at every step.** That is the whole contract.

## The gates

```bash
# Takeover: is OUR class actually serving each widget and skin? Non-zero exit if not.
php _project/scripts/which-implementation.php

# Pixel + markup
cd _project/pixel-tool
node capture.js <label> [--quick]
node compare.js ref3-a <label>

# Behaviour — what a screenshot cannot see
cd _project/behaviour-tool
node run.js <label> --compare pro-active
node run.js <label> --simulate-no-pro --compare pro-active   # falsification
```

`ref3-a` is the current reference. `ref2-a` is retired — its screenshots are not on this machine,
so comparing against it silently compares zero images.

`_project/behaviour-tool/results/pro-active.json` records what the site *does* while Pro still
works: 25 tests, 168 checks. **It is unrecoverable once Pro is removed. Never delete it.**

## Where to stop and ask

Three decisions belong to the site owner, not to you:

- the retention period for the 32 CVs under `wp-content/uploads/elementor/forms`,
- whether the reCAPTCHA v3 key is genuinely a v3 key (needs the Google console),
- whether the theme swap happens in one step or two.

Everything else: decide, state the evidence, and continue.

## How to report

Say what you ran, what it returned, and what you concluded — in that order. If something could not
be proved, say so plainly and call it unproved rather than done. A flagged gap costs an hour; a
silent one costs the site.
