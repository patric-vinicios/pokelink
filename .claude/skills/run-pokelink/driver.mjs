// REPL driver for PokéLink (Laravel + Livewire/Volt web app served by the
// docker-compose stack). No chromium-cli in this environment, so this
// wraps Playwright's `chromium` directly — same REPL shape (stdin
// commands -> browser actions), driven from tmux by an agent.
//
// Assumes the app is already running (`gates/init.sh`) and reachable at
// BASE_URL. This driver does not start/stop the stack.
import { chromium } from 'playwright';
import * as readline from 'node:readline';
import * as fs from 'node:fs';
import * as path from 'node:path';

const BASE_URL = process.env.BASE_URL || 'http://localhost:8000';
const SHOT_DIR = process.env.SCREENSHOT_DIR || '/tmp/pokelink-shots';
fs.mkdirSync(SHOT_DIR, { recursive: true });

let browser = null;
let context = null;
let page = null;

const COMMANDS = {
  async launch() {
    if (browser) return console.log('already launched');
    browser = await chromium.launch({ args: ['--no-sandbox'] });
    context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    page = await context.newPage();
    console.log('launched. base url:', BASE_URL);
  },

  async nav(urlPath) {
    if (!page) return console.log('ERROR: launch first');
    const url = /^https?:\/\//.test(urlPath || '') ? urlPath : BASE_URL + (urlPath || '/');
    await page.goto(url, { waitUntil: 'domcontentloaded' });
    console.log('nav ->', page.url());
  },

  // Logs in with the seeded admin account (see README) if the app
  // redirected to /login; no-op if already authenticated.
  async login(rest) {
    if (!page) return console.log('ERROR: launch first');
    const [email, password] = (rest || '').split(' ').filter(Boolean);
    if (!page.url().includes('/login')) {
      await page.goto(BASE_URL + '/', { waitUntil: 'domcontentloaded' });
    }
    if (!page.url().includes('/login')) return console.log('already authenticated');
    await page.locator('input[type="email"]').fill(email || 'admin@pokelink.test');
    await page.locator('input[type="password"]').fill(password || 'password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL('**/', { timeout: 15_000 });
    console.log('logged in ->', page.url());
  },

  async ss(name) {
    if (!page) return console.log('ERROR: launch first');
    const f = path.join(SHOT_DIR, (name || `ss-${Date.now()}`) + '.png');
    await page.screenshot({ path: f });
    console.log('screenshot:', f);
  },

  async click(sel) {
    if (!page) return console.log('ERROR: launch first');
    try { await page.locator(sel).first().click({ timeout: 10_000 }); console.log('click', sel, '-> OK'); }
    catch (e) { console.log('click', sel, '-> ERROR:', e.message.split('\n')[0]); }
  },

  async 'click-text'(text) {
    if (!page) return console.log('ERROR: launch first');
    try { await page.getByText(text, { exact: false }).first().click({ timeout: 10_000 }); console.log('click-text', JSON.stringify(text), '-> OK'); }
    catch (e) { console.log('click-text', JSON.stringify(text), '-> ERROR:', e.message.split('\n')[0]); }
  },

  async 'click-role'(rest) {
    if (!page) return console.log('ERROR: launch first');
    const [role, ...nameParts] = (rest || '').split(' ');
    const name = nameParts.join(' ');
    try { await page.getByRole(role, { name }).first().click({ timeout: 10_000 }); console.log('click-role', role, JSON.stringify(name), '-> OK'); }
    catch (e) { console.log('click-role', role, JSON.stringify(name), '-> ERROR:', e.message.split('\n')[0]); }
  },

  async fill(rest) {
    if (!page) return console.log('ERROR: launch first');
    const sp = rest.indexOf(' ');
    const sel = sp === -1 ? rest : rest.slice(0, sp);
    const value = sp === -1 ? '' : rest.slice(sp + 1);
    try { await page.locator(sel).first().fill(value, { timeout: 10_000 }); console.log('fill', sel, '->', JSON.stringify(value)); }
    catch (e) { console.log('fill', sel, '-> ERROR:', e.message.split('\n')[0]); }
  },

  async type(text) { if (page) await page.keyboard.type(text, { delay: 20 }); },
  async press(key) { if (page) await page.keyboard.press(key); },

  async wait(sel) {
    if (!page) return console.log('ERROR: launch first');
    try { await page.waitForSelector(sel, { timeout: 15_000 }); console.log('found:', sel); }
    catch { console.log('TIMEOUT:', sel); }
  },

  async 'wait-text'(text) {
    if (!page) return console.log('ERROR: launch first');
    try { await page.getByText(text).first().waitFor({ timeout: 15_000 }); console.log('found text:', text); }
    catch { console.log('TIMEOUT:', text); }
  },

  async eval(expr) {
    if (!page) return console.log('ERROR: launch first');
    try { console.log(JSON.stringify(await page.evaluate(expr))); }
    catch (e) { console.log('ERROR:', e.message); }
  },

  async text(sel) {
    if (!page) return console.log('ERROR: launch first');
    console.log(await page.evaluate(
      s => (s ? document.querySelector(s) : document.body)?.innerText ?? '(null)',
      sel || null,
    ));
  },

  async url() { console.log(page ? page.url() : '(no page)'); },

  async console() {
    console.log('tip: run "launch" again to attach a fresh listener, or check the terminal for prior console/pageerror lines printed automatically.');
  },

  async quit() {
    if (browser) await browser.close().catch(() => {});
    browser = null; context = null; page = null;
    console.log('closed');
  },
  help() { console.log('commands:', Object.keys(COMMANDS).join(', ')); },
};

function attachConsoleLogging(p) {
  p.on('console', (msg) => {
    if (msg.type() === 'error') console.log('[console.error]', msg.text());
  });
  p.on('pageerror', (err) => console.log('[pageerror]', err.message));
}

const originalLaunch = COMMANDS.launch;
COMMANDS.launch = async function launch() {
  await originalLaunch();
  if (page) attachConsoleLogging(page);
};

const stdin = fs.createReadStream(null, { fd: fs.openSync('/dev/stdin', 'r') });
const rl = readline.createInterface({ input: stdin, output: process.stdout, prompt: 'driver> ' });

rl.on('line', async (line) => {
  const trimmed = line.trim();
  const sp = trimmed.indexOf(' ');
  const cmd = sp === -1 ? trimmed : trimmed.slice(0, sp);
  const rest = sp === -1 ? '' : trimmed.slice(sp + 1);
  if (!cmd) return rl.prompt();
  const fn = COMMANDS[cmd];
  if (!fn) { console.log('unknown:', cmd, '- try: help'); return rl.prompt(); }
  try { await fn(rest); } catch (e) { console.log('ERROR:', e.message); }
  if (cmd === 'quit') { rl.close(); process.exit(0); }
  rl.prompt();
});
rl.on('close', async () => { await COMMANDS.quit(); process.exit(0); });

console.log('pokelink driver - "help" for commands, "launch" to start, base url:', BASE_URL);
rl.prompt();
