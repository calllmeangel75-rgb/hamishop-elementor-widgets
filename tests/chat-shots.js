// Drives the new support chat: list, modal, order picker, submit, reply.
const { chromium } = require('playwright');

const BASE = 'http://localhost/sks';
const OUT = 'D:/work-webs/build/hamishop-elementor-widgets/tests/_shots';

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1360, height: 950 } });
  const page = await ctx.newPage();
  page.on('pageerror', (e) => console.log('PAGEERROR:', e.message));
  page.on('console', (m) => { if (m.type() === 'error') console.log('CONSOLE-ERR:', m.text()); });

  await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', 'hsw_chat_test');
  await page.fill('#user_pass', 'Test!2345');
  await page.click('#wp-submit');
  await page.waitForTimeout(2500);

  await page.goto(`${BASE}/my-account/support/`, { waitUntil: 'load', timeout: 60000 });
  await page.waitForTimeout(1500);
  await page.screenshot({ path: `${OUT}/chat-empty.png` });

  // Open the modal.
  await page.click('[data-hsw-ticket-new]');
  await page.waitForTimeout(500);
  await page.screenshot({ path: `${OUT}/chat-modal.png` });

  // Order picker.
  await page.click('[data-hsw-order-input]');
  await page.waitForTimeout(3000);
  const options = await page.$$eval('[data-hsw-order-list] .hsw-ac__picker-item', (els) => els.map((e) => e.textContent.trim()));
  console.log('order options:', JSON.stringify(options));
  await page.screenshot({ path: `${OUT}/chat-picker.png` });

  if (options.length) {
    await page.click('[data-hsw-order-list] .hsw-ac__picker-item');
    await page.waitForTimeout(300);
  }

  await page.fill('#hsw-ac-t-subject', 'مشکل در ارسال سفارش');
  await page.fill('#hsw-ac-t-message', 'سلام، سفارش من هنوز ارسال نشده. لطفاً پیگیری کنید. ممنون.');

  // Read the captcha question and answer it.
  const q = await page.textContent('[data-hsw-captcha-q]');
  const nums = q.replace(/[^\d۰-۹+]/g, '').split('+');
  const fa2en = (s) => s.replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
  const answer = Number(fa2en(nums[0] || '0')) + Number(fa2en(nums[1] || '0'));
  console.log('captcha:', q.trim(), '->', answer);
  await page.fill('#hsw-ac-t-captcha', String(answer));

  await page.click('[data-hsw-ticket-submit]');
  await page.waitForTimeout(2500);

  const afterCreate = await page.evaluate(() => ({
    modalHidden: document.querySelector('[data-hsw-ticket-modal]').hidden,
    hasThread: !!document.querySelector('[data-hsw-thread]'),
    rows: document.querySelectorAll('.hsw-ac__chat-item').length,
    title: document.querySelector('.hsw-ac__thread-title h2') ? document.querySelector('.hsw-ac__thread-title h2').textContent : null,
    order: document.querySelector('.hsw-ac__thread-order') ? document.querySelector('.hsw-ac__thread-order').textContent : null,
  }));
  console.log('after create:', JSON.stringify(afterCreate));
  await page.screenshot({ path: `${OUT}/chat-thread.png` });

  // Reply inline.
  await page.fill('[data-hsw-reply-form] textarea', 'پیگیری شد؟');
  await page.click('.hsw-ac__composer-send');
  await page.waitForTimeout(1800);
  const bubbles = await page.$$eval('.hsw-ac__bubble', (els) => els.length);
  console.log('bubbles after reply:', bubbles);
  await page.screenshot({ path: `${OUT}/chat-reply.png` });

  // Mobile.
  await page.setViewportSize({ width: 390, height: 844 });
  await page.reload({ waitUntil: 'load', timeout: 60000 });
  await page.waitForTimeout(1500);
  await page.screenshot({ path: `${OUT}/chat-mobile.png` });

  await browser.close();
})().catch((e) => { console.error(e); process.exit(2); });
