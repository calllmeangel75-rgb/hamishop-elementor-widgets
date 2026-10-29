// Support ticket: create from the form, read it back, reply, close.
const { chromium } = require('playwright');

const BASE = 'http://localhost/sks';
const fa2en = (s) => String(s || '')
  .replace(/[\u06F0-\u06F9]/g, (d) => String(d.charCodeAt(0) - 0x06F0))
  .replace(/[\u0660-\u0669]/g, (d) => String(d.charCodeAt(0) - 0x0660));

async function login(page) {
  await page.goto(BASE + '/wp-login.php', { waitUntil: 'domcontentloaded' });
  if (!(await page.$('#user_login'))) { return; }
  await page.fill('#user_login', 'sks');
  await page.fill('#user_pass', 'Test!2345');
  await page.click('#wp-submit');
  await page.waitForURL((u) => !/wp-login\.php/.test(u.pathname), { timeout: 25000 }).catch(() => {});
}

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 1100 } });
  const page = await ctx.newPage();
  const errors = [];
  page.on('console', (m) => { if (m.type() === 'error') { errors.push('console: ' + m.text()); } });
  page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
  page.on('response', (r) => { if (r.status() >= 400) { errors.push('http ' + r.status() + ' ' + r.url()); } });

  await login(page);

  await page.goto(BASE + '/my-account/support/new/', { waitUntil: 'load' });
  await page.waitForTimeout(900);

  const subject = 'تست خودکار ' + Date.now();
  const q = await page.evaluate(() => {
    const el = document.querySelector('[data-hsw-captcha-q]');
    return el ? el.textContent.trim() : '';
  });
  const nums = (fa2en(q).match(/\d+/g) || []).map(Number);
  const answer = nums.length >= 2 ? nums[0] + nums[1] : null;
  console.log('captcha:', JSON.stringify(q), '→', answer);

  const setValue = async (sel, value) => {
    const el = await page.$(sel);
    if (!el) { return false; }
    const tag = await el.evaluate((n) => n.tagName.toLowerCase());
    if (tag === 'select') {
      const opts = await el.evaluate((n) => [...n.options].map((o) => o.value).filter((v) => v !== ''));
      await el.selectOption(opts[0]);
    } else {
      await el.fill(value);
    }
    return true;
  };

  await setValue('[name="subject"]', subject);
  await setValue('[name="department"]', '');
  await setValue('[name="priority"]', '');
  await setValue('[name="message"]', 'این یک تیکت آزمایشی برای بررسی فلو پشتیبانی است.');
  await setValue('[name="captcha"]', answer === null ? '' : String(answer));

  /* The "new ticket" page opens the ticket modal; the page behind it is
     covered by .hsw-ac__modal-backdrop, so the submit must be scoped to the
     modal form or the click never lands. */
  const submit = await page.$('[data-hsw-ticket-modal] form [type="submit"], [data-hsw-ticket-form] [type="submit"]');
  console.log('submit button:', !!submit);
  if (submit) {
    await submit.click();
    await page.waitForTimeout(2500);
  }
  console.log('after submit url:', page.url());

  const state = await page.evaluate(() => ({
    title: document.title.slice(0, 30),
    notices: [...document.querySelectorAll('.woocommerce-error, .woocommerce-message, .hsw-ac__modal-msg, [role="alert"]')]
      .map((n) => n.textContent.replace(/\s+/g, ' ').trim().slice(0, 90)).filter(Boolean),
    ticketLinks: [...document.querySelectorAll('a[href*="/support/"]')].map((a) => a.getAttribute('href')).slice(0, 4),
    hasThread: !!document.querySelector('[data-hsw-ticket-thread], .hsw-ac__chat, .hsw-ac__bubble'),
  }));
  console.log('state:', JSON.stringify(state));

  /* reply on the ticket page (the list keeps the chat form hidden) */
  const newLink = state.ticketLinks.find((h) => /\/support\/\d+\//.test(h || ''));
  if (newLink) {
    await page.goto(newLink, { waitUntil: 'load' });
    await page.waitForTimeout(1200);
    const pageState = await page.evaluate(() => ({
      url: location.href,
      opened: !!document.querySelector('[data-hsw-ticket-thread], .hsw-ac__chat-open, .hsw-ac__ticket-thread'),
      subject: (document.querySelector('h1, .hsw-ac__thread-title') || {}).textContent || '',
    }));
    console.log('ticket page:', JSON.stringify(pageState));
  }

  const visibleBox = async () => {
    for (const sel of ['[data-hsw-ticket-form] textarea', '.hsw-ac__chat textarea', '[data-hsw-ticket-form] input[type="text"]', '.hsw-ac__chat input[type="text"]']) {
      for (const el of await page.$$(sel)) {
        if (await el.isVisible()) { return el; }
      }
    }
    return null;
  };

  const box = await visibleBox();
  if (box) {
    await box.fill('پاسخ آزمایشی مشتری.');
    const sendBtn = await page.$('[data-hsw-ticket-form] [type="submit"], .hsw-ac__chat [type="submit"], .hsw-ac__send');
    if (sendBtn) {
      await sendBtn.click();
      await page.waitForTimeout(1800);
      const after = await page.evaluate(() => ({
        bubbles: document.querySelectorAll('.hsw-ac__bubble, [class*="bubble"]').length,
        text: (document.querySelector('[data-hsw-ticket-thread], .hsw-ac__chat') || {}).innerText || '',
      }));
      console.log('after reply bubbles:', after.bubbles, 'hasCustomerText:', /پاسخ آزمایشی/.test(after.text));
    } else {
      console.log('no send button next to the reply box');
    }
  } else {
    console.log('no visible reply box');
  }

  console.log('errors:', errors.length ? errors : 'none');
  await browser.close();
})();
