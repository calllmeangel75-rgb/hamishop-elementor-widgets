// Support ticket flow end to end: customer creates/replies/closes, staff replies
// from wp-admin, the customer sees the answer and the notification.
const { chromium } = require('playwright');

const BASE = 'http://localhost/sks';
const CUSTOMER = { user: 'sks', pass: 'Test!2345' };
const ADMIN = { user: 'hsw_test_admin', pass: process.env.HSW_ADMIN_PASS || 'Hsw!QA2026' };
let failures = 0;

const check = (label, ok, detail) => {
  if (!ok) { failures++; }
  console.log((ok ? 'ok   ' : 'FAIL ') + label + (detail ? ' — ' + detail : ''));
};

const fa2en = (s) => String(s || '')
  .replace(/[\u06F0-\u06F9]/g, (d) => String(d.charCodeAt(0) - 0x06F0))
  .replace(/[\u0660-\u0669]/g, (d) => String(d.charCodeAt(0) - 0x0660));

async function login(page, who) {
  for (let attempt = 0; attempt < 3; attempt++) {
    await page.goto(BASE + '/wp-login.php', { waitUntil: 'domcontentloaded' });
    if (!(await page.$('#user_login'))) { return true; }
    await page.fill('#user_login', who.user);
    await page.fill('#user_pass', who.pass);
    await page.click('#wp-submit');
    try {
      await page.waitForURL((u) => !/wp-login\.php/.test(u.pathname), { timeout: 25000 });
      return true;
    } catch (e) {
      await page.waitForTimeout(1500);
    }
  }
  return false;
}

const visible = async (page, sel) => {
  for (const el of await page.$$(sel)) {
    if (await el.isVisible()) { return el; }
  }
  return null;
};

