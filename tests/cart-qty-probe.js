const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const p = await (await b.newContext({ viewport: { width: 1440, height: 1100 } })).newPage();
  await p.goto('http://localhost/sks/shop/', { waitUntil: 'load' });
  await p.waitForTimeout(800);
  const add = await p.$('[data-hsw-add]');
  if (add) { await add.click(); await p.waitForTimeout(2200); }

  await p.goto('http://localhost/sks/cart/', { waitUntil: 'load' });
  await p.waitForSelector('.hsw-c-qty__in', { timeout: 10000 });
  const before = await p.$eval('.hsw-c-qty__in', (i) => ({ v: i.value, ro: i.readOnly }));

  await p.click('.hsw-c-qty__in');
  await p.fill('.hsw-c-qty__in', '3');
  await Promise.all([
    p.waitForNavigation({ timeout: 20000 }).catch(() => {}),
    p.keyboard.press('Enter'),
  ]);
  await p.waitForTimeout(2000);
  const after = await p.$eval('.hsw-c-qty__in', (i) => i.value);

  // blur/change path
  await p.fill('.hsw-c-qty__in', '2');
  await Promise.all([
    p.waitForNavigation({ timeout: 20000 }).catch(() => {}),
    p.click('body'),
  ]);
  await p.waitForTimeout(2000);
  const afterBlur = await p.$eval('.hsw-c-qty__in', (i) => i.value);

  console.log(JSON.stringify({ before, afterEnter: after, afterBlur }, null, 2));
  await b.close();
})();
