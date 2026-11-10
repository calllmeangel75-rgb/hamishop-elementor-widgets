// Browser fixture for ticket-only styling and native external-form controls.
// This is not an end-to-end WordPress admin test.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const assert = require('assert');
(async () => {
  const browser = await chromium.launch({ headless: true, ...(process.env.HSW_BROWSER_PATH ? { executablePath: process.env.HSW_BROWSER_PATH } : {}) });
  try {
    const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
    const css = fs.readFileSync(path.join(__dirname, '../assets/css/hsw-admin-tickets.min.css'), 'utf8');
    await page.setContent(`<style>${css}</style><div dir="rtl" class="post-type-hsw_ticket"><div id="poststuff"><div class="postbox"><div class="postbox-header"><h2>گفتگو</h2></div><div class="hsw-ticket-toolbar"><div class="hsw-ticket-toolbar-title"><strong>گفتگو با مشتری</strong></div><button type="submit" form="hsw-ticket-status-form" id="status">بستن</button></div><div class="hsw-ticket-thread" role="region" tabindex="0"><div class="hsw-ticket-msg is-customer">پیام مشتری</div><div class="hsw-ticket-msg is-staff">پاسخ پشتیبانی</div></div><div class="hsw-ticket-reply"><label for="reply">پاسخ</label><textarea id="reply" name="message" form="hsw-ticket-reply-form" required></textarea><button type="submit" form="hsw-ticket-reply-form" id="send">ارسال</button></div></div></div></div><div id="unrelated" class="postbox" style="border:3px solid rgb(1, 2, 3)">Other widget</div><form id="hsw-ticket-status-form"></form><form id="hsw-ticket-reply-form"></form>`);
    assert.equal(await page.locator('#unrelated').evaluate(e => getComputedStyle(e).borderTopColor), 'rgb(1, 2, 3)', 'unrelated postbox styling');
    assert.equal(await page.locator('#poststuff .postbox').evaluate(e => getComputedStyle(e).borderTopLeftRadius), '14px', 'ticket card styling');
    await page.evaluate(() => { window.sent = []; for (const f of document.querySelectorAll('form')) f.addEventListener('submit', e => { e.preventDefault(); window.sent.push({ id: f.id, message: new FormData(f).get('message') }); }); });
    await page.locator('#status').click();
    await page.locator('#reply').fill('پاسخ آزمایشی');
    await page.locator('#send').click();
    assert.deepStrictEqual(await page.evaluate(() => window.sent), [{ id: 'hsw-ticket-status-form', message: null }, { id: 'hsw-ticket-reply-form', message: 'پاسخ آزمایشی' }]);
    await page.setViewportSize({ width: 375, height: 800 });
    assert(await page.locator('.hsw-ticket-thread').evaluate(e => e.scrollWidth <= e.clientWidth + 1), 'conversation must fit mobile viewport');
    console.log('PASS ticket styling isolation and native form associations in browser fixture');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
