#!/usr/bin/env node
/*
 * prompt-injector.js — SessionStart hook. Injects the tournament brief
 * (prompt.txt) into the new session's context.
 *
 * If a pending operator revision exists (~/prompt-revision.txt, non-empty), it
 * PREPENDS a one-shot instruction telling the agent to apply that change to
 * prompt.txt, save it, and delete the revision file — so the brief is adjusted
 * on the next start, exactly once. With no revision file, it behaves as a plain
 * brief injector.
 */
'use strict';
const fs = require('fs');
const PROMPT   = process.env.STEP_PROMPT     || '/home/notificationsforsteven/prompt.txt';
const REVISION = process.env.PROMPT_REVISION || '/home/notificationsforsteven/prompt-revision.txt';

// Drain the SessionStart payload on stdin (contents not needed).
try { fs.readFileSync(0, 'utf8'); } catch { /* no stdin */ }

let brief = '';
try { brief = fs.readFileSync(PROMPT, 'utf8'); } catch { /* missing brief */ }

let rev = '';
try { rev = fs.readFileSync(REVISION, 'utf8').trim(); } catch { /* none pending */ }

let ctx = brief;
if (rev) {
  ctx =
`# ⚠️ OPERATOR REVISION REQUEST — DO THIS FIRST
Before any tournament work, revise the brief at ${PROMPT} to incorporate the change below.
Edit the file and save it, then DELETE ${REVISION} so this request runs only once. Then proceed.

Requested change:
${rev}

------------------------------------------------------------
# CURRENT BRIEF (${PROMPT})

${brief}`;
}

process.stdout.write(JSON.stringify({
  hookSpecificOutput: { hookEventName: 'SessionStart', additionalContext: ctx },
}));
