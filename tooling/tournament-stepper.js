#!/usr/bin/env node
/*
 * tournament-stepper.js — a Stop hook that forces the agent through exactly
 * STEP_MAX (default 100) steps, flashing a beacon each step.
 *
 * On each Stop: if ARMED, increment a persistent counter, append a timestamped
 * beacon to the log, and return decision:"block" so the agent CANNOT stop — the
 * `reason` (the tournament brief + step number) is fed back as its next step.
 * At STEP_MAX it releases the agent and auto-disarms.
 *
 * SAFETY: disarmed unless the ARM marker file exists, so it never traps an
 * ordinary session. Arm:  touch ~/.tournament-active
 *         Disarm: rm ~/.tournament-active   (also automatic at STEP_MAX)
 *
 * Proof of stepping: STATE holds the live count; LOG is a timestamped audit trail.
 */
'use strict';
const fs = require('fs');

const MAX    = parseInt(process.env.STEP_MAX || '100', 10);
const STATE  = process.env.STEP_STATE  || '/home/notificationsforsteven/.tournament-steps';
const ARM    = process.env.STEP_ARM    || '/home/notificationsforsteven/.tournament-active';
const LOG    = process.env.STEP_LOG    || '/home/notificationsforsteven/.tournament-beacon.log';
const PROMPT = process.env.STEP_PROMPT || '/home/notificationsforsteven/prompt.txt';

// Drain the Stop-hook payload on stdin (contents not needed).
try { fs.readFileSync(0, 'utf8'); } catch { /* no stdin */ }

// Disarmed → behave as if no hook exists: let the agent stop normally.
if (!fs.existsSync(ARM)) process.exit(0);

let n = 0;
try { n = parseInt(fs.readFileSync(STATE, 'utf8').trim(), 10) || 0; } catch { /* first run */ }
const ts = new Date().toISOString();

if (n >= MAX) {                                    // run complete → release + auto-disarm
  try { fs.writeFileSync(STATE, '0'); } catch {}
  try { fs.unlinkSync(ARM); } catch {}
  const msg = `✅ tournament stepper: ${MAX}/${MAX} steps complete — released.`;
  try { fs.appendFileSync(LOG, `${ts} [done] ${msg}\n`); } catch {}
  process.stdout.write(JSON.stringify({ systemMessage: msg }));
  process.exit(0);
}

n += 1;
try { fs.writeFileSync(STATE, String(n)); } catch {}
const beacon = `⚡ BEACON  step ${n}/${MAX}`;
try { fs.appendFileSync(LOG, `${ts} ${beacon}\n`); } catch {}

let prompt = '';
try { prompt = fs.readFileSync(PROMPT, 'utf8'); } catch { /* prompt optional */ }

// decision:"block" prevents the stop; `reason` is fed back to the model as its next step.
process.stdout.write(JSON.stringify({
  decision: 'block',
  reason: `${prompt}\n\n[STEP ${n}/${MAX}] Take exactly ONE step of tournament work now, then stop. The stepper advances the counter and re-issues this brief until step ${MAX}.`,
  systemMessage: beacon,
}));