(async () => {
  const browser = await chromium.launch();
  const customer = await browser.newContext({ viewport: { width: 1440, height: 1100 } });
  const cPage = await customer.newPage();
  const errors = [];
  cPage.on('console', (m) => { if (m.type() === 'error') { errors.push('customer: ' + m.text()); } });
  cPage.on('pageerror', (e) => errors.push('customer: ' + e.message));
  cPage.on('response', (r) => { if (r.status() >= 400) { errors.push('customer http ' + r.status() + ' ' + r.url()); } });

  /* ---------- 1. customer creates a ticket ---------- */
  const okLogin = await login(cPage, CUSTOMER);
  check('customer logged in', okLogin, cPage.url());

  const subject = 'تست فلو تیکت ' + Date.now();
  const customerText = 'متن اولیهٔ مشتری برای تست فلو کامل تیکت.';

  /* highest ticket id already in the list, so the new one is unambiguous */
  await cPage.goto(BASE + '/my-account/support/', { waitUntil: 'load' });
  await cPage.waitForTimeout(700);
  const maxBefore = await cPage.evaluate(() => {
    const ids = [...document.querySelectorAll('a[href*="/support/"]')]
      .map((a) => Number((((a.getAttribute('href') || '').match(/\/support\/(\d+)\//) || [])[1])) || 0)
      .filter(Boolean);
    return ids.length ? Math.max.apply(null, ids) : 0;
  });

  await cPage.goto(BASE + '/my-account/support/new/', { waitUntil: 'load' });
  await cPage.waitForTimeout(900);

  /* the page keeps the last conversation open; «تیکت جدید» opens the modal */
  if (!(await visible(cPage, '[data-hsw-ticket-form]'))) {
    const newBtn = await visible(cPage, '[data-hsw-ticket-new]');
    if (newBtn) {
      await newBtn.click();
      await cPage.waitForTimeout(800);
    }
  }

  const q = await cPage.evaluate(() => {
    const el = document.querySelector('[data-hsw-captcha-q]');
    return el ? el.textContent.trim() : '';
  });
  const nums = (fa2en(q).match(/\d+/g) || []).map(Number);
  const answer = nums.length >= 2 ? nums[0] + nums[1] : null;

  const setValue = async (sel, value) => {
    const el = await visible(cPage, sel);
    if (!el) { return false; }
    const tag = await el.evaluate((n) => n.tagName.toLowerCase());
    if (tag === 'select') {
      const opts = await el.evaluate((n) => [...n.options].map((o) => o.value).filter(Boolean));
      if (opts.length) { await el.selectOption(opts[0]); }
    } else {
      await el.fill(value);
    }
    return true;
  };

  await setValue('[name="subject"]', subject);
  await setValue('[name="department"]', '');
  await setValue('[name="priority"]', '');
  await setValue('[name="message"]', customerText);
  await setValue('[name="captcha"]', answer === null ? '' : String(answer));

  const submit = await cPage.$('[data-hsw-ticket-form] [type="submit"]');
  if (submit) {
    await submit.click();
  } else {
    await cPage.click('[data-hsw-ticket-modal] [type="submit"]');
  }
  await cPage.waitForTimeout(1200);
  try {
    await cPage.waitForURL(/\/support\/\d+\//, { timeout: 20000 });
  } catch (e) { /* the id is read from the links below */ }
  await cPage.waitForTimeout(900);

  const ticketId = await cPage.evaluate((before) => {
    const fromUrl = (location.pathname.match(/\/support\/(\d+)\//) || [])[1];
    if (fromUrl) { return Number(fromUrl); }
    const links = [...document.querySelectorAll('a[href*="/support/"]')].map((a) => a.getAttribute('href') || '');
    const ids = links.map((h) => (h.match(/\/support\/(\d+)\//) || [])[1]).filter(Boolean).map(Number);
    const fresh = ids.filter((id) => id > before).sort((a, b) => b - a);
    return fresh.length ? fresh[0] : 0;
  }, maxBefore);
  check('ticket created', ticketId > 0, 'id=' + ticketId);

  if (!ticketId) {
    await browser.close();
    console.log('RESULT: cannot continue without a ticket');
    process.exit(2);
  }

  const ticketUrl = BASE + '/my-account/support/' + ticketId + '/';
  await cPage.goto(ticketUrl, { waitUntil: 'load' });
  await cPage.waitForTimeout(900);

  const opened = await cPage.evaluate((text) => {
    const body = document.body.innerText;
    const status = document.querySelector('.hsw-ac__status-badge');
    return {
      hasText: body.includes(text),
      status: status ? status.textContent.replace(/\s+/g, ' ').trim().slice(0, 40) : '',
      bubbles: document.querySelectorAll('.hsw-ac__bubble').length,
    };
  }, customerText);
  check('the customer sees the new ticket and its message', opened.hasText && opened.bubbles >= 1, JSON.stringify(opened));

  /* ---------- 2. customer replies ---------- */
  const replyText = 'پاسخ دوم مشتری در همان تیکت.';
  const composer = await cPage.$('form[data-hsw-reply-form] textarea, form[data-hsw-reply-form] input[type="text"]');
  if (composer) {
    await composer.fill(replyText);
    const send = await cPage.$('form[data-hsw-reply-form] [type="submit"]');
    if (send) { await send.click(); }
    await cPage.waitForTimeout(1800);
    const after = await cPage.evaluate((t) => ({
      hasText: document.body.innerText.includes(t),
      bubbles: document.querySelectorAll('.hsw-ac__bubble').length,
    }), replyText);
    check('customer reply is appended', after.hasText && after.bubbles >= 2, JSON.stringify(after));
  } else {
    check('customer reply composer exists', false, 'no form[data-hsw-reply-form]');
  }

  /* ---------- 3. staff replies from wp-admin ---------- */
  const admin = await browser.newContext({ viewport: { width: 1440, height: 1100 } });
  const aPage = await admin.newPage();
  aPage.on('pageerror', (e) => errors.push('admin: ' + e.message));
  const okAdmin = await login(aPage, ADMIN);
  check('staff logged in', okAdmin, aPage.url());

  const staffText = 'پاسخ تیم پشتیبانی برای تست فلو.';
  const editUrl = BASE + '/wp-admin/post.php?post=' + ticketId + '&action=edit';
  await aPage.goto(editUrl, { waitUntil: 'load' });
  await aPage.waitForTimeout(900);
  const replyBox = await aPage.$('textarea[form="hsw-ticket-reply-form"], #hsw-ticket-reply-form textarea');
  if (replyBox) {
    await replyBox.fill(staffText);
    await Promise.all([
      aPage.waitForNavigation({ waitUntil: 'load', timeout: 25000 }).catch(() => null),
      aPage.click('button[form="hsw-ticket-reply-form"]'),
    ]);
    await aPage.waitForTimeout(800);
    const adminState = await aPage.evaluate((t) => ({
      replied: /hsw_replied=1/.test(location.href),
      hasText: document.body.innerText.includes(t),
    }), staffText);
    check('staff reply saved from wp-admin', adminState.replied && adminState.hasText, JSON.stringify(adminState));
  } else {
    check('staff reply box exists', false, 'no reply textarea on the ticket edit screen');
  }

  /* ---------- 4. customer sees the answer + status + notification ---------- */
  await cPage.goto(ticketUrl, { waitUntil: 'load' });
  await cPage.waitForTimeout(900);
  const seen = await cPage.evaluate((t) => {
    const statusEl = document.querySelector('.hsw-ac__status-badge');
    return {
      hasStaffText: document.body.innerText.includes(t),
      status: statusEl ? statusEl.textContent.replace(/\s+/g, ' ').trim().slice(0, 40) : '',
      bubbles: document.querySelectorAll('.hsw-ac__bubble').length,
    };
  }, staffText);
  check('customer sees the staff answer', seen.hasStaffText && seen.bubbles >= 3, JSON.stringify(seen));
  check('ticket status shows an answered state', /پاسخ|answered/.test(seen.status), seen.status);

  await cPage.goto(BASE + '/my-account/notifications/', { waitUntil: 'load' });
  await cPage.waitForTimeout(700);
  const notified = await cPage.evaluate(() => document.body.innerText.includes('پاسخ جدید در تیکت شما'));
  check('customer notification was created', notified);

  /* ---------- 5. customer closes the ticket ---------- */
  await cPage.goto(ticketUrl, { waitUntil: 'load' });
  await cPage.waitForTimeout(700);
  /* the close button asks «بسته شود؟» — Playwright dismisses dialogs by
     default, which is why the first run looked like a no-op. */
  let confirmSeen = false;
  cPage.on('dialog', async (dialog) => {
    confirmSeen = true;
    await dialog.accept();
  });
  const closeBtn = await visible(cPage, '[data-hsw-ticket-close]');
  if (closeBtn) {
    await closeBtn.click();
    await cPage.waitForTimeout(1600);
    const closed = await cPage.evaluate(() => {
      const statusEl = document.querySelector('.hsw-ac__status-badge');
      return {
        status: statusEl ? statusEl.textContent.replace(/\s+/g, ' ').trim().slice(0, 40) : '',
        composer: !!document.querySelector('form[data-hsw-reply-form]'),
        closedBlock: !!document.querySelector('.hsw-ac__thread-closed'),
        body: document.body.innerText.includes('بسته'),
      };
    });
    check('close asks for confirmation', confirmSeen);
    check('closing replaces the composer with the closed notice', !closed.composer && closed.closedBlock, JSON.stringify(closed));
    check('closed state is visible to the customer', /بسته|closed/.test(closed.status) || closed.body, JSON.stringify(closed));
  } else {
    check('close control exists', false, 'no [data-hsw-ticket-close]');
  }

  /* ---------- 6. staff closes / reopens from wp-admin ---------- */
  await aPage.goto(editUrl, { waitUntil: 'load' });
  await aPage.waitForTimeout(700);
  const adminBadge = () => aPage.evaluate(() => {
    const el = document.querySelector('.hsw-admin-badge');
    return el ? el.textContent.replace(/\s+/g, ' ').trim() : '';
  });
  const statusBtn = await aPage.$('button[form="hsw-ticket-status-form"]');
  if (statusBtn) {
    /* the customer closed it a moment ago, so the staff screen must say so */
    check('admin sees the closed status the customer set', /بسته/.test(await adminBadge()), await adminBadge());

    await Promise.all([
      aPage.waitForNavigation({ waitUntil: 'load', timeout: 25000 }).catch(() => null),
      statusBtn.click(),
    ]);
    await aPage.waitForTimeout(700);
    const afterReopen = await adminBadge();
    check('staff can reopen the ticket', /انتظار/.test(afterReopen), afterReopen);

    const closeAgain = await aPage.$('button[form="hsw-ticket-status-form"]');
    if (closeAgain) {
      await Promise.all([
        aPage.waitForNavigation({ waitUntil: 'load', timeout: 25000 }).catch(() => null),
        closeAgain.click(),
      ]);
      await aPage.waitForTimeout(700);
      const afterClose = await adminBadge();
      check('staff can close the ticket', /بسته/.test(afterClose), afterClose);
    }

    /* the reopened state must reach the customer (composer back) */
    await aPage.goto(editUrl, { waitUntil: 'load' });
    await aPage.waitForTimeout(500);
    const reopenBtn = await aPage.$('button[form="hsw-ticket-status-form"]');
    if (reopenBtn) {
      await Promise.all([
        aPage.waitForNavigation({ waitUntil: 'load', timeout: 25000 }).catch(() => null),
        reopenBtn.click(),
      ]);
      await aPage.waitForTimeout(600);
    }
    await cPage.goto(ticketUrl, { waitUntil: 'load' });
    await cPage.waitForTimeout(800);
    const reopened = await cPage.evaluate(() => ({
      composer: !!document.querySelector('form[data-hsw-reply-form]'),
      status: (document.querySelector('.hsw-ac__status-badge') || {}).textContent || '',
    }));
    check('the customer can reply again after a reopen',
      reopened.composer && /انتظار|پاسخ/.test(reopened.status), JSON.stringify(reopened));
  } else {
    check('admin status control exists', false, 'no status button');
  }

  check('no console/network errors', errors.length === 0, errors.slice(0, 3).join(' | '));

  await browser.close();
  console.log('ticket id: ' + ticketId);
  console.log(failures ? 'RESULT: ' + failures + ' problem(s)' : 'RESULT: clean');
  process.exit(failures ? 1 : 0);
})();
