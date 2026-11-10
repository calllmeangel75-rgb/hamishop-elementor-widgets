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
    await page.route('https://example.test/image.jpg', route => route.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600"><rect width="800" height="600" fill="red"/></svg>' }));
    const lightbox = fs.readFileSync(path.join(__dirname, '../assets/js/hsw-admin-ticket-lightbox.min.js'), 'utf8');
    await page.locator('.hsw-ticket-thread').evaluate(el => el.insertAdjacentHTML('beforeend', '<a class="hsw-ticket-attach" href="https://example.test/image.jpg" target="_blank"><img alt="" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=="></a>'));
    await page.evaluate(() => document.body.classList.add('post-type-hsw_ticket'));
    await page.addScriptTag({ content: lightbox });
    const url = page.url();
    await page.locator('.hsw-ticket-attach').click();
    assert(await page.locator('dialog.hsw-ticket-lightbox').evaluate(el => el.open), 'image opens inside modal');
    assert.equal(page.url(), url, 'lightbox does not navigate');
    assert.equal(await page.locator('dialog img').getAttribute('src'), 'https://example.test/image.jpg');
    await page.locator('dialog img').evaluate(img => img.decode());
    const image = page.locator('dialog img');
    const initialWidth = await image.evaluate(el => el.getBoundingClientRect().width);
    const zoom = page.locator('.hsw-ticket-lightbox__zoom');
    await zoom.click();
    assert.equal(await zoom.getAttribute('aria-pressed'), 'true');
    assert(Math.abs(await image.evaluate(el => el.getBoundingClientRect().width) - initialWidth * 2) < 1, 'zoom doubles displayed width');
    assert(await page.locator('.hsw-ticket-lightbox__viewport').evaluate(el => el.scrollWidth > el.clientWidth), 'zoomed image can be scrolled');
    await zoom.click();
    assert.equal(await zoom.getAttribute('aria-pressed'), 'false');
    assert(Math.abs(await image.evaluate(el => el.getBoundingClientRect().width) - initialWidth) < 1, 'zoom resets');
    await zoom.click();
    await page.keyboard.press('Escape');
    assert(!(await page.locator('dialog.hsw-ticket-lightbox').evaluate(el => el.open)), 'Escape closes modal');
    assert(await page.locator('.hsw-ticket-attach').evaluate(el => document.activeElement === el), 'focus returns to image link');
    await page.locator('.hsw-ticket-attach').click();
    assert.equal(await zoom.getAttribute('aria-pressed'), 'false', 'zoom resets between openings');
    await page.locator('.hsw-ticket-lightbox__close').click();
    console.log('PASS ticket styling isolation, form associations and lightbox in browser fixture');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
